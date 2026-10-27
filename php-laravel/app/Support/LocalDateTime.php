<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Parsing of the ISO-8601 date-times accepted by the API (`2026-11-03T14:30:00-03:00`, `...Z`,
 * `2026-11-03T14:30` without offset = America/Sao_Paulo), always normalized to the app timezone.
 *
 * Eloquent stores a Carbon instance using its own timezone, so values must be converted before they are
 * assigned: every datetime column holds São Paulo local time.
 */
final class LocalDateTime
{
    private const PATTERN = '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2})(?:\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})?$/';

    /**
     * Whether the value is a strict ISO-8601 date-time with a real calendar date (no "tomorrow", no 2026-02-30).
     */
    public static function isValid(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, $value, $parts)) {
            return false;
        }

        $offsetOk = ! isset($parts[7]) || $parts[7] === 'Z'
            || ((int) substr($parts[7], 1, 2) <= 14 && (int) substr($parts[7], -2) < 60);

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
            && (int) $parts[4] < 24
            && (int) $parts[5] < 60
            && (int) ($parts[6] ?? 0) < 60
            && $offsetOk;
    }

    /**
     * Parses a valid value (see isValid()) into the app timezone, dropping seconds' fractions.
     */
    public static function parse(string $value): Carbon
    {
        $timezone = self::timezone();

        return Carbon::parse($value, $timezone)->setTimezone($timezone)->startOfSecond();
    }

    /**
     * Start of the given São Paulo day (`YYYY-MM-DD`).
     */
    public static function startOfDay(string $date): Carbon
    {
        return Carbon::createFromFormat('!Y-m-d', $date, self::timezone()) ?: Carbon::now(self::timezone())->startOfDay();
    }

    public static function today(): Carbon
    {
        return Carbon::now(self::timezone())->startOfDay();
    }

    public static function timezone(): string
    {
        $timezone = config('app.timezone');

        return is_string($timezone) ? $timezone : 'America/Sao_Paulo';
    }
}
