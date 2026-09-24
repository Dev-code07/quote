<?php

namespace App\Enums;

/**
 * Letterhead header alignment (decision #10, design.md 2.2).
 */
enum HeaderAlignment: string
{
    case Left = 'left';
    case Center = 'center';
    case Right = 'right';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * CSS text-align value for the letterhead block.
     */
    public function css(): string
    {
        return $this->value;
    }

    public static function default(): self
    {
        return self::Center;
    }
}
