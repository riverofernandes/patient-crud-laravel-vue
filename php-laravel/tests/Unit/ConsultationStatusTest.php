<?php

namespace Tests\Unit;

use App\Enums\ConsultationStatus;
use App\Support\LocalDateTime;
use App\Support\Refraction;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConsultationStatusTest extends TestCase
{
    /**
     * Full from x to matrix (25 pairs), including same-status "transitions".
     *
     * @return array<string, array{ConsultationStatus, ConsultationStatus, bool}>
     */
    public static function transitions(): array
    {
        $allowed = [
            'scheduled' => ['confirmed', 'completed', 'no_show', 'cancelled'],
            'confirmed' => ['completed', 'no_show', 'cancelled'],
            'completed' => [],
            'no_show' => [],
            'cancelled' => [],
        ];

        $cases = [];

        foreach (ConsultationStatus::cases() as $from) {
            foreach (ConsultationStatus::cases() as $to) {
                $cases["{$from->value} -> {$to->value}"] = [$from, $to, in_array($to->value, $allowed[$from->value], true)];
            }
        }

        return $cases;
    }

    #[DataProvider('transitions')]
    public function test_transition_matrix(ConsultationStatus $from, ConsultationStatus $to, bool $expected): void
    {
        $this->assertSame($expected, $from->canTransitionTo($to));
    }

    public function test_final_and_blocking_statuses(): void
    {
        $this->assertSame(
            ['completed', 'no_show', 'cancelled'],
            array_values(array_map(fn ($s) => $s->value, array_filter(ConsultationStatus::cases(), fn ($s) => $s->isFinal())))
        );
        $this->assertSame(['scheduled', 'confirmed', 'completed'], ConsultationStatus::blockingValues());
        $this->assertSame('não compareceu', ConsultationStatus::NoShow->label());
    }

    public function test_diopters_are_formatted_with_sign_and_typographic_minus(): void
    {
        $this->assertSame('+1.25', Refraction::diopters(1.25));
        $this->assertSame("\u{2212}0.50", Refraction::diopters(-0.5));
        $this->assertSame('0.00', Refraction::diopters(0.0));
        $this->assertSame('+4.00', Refraction::diopters(4));
        $this->assertSame("\u{2014}", Refraction::diopters(null));
        $this->assertSame("180\u{00B0}", Refraction::axis(180));
        $this->assertSame("\u{2014}", Refraction::axis(null));
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function dateTimes(): array
    {
        return [
            'with offset' => ['2026-11-03T14:30:00-03:00', true],
            'utc Z with millis (JS toISOString)' => ['2026-11-03T17:30:00.000Z', true],
            'without offset, no seconds' => ['2026-11-03T14:30', true],
            'compact offset' => ['2026-11-03T14:30:00+0000', true],
            'date only' => ['2026-11-03', false],
            'space separator' => ['2026-11-03 14:30', false],
            'relative' => ['tomorrow', false],
            'impossible date' => ['2026-02-30T10:00', false],
            'hour 24' => ['2026-11-03T24:00', false],
            'minute 60' => ['2026-11-03T10:60', false],
            'bad offset' => ['2026-11-03T10:00+25:00', false],
            'not a string' => [20261103, false],
        ];
    }

    #[DataProvider('dateTimes')]
    public function test_iso_date_time_validation(mixed $value, bool $valid): void
    {
        $this->assertSame($valid, LocalDateTime::isValid($value));
    }

    public function test_parse_normalizes_to_sao_paulo(): void
    {
        $this->assertSame('2026-11-03 14:30:00', LocalDateTime::parse('2026-11-03T17:30:00.000Z')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-03 14:30:00', LocalDateTime::parse('2026-11-03T14:30')->format('Y-m-d H:i:s'));
        $this->assertSame('America/Sao_Paulo', LocalDateTime::parse('2026-11-03T14:30+02:00')->getTimezone()->getName());
    }
}
