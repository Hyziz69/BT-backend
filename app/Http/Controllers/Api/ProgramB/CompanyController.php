<?php

namespace App\Http\Controllers\Api\ProgramB;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        // Validate the incoming request. IČO must be unique across the whole table.
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'ICO'         => 'required|string|unique:companies,ICO|max:20',
            'sector'      => 'required|string|max:100',
            'description' => 'nullable|string',
            'website'     => 'nullable|url',
            'contacts'    => 'nullable|array', // expecting a JSON object from the frontend
        ]);

        try {
            // Create the new company record in the DB
            $company = Company::create($validated);

            return response()->json([
                'message' => 'Spoločnosť bola úspešne zaregistrovaná.',
                'company' => $company
            ], 201);

        } catch (\Exception $e) {
            // Log the error so we can debug it later if needed
            Log::error('Company registration failed: ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba servera.'], 500);
        }
    }

    public function show(Company $company): JsonResponse
    {
        // Just return the company data for now
        return response()->json([
            'company' => $company
        ], 200);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        // TODO add policy check here later so only the company admin can edit this profile

        // Using 'sometimes' so the frontend can send only the fields that were actually changed
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'ICO'         => 'sometimes|required|string|max:20|unique:companies,ICO,' . $company->id,
            'sector'      => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'website'     => 'nullable|url',
            'contacts'    => 'nullable|array',
        ]);

        try {
            // Update and fetch the fresh instance from the database
            $company->update($validated);

            return response()->json([
                'message' => 'Profil spoločnosti bol aktualizovaný.',
                'company' => $company->fresh()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Company update failed: ' . $e->getMessage());
            return response()->json(['message' => 'Vyskytla sa chyba servera.'], 500);
        }
    }
}
