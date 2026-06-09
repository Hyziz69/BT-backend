<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class AdminReportController extends Controller
{
    public function __construct(
        private readonly AdminReportService $reportService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return response()->json([
            'reports' => $this->reportService->availableReports(),
            'summary' => $this->reportService->summary($filters),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        return response()->json(
            $this->reportService->summary($this->validatedFilters($request))
        );
    }

    public function download(Request $request, string $type): Response
    {
        abort_unless(
            in_array($type, ['summary', 'users', 'companies', 'teams', 'applications', 'mentors'], true),
            404,
            'Report type not found.'
        );

        return $this->reportService->csvResponse(
            $type,
            $this->validatedFilters($request)
        );
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', 'string', 'max:100'],
            'account_type' => ['nullable', 'string', 'max:100'],
            'program_type' => ['nullable', Rule::in(['program_a', 'program_b'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
    }
}