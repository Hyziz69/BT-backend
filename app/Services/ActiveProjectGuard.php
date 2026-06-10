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
            ->with(['team:id,name', 'call:id,title,status,opens_at,closes_at', 'challenge:id,title,company_id'])
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
        $teamName = $application->team?->name ?? 'Your team';
        $currentCall = $application->call?->title ?? 'another call';

        return "Your team already has an active project in \"{$currentCall}\". Complete or archive it before joining \"{$targetCall->title}\".";
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