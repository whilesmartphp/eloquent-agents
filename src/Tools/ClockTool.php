<?php

namespace Whilesmart\Agents\Tools;

use Illuminate\Support\Carbon;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * Gives the model the current date and time, plus common relative anchors.
 * Models have no clock of their own, so this is the cheapest high-value tool.
 */
class ClockTool extends AbstractTool
{
    public function name(): string
    {
        return 'clock';
    }

    public function description(): string
    {
        return 'Get the current date and time and common relative date anchors (today, start of month, etc.). Use this before reasoning about any relative date such as "last month" or "this week".';
    }

    public function parameters(): array
    {
        return [
            ParameterSpec::string('timezone', 'IANA timezone, e.g. "Europe/Berlin". Defaults to the application timezone.', required: false),
        ];
    }

    public function handle(array $arguments, ToolContext $context): string|array
    {
        $timezone = $arguments['timezone'] ?? config('app.timezone', 'UTC');

        try {
            $now = Carbon::now($timezone);
        } catch (\Throwable) {
            return 'Unknown timezone: '.$timezone;
        }

        return [
            'now' => $now->toIso8601String(),
            'date' => $now->toDateString(),
            'time' => $now->toTimeString(),
            'day_of_week' => $now->englishDayOfWeek,
            'timezone' => $now->getTimezone()->getName(),
            'start_of_today' => $now->copy()->startOfDay()->toIso8601String(),
            'start_of_this_month' => $now->copy()->startOfMonth()->toDateString(),
            'start_of_last_month' => $now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'end_of_last_month' => $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            'start_of_this_year' => $now->copy()->startOfYear()->toDateString(),
        ];
    }
}
