<?php

namespace App\Http\Controllers\Api\ProgramB;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only company representatives may register a company.
        if ($user->account_type !== 'company_contact') {
            return response()->json(['message' => 'Iba zástupcovia spoločnosti môžu zaregistrovať spoločnosť.'], 403);
        }

        // A user can only belong to one company at a time.
        if ($user->belongsToCompany()) {
            return response()->json(['message' => 'Už ste priradený k spoločnosti.'], 422);
        }

        // Validate the incoming request. IČO must be unique across the whole table.
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'ico'         => 'required|string|unique:companies,ico|max:20',
            'sector'      => 'required|string|max:100',
            'description' => 'nullable|string',
            'website'     => 'nullable|url',
        ]);

        try {
            // Create the company and link the creator as its owner in a single transaction.
            $company = DB::transaction(function () use ($validated, $user) {
                $company = Company::create($validated + ['status' => 'active']);

                $user->update([
                    'company_id'   => $company->id,
                    'company_role' => 'owner',
                ]);

                return $company;
            });

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
        // Only owners/managers of this company (or admins) may edit the profile.
        if ($request->user()->cannot('update', $company)) {
            return response()->json(['message' => 'Prístup zamietnutý.'], 403);
        }

        // Using 'sometimes' so the frontend can send only the fields that were actually changed
        $validated = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'ico'         => 'sometimes|required|string|max:20|unique:companies,ico,' . $company->id,
            'sector'      => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'website'     => 'nullable|url',
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
