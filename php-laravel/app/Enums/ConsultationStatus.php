<?php

namespace App\Enums;

/**
 * Consultation lifecycle. The transition table lives here only: requests, the seeder and tests ask
 * canTransitionTo() instead of repeating the rules.
 *
 *   scheduled -> confirmed, completed, no_show, cancelled
 *   confirmed -> completed, no_show, cancelled
 *   completed, no_show, cancelled -> (final)
 */
enum ConsultationStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    /**
     * Statuses this one may move to (empty for final statuses).
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Scheduled => [self::Confirmed, self::Completed, self::NoShow, self::Cancelled],
            self::Confirmed => [self::Completed, self::NoShow, self::Cancelled],
            self::Completed, self::NoShow, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * Final statuses can no longer change, and only `notes` may still be edited.
     */
    public function isFinal(): bool
    {
        return $this->allowedTransitions() === [];
    }

    /**
     * Whether a consultation in this status occupies the professional's time slot (overlap check).
     */
    public function blocksSchedule(): bool
    {
        return ! in_array($this, [self::Cancelled, self::NoShow], true);
    }

    /**
     * pt-BR label used in messages and in the prescription.
     */
    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'agendado',
            self::Confirmed => 'confirmado',
            self::Completed => 'realizado',
            self::NoShow => 'não compareceu',
            self::Cancelled => 'cancelado',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }

    /**
     * Values of the statuses that occupy the professional's time slot.
     *
     * @return array<int, string>
     */
    public static function blockingValues(): array
    {
        return array_values(array_map(
            fn (self $status) => $status->value,
            array_filter(self::cases(), fn (self $status) => $status->blocksSchedule())
        ));
    }
}
