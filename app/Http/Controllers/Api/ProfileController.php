<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->buildProfilePayload($user, $user, true));
    }

    public function publicProfile(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        return response()->json($this->buildProfilePayload($user, $viewer, false));
    }

    public function profileCard(Request $request, User $user): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();

        $user->loadMissing(['company:id,name,sector,status', 'teams:id,name,leader_id']);

        $isAdmin = $this->isAdmin($viewer);
        $isSelf = $viewer->id === $user->id;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $isAdmin || $isSelf ? $user->email : null,
                'avatar_url' => $user->avatar_url,
                'account_type' => $user->account_type,
                'status' => $isAdmin || $isSelf ? $user->status : null,
                'bio' => $user->bio,
                'company' => $user->company ? [
                    'id' => $user->company->id,
                    'name' => $user->company->name,
                    'sector' => $user->company->sector,
                ] : null,
            ],
            'teams' => $user->teams->take(3)->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
            ])->values(),
        ]);
    }

    public function updateDetails(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'github_url' => ['nullable', 'url', 'max:255'],
            'portfolio_url' => ['nullable', 'url', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->update($validated);

        return response()->json([
            'message' => 'Profile details updated successfully.',
            'user' => $this->userPayload($user->fresh(), $user, true),
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->forceFill([
            'avatar_path' => $path,
        ])->save();

        return response()->json([
            'message' => 'Profile avatar updated successfully.',
            'user' => $this->userPayload($user->fresh(), $user, true),
        ]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $user->forceFill([
            'avatar_path' => null,
        ])->save();

        return response()->json([
            'message' => 'Profile avatar removed successfully.',
            'user' => $this->userPayload($user->fresh(), $user, true),
        ]);
    }

    public function uploadCv(Request $request): JsonResponse
    {
        $request->validate([
            'cv' => ['required', 'file', 'mimes:pdf,doc,docx,txt', 'max:5120'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->account_type !== 'student') {
            return response()->json([
                'message' => 'CV upload is available only for student accounts.',
            ], 403);
        }

        $profile = StudentProfile::firstOrCreate(['user_id' => $user->id]);

        if ($profile->cv_path) {
            Storage::disk('public')->delete($profile->cv_path);
        }

        $path = $request->file('cv')->store('cvs', 'public');

        $profile->forceFill([
            'cv_path' => $path,
            'cv_url' => null,
        ])->save();

        return response()->json([
            'message' => 'CV uploaded successfully.',
            'student_profile' => $this->studentProfilePayload($profile->fresh(), true),
        ]);
    }

    public function deleteCv(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->account_type !== 'student') {
            return response()->json([
                'message' => 'CV delete is available only for student accounts.',
            ], 403);
        }

        $profile = StudentProfile::firstOrCreate(['user_id' => $user->id]);

        if ($profile->cv_path) {
            Storage::disk('public')->delete($profile->cv_path);
        }

        $profile->forceFill([
            'cv_path' => null,
            'cv_url' => null,
        ])->save();

        return response()->json([
            'message' => 'CV removed successfully.',
            'student_profile' => $this->studentProfilePayload($profile->fresh(), true),
        ]);
    }

    public function updateStudentProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->account_type !== 'student') {
            return response()->json([
                'message' => 'Student profile can be updated only by student accounts.',
            ], 403);
        }

        $validated = $request->validate([
            'study_program' => ['nullable', 'string', 'max:200'],
            'study_year' => ['nullable', 'integer', 'min:1', 'max:8'],
            'skills' => ['nullable', 'array', 'max:20'],
            'skills.*' => ['string', 'max:100'],
            'academic_declaration' => ['boolean'],
        ]);

        $profile = StudentProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'study_program' => $validated['study_program'] ?? null,
                'study_year' => $validated['study_year'] ?? null,
                'skills' => array_values($validated['skills'] ?? []),
                'academic_declaration' => (bool) ($validated['academic_declaration'] ?? false),
            ]
        );

        return response()->json([
            'message' => 'Student profile updated successfully.',
            'student_profile' => $this->studentProfilePayload($profile->fresh(), true),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
                'errors' => [
                    'current_password' => ['Current password is incorrect.'],
                ],
            ], 422);
        }

        $user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        return response()->json([
            'message' => 'Password changed successfully.',
        ]);
    }

    private function buildProfilePayload(User $profileUser, User $viewer, bool $isSelf): array
    {
        $profileUser->loadMissing([
            'company:id,name,sector,status',
            'profile',
            'teams:id,name,leader_id,created_at',
            'ledTeams:id,name,leader_id,created_at',
        ]);

        $isAdmin = $this->isAdmin($viewer);
        $canSeePrivate = $isSelf || $isAdmin;

        $teamIds = $profileUser->teams()->pluck('teams.id')->unique()->values();

        $applicationsQuery = Application::query()
            ->whereIn('team_id', $teamIds)
            ->with([
                'team:id,name,leader_id',
                'call:id,title,status,program_id',
                'call.program:id,name,type',
            ]);

        $applicationStatuses = (clone $applicationsQuery)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'user' => $this->userPayload($profileUser, $viewer, $canSeePrivate),
            'student_profile' => $this->studentProfilePayload($profileUser->profile, $canSeePrivate),
            'stats' => [
                'teams_count' => $teamIds->count(),
                'led_teams_count' => $profileUser->ledTeams()->count(),
                'applications_count' => (clone $applicationsQuery)->count(),
                'submitted_applications_count' => (int) ($applicationStatuses['submitted'] ?? 0),
                'approved_applications_count' => (int) ($applicationStatuses['approved'] ?? 0),
                'rejected_applications_count' => (int) ($applicationStatuses['rejected'] ?? 0),
                'active_applications_count' => (int) ($applicationStatuses['active'] ?? 0),
                'mentor_assignments_count' => method_exists($profileUser, 'mentorships')
                    ? $profileUser->mentorships()->count()
                    : 0,
            ],
            'teams' => $profileUser->teams->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'role' => $canSeePrivate ? $team->pivot?->role : null,
                'joined_at' => $canSeePrivate && $team->pivot?->joined_at
                    ? (string) $team->pivot->joined_at
                    : null,
            ])->values(),
            'recent_applications' => (clone $applicationsQuery)
                ->latest('created_at')
                ->limit(5)
                ->get()
                ->map(fn (Application $application) => [
                    'id' => $application->id,
                    'status' => $application->status,
                    'submitted_at' => optional($application->submitted_at)->toISOString(),
                    'created_at' => optional($application->created_at)->toISOString(),
                    'team' => $application->team ? [
                        'id' => $application->team->id,
                        'name' => $application->team->name,
                    ] : null,
                    'call' => $application->call ? [
                        'id' => $application->call->id,
                        'title' => $application->call->title,
                        'status' => $application->call->status,
                        'program' => $application->call->program ? [
                            'id' => $application->call->program->id,
                            'name' => $application->call->program->name,
                            'type' => $application->call->program->type,
                        ] : null,
                    ] : null,
                ])
                ->values(),
        ];
    }

    private function userPayload(User $user, User $viewer, bool $canSeePrivate): array
    {
        return [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'email' => $canSeePrivate ? $user->email : null,
            'avatar_path' => $canSeePrivate ? $user->avatar_path : null,
            'avatar_url' => $user->avatar_url,
            'bio' => $user->bio,
            'phone' => $canSeePrivate ? $user->phone : null,
            'linkedin_url' => $user->linkedin_url,
            'github_url' => $user->github_url,
            'portfolio_url' => $user->portfolio_url,
            'account_type' => $user->account_type,
            'status' => $canSeePrivate ? $user->status : null,
            'gdpr_consent' => $canSeePrivate ? (bool) $user->gdpr_consent : null,
            'gdpr_consented_at' => $canSeePrivate ? optional($user->gdpr_consented_at)->toISOString() : null,
            'email_verified_at' => $canSeePrivate ? optional($user->email_verified_at)->toISOString() : null,
            'created_at' => optional($user->created_at)->toISOString(),
            'company' => $user->company ? [
                'id' => $user->company->id,
                'name' => $user->company->name,
                'sector' => $user->company->sector,
                'status' => $canSeePrivate ? $user->company->status : null,
            ] : null,
        ];
    }

    private function studentProfilePayload(?StudentProfile $profile, bool $canSeePrivate): ?array
    {
        if (!$profile) {
            return null;
        }

        return [
            'study_program' => $profile->study_program,
            'study_year' => $profile->study_year,
            'skills' => $profile->skills ?? [],
            'cv_path' => $canSeePrivate ? $profile->cv_path : null,
            'cv_url' => $canSeePrivate ? $profile->cv_download_url : null,
            'academic_declaration' => $canSeePrivate ? (bool) $profile->academic_declaration : null,
            'academic_notes' => $canSeePrivate ? $profile->academic_notes : null,
        ];
    }

    private function isAdmin(User $user): bool
    {
        return in_array($user->account_type, ['nti_admin', 'superadmin'], true);
    }
}