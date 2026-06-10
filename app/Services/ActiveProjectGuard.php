<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Call;
use App\Models\Team;
use Carbon\CarbonInterface;

class ActiveProjectGuard
{
    private const FINISHED_STATUSES = [
        'rejected',
        'completed',
        'archived',
    ];

    public function findConflict(Team $team, Call $targetCall): ?Application
    {
        $memberIds = $team->members()->pluck('users.id');

        if ($memberIds->isEmpty()) {
            return null;
        }

        $applications = Application::query()
            ->with(['team:id,name', 'call:id,title,status,opens_at,closes_at', 'challenge:id,title'])
            ->whereNotIn('status', self::FINISHED_STATUSES)
            ->whereHas('team.members', function ($query) use ($memberIds) {
                $query->whereIn('users.id', $memberIds);
            })
            ->get();

        foreach ($applications as $application) {
            if (!$application->call) {
                return $application;
            }

            if ($application->call_id === $targetCall->id) {
                return $application;
            }

            if ($this->callsOverlap($application->call, $targetCall)) {
                return $application;
            }
        }

        return null;
    }

    public function conflictMessage(Application $application, Call $targetCall): string
    {
        $teamName = $application->team?->name ?? 'This team';
        $currentCall = $application->call?->title ?? 'another active call';
        $targetCallTitle = $targetCall->title;
        $projectTitle = $application->challenge?->title ?? 'active project/application';

        return "{$teamName} already has an active project/application ({$projectTitle}) in '{$currentCall}'. It cannot join '{$targetCallTitle}' while the call periods overlap or the current project is still active.";
    }

    private function callsOverlap(Call $first, Call $second): bool
    {
        $firstStart = $this->start($first);
        $firstEnd = $this->end($first);
        $secondStart = $this->start($second);
        $secondEnd = $this->end($second);

        return $firstStart <= $secondEnd && $secondStart <= $firstEnd;
    }

    private function start(Call $call): CarbonInterface
    {
        return $call->opens_at ?? $call->created_at ?? now()->subYears(50);
    }

    private function end(Call $call): CarbonInterface
    {
        return $call->closes_at ?? now()->addYears(50);
    }
}