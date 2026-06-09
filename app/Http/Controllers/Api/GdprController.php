<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GdprController extends Controller
{
    public function export(Request $request): Response
    {
        $user = $request->user();

        $user->load([
            'profile',
            'teams.members',
            'teams.applications',
            'mentorships.consultations',
            'evaluations',
            'notifications',
        ]);

        $data = [
            'exported_at' => now()->toDateTimeString(),
            'user' => [
                'id'           => $user->id,
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'email'        => $user->email,
                'account_type' => $user->account_type,
                'status'       => $user->status,
                'created_at'   => $user->created_at,
            ],
            'profile'      => $user->profile,
            'teams'        => $user->teams,
            'mentorships'  => $user->mentorships,
            'evaluations'  => $user->evaluations,
            'notifications' => $user->notifications,
        ];

        $filename = 'nti-personal-data-' . $user->id . '-' . now()->format('Y-m-d') . '.json';

        return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 200, [
            'Content-Type'        => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function requestDeletion(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->status === 'pending_deletion') {
            return response()->json(['message' => 'Deletion request already pending.'], 422);
        }

        $user->update(['status' => 'pending_deletion']);

        AuditEvent::create([
            'actor_id'    => $user->id,
            'action'      => 'gdpr.deletion_requested',
            'entity_type' => get_class($user),
            'entity_id'   => $user->id,
            'payload'     => ['email' => $user->email],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return response()->json(['message' => 'Deletion request submitted. An admin will process it shortly.']);
    }

    public function cancelDeletion(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->status !== 'pending_deletion') {
            return response()->json(['message' => 'No pending deletion request.'], 422);
        }

        $user->update(['status' => 'active']);

        return response()->json(['message' => 'Deletion request cancelled.']);
    }
}