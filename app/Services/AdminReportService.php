<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Call;
use App\Models\Company;
use App\Models\Document;
use App\Models\Mentorship;
use App\Models\Program;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AdminReportService
{
    public function availableReports(): array
    {
        return [
            [
                'type' => 'summary',
                'name' => 'Platform summary',
                'description' => 'High-level overview of users, programs, calls, teams, applications and mentorships.',
            ],
            [
                'type' => 'users',
                'name' => 'Users',
                'description' => 'Export registered users with role, status, company and activity counts.',
            ],
            [
                'type' => 'companies',
                'name' => 'Companies',
                'description' => 'Export companies with status, sector, members and challenges.',
            ],
            [
                'type' => 'teams',
                'name' => 'Teams',
                'description' => 'Export teams with leader, member count and application count.',
            ],
            [
                'type' => 'applications',
                'name' => 'Applications',
                'description' => 'Export applications with call, program, team, challenge, status and score.',
            ],
            [
                'type' => 'mentors',
                'name' => 'Mentors',
                'description' => 'Export mentors with mentorship and evaluation activity.',
            ],
        ];
    }

    public function summary(array $filters = []): array
    {
        return [
            'generated_at' => now()->toDateTimeString(),
            'filters' => $this->cleanFilters($filters),

            'users' => [
                'total' => User::query()->count(),
                'active' => User::query()->where('status', 'active')->count(),
                'pending' => User::query()->where('status', 'pending')->count(),
                'suspended' => User::query()->where('status', 'suspended')->count(),
                'by_role' => $this->countByColumn(User::query(), 'account_type'),
                'by_status' => $this->countByColumn(User::query(), 'status'),
            ],

            'companies' => [
                'total' => Company::query()->count(),
                'active' => Company::query()->where('status', 'active')->count(),
                'pending' => Company::query()->where('status', 'pending')->count(),
                'inactive' => Company::query()->where('status', 'inactive')->count(),
                'by_status' => $this->countByColumn(Company::query(), 'status'),
                'by_sector' => $this->countByColumn(Company::query(), 'sector'),
            ],

            'programs' => [
                'total' => Program::query()->count(),
                'active' => Program::query()->where('is_active', true)->count(),
                'by_type' => $this->countByColumn(Program::query(), 'type'),
            ],

            'calls' => [
                'total' => Call::query()->count(),
                'open' => Call::query()->where('status', 'open')->count(),
                'draft' => Call::query()->where('status', 'draft')->count(),
                'evaluating' => Call::query()->where('status', 'evaluating')->count(),
                'closed' => Call::query()->where('status', 'closed')->count(),
                'by_status' => $this->countByColumn(Call::query(), 'status'),
            ],

            'teams' => [
                'total' => Team::query()->count(),
                'members_total' => DB::table('team_members')->count(),
                'average_members' => $this->averageTeamMembers(),
            ],

            'applications' => [
                'total' => $this->applicationQuery($filters)->count(),
                'submitted' => $this->applicationQuery($filters)->whereNotNull('submitted_at')->count(),
                'approved' => $this->applicationQuery($filters)->where('status', 'approved')->count(),
                'rejected' => $this->applicationQuery($filters)->where('status', 'rejected')->count(),
                'active' => $this->applicationQuery($filters)->where('status', 'active')->count(),
                'pending' => $this->applicationQuery($filters)->whereIn('status', [
                    'draft',
                    'submitted',
                    'formally_verified',
                    'in_evaluation',
                    'pending_supplement',
                ])->count(),
                'by_status' => $this->countByColumn($this->applicationQuery($filters), 'status'),
            ],

            'documents' => [
                'total' => Document::query()->count(),
                'by_type' => $this->countByColumn(Document::query(), 'doc_type'),
                'by_classification' => $this->countByColumn(Document::query(), 'classification'),
            ],

            'mentorships' => [
                'total' => Mentorship::query()->count(),
                'active' => Mentorship::query()->whereNull('ended_at')->count(),
                'ended' => Mentorship::query()->whereNotNull('ended_at')->count(),
            ],
        ];
    }

    public function csvResponse(string $type, array $filters = []): Response
    {
        $filename = 'nti-' . $type . '-report-' . now()->format('Y-m-d-H-i') . '.csv';

        return response($this->csv($type, $filters), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function csv(string $type, array $filters = []): string
    {
        return match ($type) {
            'summary' => $this->summaryCsv($filters),
            'users' => $this->usersCsv($filters),
            'companies' => $this->companiesCsv($filters),
            'teams' => $this->teamsCsv($filters),
            'applications' => $this->applicationsCsv($filters),
            'mentors' => $this->mentorsCsv($filters),
            default => throw new InvalidArgumentException('Unknown report type.'),
        };
    }

    private function usersCsv(array $filters): string
    {
        $query = User::query()
            ->with('company')
            ->withCount(['teams', 'ledTeams', 'mentorships', 'evaluations']);

        if (!empty($filters['account_type'])) {
            $query->where('account_type', $filters['account_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $this->toCsv(array_merge(
            [[
                'ID',
                'First name',
                'Last name',
                'Email',
                'Role',
                'Status',
                'Company',
                'Company role',
                'Teams',
                'Led teams',
                'Mentorships',
                'Evaluations',
                'Created at',
            ]],
            $query->orderByDesc('created_at')->get()->map(fn (User $user) => [
                $user->id,
                $user->first_name,
                $user->last_name,
                $user->email,
                $user->account_type,
                $user->status,
                $user->company?->name,
                $user->company_role,
                $user->teams_count,
                $user->led_teams_count,
                $user->mentorships_count,
                $user->evaluations_count,
                $this->dateValue($user->created_at),
            ])->toArray()
        ));
    }

    private function companiesCsv(array $filters): string
    {
        $query = Company::query()
            ->withCount(['users', 'challenges', 'invitations']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $this->toCsv(array_merge(
            [[
                'ID',
                'Name',
                'ICO',
                'Sector',
                'Status',
                'Website',
                'Users',
                'Challenges',
                'Invitations',
                'Created at',
            ]],
            $query->orderByDesc('created_at')->get()->map(fn (Company $company) => [
                $company->id,
                $company->name,
                $company->ico,
                $company->sector,
                $company->status,
                $company->website,
                $company->users_count,
                $company->challenges_count,
                $company->invitations_count,
                $this->dateValue($company->created_at),
            ])->toArray()
        ));
    }

    private function teamsCsv(array $filters): string
    {
        return $this->toCsv(array_merge(
            [[
                'ID',
                'Team name',
                'Leader',
                'Leader email',
                'Members',
                'Applications',
                'Competencies',
                'Created at',
            ]],
            Team::query()
                ->with('leader')
                ->withCount(['members', 'applications'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Team $team) => [
                    $team->id,
                    $team->name,
                    $team->leader?->full_name,
                    $team->leader?->email,
                    $team->members_count,
                    $team->applications_count,
                    $this->arrayValue($team->competencies),
                    $this->dateValue($team->created_at),
                ])->toArray()
        ));
    }

    private function applicationsCsv(array $filters): string
    {
        return $this->toCsv(array_merge(
            [[
                'ID',
                'Program',
                'Call',
                'Team',
                'Team leader',
                'Challenge',
                'Company',
                'Status',
                'Score',
                'Submitted at',
                'Decided at',
                'Documents',
                'Evaluations',
                'Milestones',
                'Mentorships',
                'Created at',
            ]],
            $this->applicationQuery($filters)
                ->with([
                    'team:id,name,leader_id',
                    'team.leader:id,first_name,last_name,email',
                    'call:id,program_id,title,status',
                    'call.program:id,type,name',
                    'challenge:id,title,company_id,status',
                    'challenge.company:id,name',
                ])
                ->withCount(['documents', 'evaluations', 'milestones', 'mentorships'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Application $application) => [
                    $application->id,
                    $application->call?->program?->name ?? $application->call?->program?->type,
                    $application->call?->title,
                    $application->team?->name,
                    $application->team?->leader?->full_name,
                    $application->challenge?->title,
                    $application->challenge?->company?->name,
                    $application->status,
                    $application->score,
                    $this->dateValue($application->submitted_at),
                    $this->dateValue($application->decided_at),
                    $application->documents_count,
                    $application->evaluations_count,
                    $application->milestones_count,
                    $application->mentorships_count,
                    $this->dateValue($application->created_at),
                ])->toArray()
        ));
    }

    private function mentorsCsv(array $filters): string
    {
        $query = User::query()
            ->where('account_type', 'mentor')
            ->withCount([
                'mentorships',
                'evaluations',
                'mentorships as active_mentorships_count' => fn (Builder $query) => $query->whereNull('ended_at'),
            ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $this->toCsv(array_merge(
            [[
                'ID',
                'First name',
                'Last name',
                'Email',
                'Status',
                'Mentorships',
                'Active mentorships',
                'Evaluations',
                'Created at',
            ]],
            $query->orderByDesc('created_at')->get()->map(fn (User $mentor) => [
                $mentor->id,
                $mentor->first_name,
                $mentor->last_name,
                $mentor->email,
                $mentor->status,
                $mentor->mentorships_count,
                $mentor->active_mentorships_count,
                $mentor->evaluations_count,
                $this->dateValue($mentor->created_at),
            ])->toArray()
        ));
    }

    private function summaryCsv(array $filters): string
    {
        $rows = [[
            'Section',
            'Metric',
            'Value',
        ]];

        foreach ($this->summary($filters) as $section => $value) {
            if (is_array($value)) {
                foreach ($value as $metric => $metricValue) {
                    $rows[] = [
                        $section,
                        $metric,
                        is_array($metricValue) ? json_encode($metricValue, JSON_UNESCAPED_UNICODE) : $metricValue,
                    ];
                }
            } else {
                $rows[] = [
                    'general',
                    $section,
                    $value,
                ];
            }
        }

        return $this->toCsv($rows);
    }

    private function applicationQuery(array $filters): Builder
    {
        $query = Application::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['program_type'])) {
            $query->whereHas('call.program', function (Builder $programQuery) use ($filters) {
                $programQuery->where('type', $filters['program_type']);
            });
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    private function countByColumn(Builder $query, string $column): array
    {
        return (clone $query)
            ->select($column, DB::raw('count(*) as total'))
            ->groupBy($column)
            ->orderByDesc('total')
            ->get()
            ->mapWithKeys(fn ($row) => [($row->{$column} ?: 'empty') => (int) $row->total])
            ->toArray();
    }

    private function averageTeamMembers(): float
    {
        $teams = Team::query()->count();

        if ($teams === 0) {
            return 0;
        }

        return round(DB::table('team_members')->count() / $teams, 2);
    }

    private function cleanFilters(array $filters): array
    {
        return collect($filters)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->toArray();
    }

    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);

        return stream_get_contents($handle) ?: '';
    }

    private function dateValue($value): ?string
    {
        return $value ? $value->toDateTimeString() : null;
    }

    private function arrayValue($value): string
    {
        if (is_array($value)) {
            return implode(', ', $value);
        }

        return (string) ($value ?? '');
    }
}