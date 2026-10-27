<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * `per_page` query parameter shared by the paginated listings: 1 to 100 (invalid -> 10, larger -> 100).
 */
final class PerPage
{
    public const DEFAULT = 10;

    public const MAX = 100;

    public static function from(Request $request): int
    {
        $perPage = filter_var($request->query('per_page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            ?: self::DEFAULT;

        return min($perPage, self::MAX);
    }
}
