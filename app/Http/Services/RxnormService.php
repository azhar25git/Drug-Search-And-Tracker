<?php

namespace App\Http\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RxnormService
{
    private static int $expirySeconds = 3600;

    /**
     * @throws Exception
     */
    public static function searchDrug(string $drug_name, string $ttyName = 'SBD', int $limit = 5): array
    {
        $searchCacheKey = "search_{$ttyName}_".md5($drug_name);
        $results = Cache::remember($searchCacheKey, self::$expirySeconds, function () use ($drug_name, $ttyName, $limit) {
            $response = self::_getDrugsByName($drug_name);
            if ($response->failed()) {
                throw new \Exception('RxNorm API error');
            }

            $data = $response->json();
            $ttyGroup = collect($data['drugGroup']['conceptGroup'] ?? [])->firstWhere('tty', $ttyName);
            $ttys = $ttyGroup['conceptProperties'] ?? [];
            $topResults = array_slice($ttys, 0, $limit);

            $results = [];
            foreach ($topResults as $item) {
                $rxcui = $item['rxcui'];
                $name = $item['name'];

                $histCacheKey = "history_{$rxcui}";
                $histData = Cache::remember($histCacheKey, self::$expirySeconds, function () use ($rxcui) {
                    $histResponse = self::_getHistoryStatus($rxcui);
                    if ($histResponse->failed()) {
                        throw new \Exception('RxNorm history API error');
                    }

                    return $histResponse->json()['rxcuiStatusHistory'];
                });

                $ingredients = collect($histData['definitionalFeatures']['ingredientAndStrength'] ?? [])->pluck('baseName')->unique()->values()->all();
                $dosages = collect($histData['definitionalFeatures']['doseFormGroupConcept'] ?? [])->pluck('doseFormGroupName')->unique()->values()->all();

                $results[] = [
                    'rxcui' => $rxcui,
                    'name' => $name,
                    'ingredient_base_names' => $ingredients,
                    'dosage_forms' => $dosages,
                ];
            }

            return $results;
        });

        return $results;
    }

    public static function isValidDrug(string $rxcui): bool
    {
        $histCacheKey = 'history_'.$rxcui;
        $histData = Cache::remember($histCacheKey, self::$expirySeconds, function () use ($rxcui) {
            $histResponse = self::_getHistoryStatus($rxcui);
            $statusHistory = $histResponse->json()['rxcuiStatusHistory'] ?? [];

            if ($histResponse->failed()
                || empty($statusHistory)
                || empty($statusHistory['attributes'])
                || empty($statusHistory['attributes']['name'])
            ) {
                return false;
            }

            return true;
        });

        return $histData;
    }

    /**
     * @throws Exception
     */
    public static function getDrugDetails(string $rxcui): array
    {
        $detailsCacheKey = 'drug_details_'.$rxcui;
        $details = Cache::remember($detailsCacheKey, self::$expirySeconds, function () use ($rxcui) {
            $histResponse = self::_getHistoryStatus($rxcui);
            if ($histResponse->failed()) {
                throw new \Exception('RxNorm API error');
            }
            $histData = $histResponse->json()['rxcuiStatusHistory'];

            $name = $histData['attributes']['name'] ?? 'Unknown';
            $ingredients = collect($histData['definitionalFeatures']['ingredientAndStrength'] ?? [])->pluck('baseName')->unique()->values()->all();
            $dosages = collect($histData['definitionalFeatures']['doseFormGroupConcept'] ?? [])->pluck('doseFormGroupName')->unique()->values()->all();

            return [
                'name' => $name,
                'baseNames' => $ingredients,
                'doseFormGroupName' => $dosages,
            ];
        });

        return $details;
    }

    private static function _getHistoryStatus(string $rxcui)
    {
        return Http::get("https://rxnav.nlm.nih.gov/REST/rxcui/{$rxcui}/historystatus.json");
    }

    private static function _getDrugsByName(string $drug_name)
    {
        return Http::get("https://rxnav.nlm.nih.gov/REST/drugs.json?name={$drug_name}");
    }
}
