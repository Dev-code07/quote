<?php

namespace App\Enums;

/**
 * Quote lifecycle (PRD FR-09).
 *
 * Draft, Sent and Approved are set by the admin. Expired is ONLY ever set by
 * the quotes:expire command (BR-05), and Approved quotes are never auto-expired
 * (assumption A6).
 */
enum QuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Approved = 'approved';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Approved => 'Approved',
            self::Expired => 'Expired',
        };
    }

    /**
     * Badge tone used by the x-badge component.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Sent => 'accent',
            self::Approved => 'success',
            self::Expired => 'warning',
        };
    }

    /**
     * Statuses the admin may choose from (Expired is excluded, BR-05).
     *
     * @return array<string, string>
     */
    public static function selectableOptions(): array
    {
        $options = [];

        foreach ([self::Draft, self::Sent, self::Approved] as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Whether a transition is allowed.
     *
     * The admin may move between Draft, Sent and Approved freely (including
     * reverting), but can never set or clear Expired manually.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($target === self::Expired) {
            return false;
        }

        return $this !== $target;
    }
}
