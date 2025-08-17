<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Services\RxnormService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DrugController extends Controller
{
    public function search(Request $request)
    {
        try {
            $drug_name = $request->validate(['drug_name' => 'required|string'])['drug_name'];

            $results = RxnormService::searchDrug($drug_name);

            return response()->json($results);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function add(Request $request)
    {
        try {
            $data = $request->validate([
                'rxcui' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        if (! RxnormService::isValidDrug($value)) {
                            $fail("The {$attribute}: {$value} does not exist.");
                        }
                    },
                ],
            ]);
            $rxcui = $data['rxcui'];

            $user = auth()->user();
            if ($user->medications()->where('rxcui', $rxcui)->exists()) {
                return response()->json(['error' => 'Drug already added'], 400);
            }

            $user->medications()->create(['rxcui' => $rxcui]);

            return response()->json(['message' => 'Drug added'], 201);
        } catch (ValidationException $e) {
            return response()->json(['errors' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function delete(Request $request)
    {
        $user = auth()->user();

        $rxcui = $request->validate([
            'rxcui' => [
                'required',
                'string',
                Rule::exists('medications', 'rxcui')
                    ->where(fn ($query) => $query->where('user_id', $user->id)),
            ],
        ]);

        $med = $user->medications()->where('rxcui', $rxcui)->first();

        if (! $med) {
            return response()->json(['error' => 'Drug not found'], 404);
        }

        $med->delete();

        return response()->json(['message' => 'Drug deleted']);
    }

    public function list()
    {
        $user = auth()->user();
        $meds = $user->medications;

        $results = [];
        foreach ($meds as $med) {
            $rxcui = $med->rxcui;

            $details = RxnormService::getDrugDetails($rxcui);

            $results[] = [
                'rxcui' => $rxcui,
                ...$details,
            ];
        }

        return response()->json($results);
    }
}
