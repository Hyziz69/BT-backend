<?php

namespace App\Http\Controllers\Api\ProgramA;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProgramA\StoreDocumentRequest;
use App\Http\Resources\ProgramA\DocumentResource;
use App\Models\Application;
use App\Models\Document;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public const PROGRAM_A_DOC_TYPES = [
        'executive_summary',
        'tech_architecture',
        'roadmap',
        'budget',
        'risk_analysis',
        'monetization',
        'cv',
        'attachment',
        'other',
    ];

    private const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg',
        'image/png',
        'text/plain',
    ];

    public function index(Request $request, Application $application): JsonResponse
    {
        $this->authorizeAccess($request->user(), $application);

        $documents = $application->documents()
            ->orderBy('doc_type')
            ->orderByDesc('version')
            ->get();

        return response()->json([
            'data' => DocumentResource::collection($documents),
        ]);
    }

    public function store(StoreDocumentRequest $request, Application $application): JsonResponse
    {
        $user = $request->user();
        $this->authorizeUpload($user, $application);

        if (!in_array($user->account_type, ['nti_admin', 'superadmin']) &&
            !in_array($application->status, ['draft', 'pending_supplement'])) {
            return response()->json(['message' => 'Documents cannot be uploaded after submission.'], 422);
        }

        $file    = $request->file('file');
        $docType = $request->doc_type;

        if (!in_array($file->getMimeType(), self::ALLOWED_MIME_TYPES)) {
            return response()->json(['message' => 'File type not permitted.'], 422);
        }

        $latestVersion = $application->documents()
            ->where('doc_type', $docType)
            ->max('version') ?? 0;

        $nextVersion = $latestVersion + 1;

        $path = $file->storeAs(
            "applications/{$application->id}/{$docType}",
            "v{$nextVersion}_" . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
                . '.' . $file->getClientOriginalExtension(),
            'local'
        );

        $document = Document::create([
            'application_id' => $application->id,
            'uploaded_by'    => $user->id,
            'doc_type'       => $docType,
            'classification' => $request->classification ?? 'internal',
            'filename'       => $file->getClientOriginalName(),
            'file_path'      => $path,
            'mime_type'      => $file->getMimeType(),
            'file_size'      => $file->getSize(),
            'version'        => $nextVersion,
        ]);

        AuditEvent::create([
            'actor_id'    => $user->id,
            'action'      => 'document.uploaded',
            'entity_type' => Document::class,
            'entity_id'   => $document->id,
            'payload'     => [
                'application_id' => $application->id,
                'doc_type'       => $docType,
                'version'        => $nextVersion,
                'filename'       => $file->getClientOriginalName(),
            ],
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
        ]);

        return response()->json([
            'message' => 'Document uploaded successfully.',
            'data'    => new DocumentResource($document),
        ], 201);
    }

    public function download(Request $request, Application $application, Document $document): mixed
    {
        $this->authorizeAccess($request->user(), $application);

        if ($document->application_id !== $application->id) {
            abort(404);
        }

        if (!Storage::disk('local')->exists($document->file_path)) {
            return response()->json(['message' => 'File not found on storage.'], 404);
        }

        return Storage::disk('local')->download($document->file_path, $document->filename);
    }

    public function checklist(Request $request, Application $application): JsonResponse
    {
        $this->authorizeAccess($request->user(), $application);

        $required = [
            'executive_summary',
            'tech_architecture',
            'roadmap',
            'budget',
            'risk_analysis',
            'monetization',
        ];

        $uploaded = $application->documents()
            ->whereIn('doc_type', $required)
            ->get()
            ->keyBy('doc_type');

        $checklist = collect($required)->map(fn ($type) => [
            'doc_type'    => $type,
            'uploaded'    => $uploaded->has($type),
            'version'     => $uploaded->get($type)?->version,
            'filename'    => $uploaded->get($type)?->filename,
            'uploaded_at' => $uploaded->get($type)?->created_at,
        ]);

        $allUploaded = $checklist->every(fn ($item) => $item['uploaded']);

        return response()->json([
            'data' => [
                'ready_to_submit' => $allUploaded,
                'checklist'       => $checklist,
            ],
        ]);
    }

    public function destroy(Request $request, Application $application, Document $document): JsonResponse
    {
        $user = $request->user();
        $this->authorizeUpload($user, $application);

        if ($document->application_id !== $application->id) {
            abort(404);
        }

        if (!in_array($application->status, ['draft', 'pending_supplement'])) {
            return response()->json(['message' => 'Documents cannot be deleted after submission.'], 422);
        }

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return response()->json(['message' => 'Document deleted.']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function authorizeAccess($user, Application $application): void
    {
        if (in_array($user->account_type, ['nti_admin', 'superadmin', 'evaluator', 'mentor'])) {
            return;
        }

        $isMember = $application->team->members()->where('user_id', $user->id)->exists();
        if (!$isMember) {
            abort(403, 'You do not have access to this application\'s documents.');
        }
    }

    private function authorizeUpload($user, Application $application): void
    {
        if (in_array($user->account_type, ['nti_admin', 'superadmin'])) {
            return;
        }

        $isLeader = $application->team->leader_id === $user->id;
        if (!$isLeader) {
            abort(403, 'Only the team leader can upload documents.');
        }
    }
}