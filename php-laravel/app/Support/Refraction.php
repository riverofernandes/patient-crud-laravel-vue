<?php

namespace App\Support;

/**
 * Formatting of refraction values for the glasses prescription.
 */
final class Refraction
{
    /** U+2212 MINUS SIGN: typographic minus, as printed on prescriptions. */
    public const MINUS = "\u{2212}";

    public const EMPTY = "\u{2014}";

    /**
     * Diopters with explicit sign and two decimals: +1.25, −0.50, 0.00; "—" when absent.
     */
    public static function diopters(?float $value): string
    {
        if ($value === null) {
            return self::EMPTY;
        }

        $formatted = number_format(abs($value), 2, '.', '');

        return match (true) {
            $value > 0 => '+'.$formatted,
            $value < 0 => self::MINUS.$formatted,
            default => $formatted,
        };
    }

    /**
     * Axis in degrees (e.g. 180°); "—" when absent.
     */
    public static function axis(?int $value): string
    {
        return $value === null ? self::EMPTY : $value."\u{00B0}";
    }
}
