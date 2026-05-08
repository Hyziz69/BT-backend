<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentProfileController extends Controller
{
    /**
     * Get the authenticated student's profile.
     */
    public function show(Request $request): JsonResponse
    {
//        $user = $request->user();
          $user = \App\Models\User::where('account_type', 'student')->first();
        $profile = StudentProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'study_program'       => null,
                'study_year'          => null,
                'skills'              => [],
                'cv_url'              => null,
                'academic_declaration'=> false,
            ]
        );

        return response()->json([
            'data' => $this->formatProfile($user, $profile),
        ]);
    }

    /**
     * Update the authenticated student's profile and handle CV upload.
     */
    public function update(Request $request): JsonResponse
    {
//        $user = $request->user();
          $user = \App\Models\User::where('account_type', 'student')->first();

        if ($user->account_type !== 'student') {
            return response()->json(['message' => 'Only students have profiles.'], 403);
        }

        // Added cv_file to validation. Max 5MB, PDF/DOC formats.
        $validated = $request->validate([
            'study_program'        => ['nullable', 'string', 'max:200'],
            'study_year'           => ['nullable', 'integer', 'min:1', 'max:6'],
            'skills'               => ['nullable', 'array'],
            'skills.*'             => ['string', 'max:100'],
            'academic_declaration' => ['nullable', 'boolean'],
            'cv_file'              => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        $profile = StudentProfile::firstOrCreate(['user_id' => $user->id]);

        // Handle the physical file upload if it exists in the request
        if ($request->hasFile('cv_file')) {
            // Clean up old file from storage to save space
            if ($profile->cv_url) {
                Storage::disk('public')->delete($profile->cv_url);
            }

            // Store new file in 'public/cvs' directory
            $path = $request->file('cv_file')->store('cvs', 'public');
            $validated['cv_url'] = $path;
        }

        // Remove the helper 'cv_file' field before saving to DB
        unset($validated['cv_file']);

        $profile->update($validated);

        return response()->json([
            'message' => 'Profile updated.',
            'data'    => $this->formatProfile($user, $profile),
        ]);
    }

    private function formatProfile($user, StudentProfile $profile): array
    {
        return [
            'user' => [
                'id'           => $user->id,
                'name'         => $user->first_name . ' ' . $user->last_name,
                'email'        => $user->email,
                'account_type' => $user->account_type,
            ],
            'study_program'        => $profile->study_program,
            'study_year'           => $profile->study_year,
            'skills'               => $profile->skills ?? [],
            'cv_url'               => $profile->cv_url,
            'academic_declaration' => $profile->academic_declaration,
            'academic_notes'       => $profile->academic_notes,
        ];
    }
}
