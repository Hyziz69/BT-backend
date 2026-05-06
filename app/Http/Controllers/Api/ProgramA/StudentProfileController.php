<?php

namespace App\Http\Controllers\Api\ProgramA;

use App\Http\Controllers\Controller;
use App\Models\StudentProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentProfileController extends Controller
{
    /**
     * Get the authenticated student's profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

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
     * Update the authenticated student's profile.
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->account_type !== 'student') {
            return response()->json(['message' => 'Only students have profiles.'], 403);
        }

        $validated = $request->validate([
            'study_program'        => ['nullable', 'string', 'max:200'],
            'study_year'           => ['nullable', 'integer', 'min:1', 'max:6'],
            'skills'               => ['nullable', 'array'],
            'skills.*'             => ['string', 'max:100'],
            'academic_declaration' => ['nullable', 'boolean'],
        ]);

        $profile = StudentProfile::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );

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