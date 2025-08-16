<?php

namespace App\Http\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class RxnormService
{
    public static int $expirySeconds = 3600;

    /**
     * @throws Exception
     */
    public static function searchDrug(string $drug_name): array
    {
        $cacheKey = 'search_'.md5($drug_name);
        $results = Cache::remember($cacheKey, self::$expirySeconds, function () use ($drug_name) {
            $response = self::_getDrugsByName($drug_name);
            if ($response->failed()) {
                throw new \Exception('RxNorm API error');
            }

            $data = $response->json();
            $sbdGroup = collect($data['drugGroup']['conceptGroup'] ?? [])->firstWhere('tty', 'SBD');
            $sbds = $sbdGroup['conceptProperties'] ?? [];
            $top5 = array_slice($sbds, 0, 5);

            $results = [];
            foreach ($top5 as $sbd) {
                $rxcui = $sbd['rxcui'];
                $name = $sbd['name'];

                $histCacheKey = 'history_'.$rxcui;
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

    public static function doesDrugExist(string $rxcui): bool
    {
        $histCacheKey = 'history_'.$rxcui;
        $histData = Cache::remember($histCacheKey, self::$expirySeconds, function () use ($rxcui) {
            $histResponse = self::_getHistoryStatus($rxcui);

            if ($histResponse->failed() || empty($histResponse->json()['rxcuiStatusHistory'])) {
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
