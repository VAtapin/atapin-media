<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Carbon;

class TaskRecurrence
{
    /** @return list<string> */
    public function dates(Task $task, Carbon $start, Carbon $end, string $timezone): array
    {
        if (!$task->due_date) return [];

        $anchor = Carbon::parse($task->due_date->format('Y-m-d'), $timezone)->startOfDay();
        $until = $task->recurrence_until
            ? Carbon::parse($task->recurrence_until->format('Y-m-d'), $timezone)->endOfDay()
            : $end->copy();
        if ($until->gt($end)) $until = $end->copy();
        if ($anchor->gt($until)) return [];

        $recurrence = $task->recurrence ?: 'once';
        if ($recurrence === 'once') {
            return $anchor->betweenIncluded($start, $until) ? [$anchor->toDateString()] : [];
        }

        $interval = max(1, (int) ($task->recurrence_interval ?: 1));
        $unit = $recurrence === 'custom'
            ? $task->recurrence_unit
            : match ($recurrence) { 'daily' => 'day', 'weekly' => 'week', 'monthly' => 'month', default => null };
        if (!in_array($unit, Task::RECURRENCE_UNITS, true)) return [];

        $index = 0;
        if ($anchor->lt($start)) {
            if ($unit === 'month') {
                $months = max(0, (int) floor($anchor->diffInMonths($start)));
                $index = intdiv($months, $interval);
            } else {
                $stepDays = $interval * ($unit === 'week' ? 7 : 1);
                $days = max(0, (int) floor($anchor->diffInDays($start)));
                $index = intdiv($days, $stepDays);
            }
        }

        $date = $this->at($anchor, $unit, $interval, $index);
        while ($date->lt($start)) {
            $date = $this->at($anchor, $unit, $interval, ++$index);
        }

        $dates = [];
        while ($date->lte($until) && count($dates) < 1000) {
            $dates[] = $date->toDateString();
            $date = $this->at($anchor, $unit, $interval, ++$index);
        }

        return $dates;
    }

    private function at(Carbon $anchor, string $unit, int $interval, int $index): Carbon
    {
        $amount = $interval * $index;
        return match ($unit) {
            'day' => $anchor->copy()->addDays($amount),
            'week' => $anchor->copy()->addWeeks($amount),
            'month' => $anchor->copy()->addMonthsNoOverflow($amount),
        };
    }
}
