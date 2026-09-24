<?php

namespace App\Enums;

/**
 * The six quotation accent palettes from docs/design.md section 1.2.
 *
 * Each palette maps to the four CSS custom properties the A4 document uses, so
 * a template only ever stores the palette key (Architecture.md 5.4).
 */
enum AccentPalette: string
{
    case Navy = 'navy';
    case Teal = 'teal';
    case Maroon = 'maroon';
    case Forest = 'forest';
    case Slate = 'slate';
    case Plum = 'plum';

    public function label(): string
    {
        return match ($this) {
            self::Navy => 'Navy',
            self::Teal => 'Teal',
            self::Maroon => 'Maroon',
            self::Forest => 'Forest',
            self::Slate => 'Slate',
            self::Plum => 'Plum',
        };
    }

    /**
     * ink, ink-2, soft background, hairline colour.
     *
     * @return array{ink: string, ink2: string, soft: string, line: string}
     */
    public function colours(): array
    {
        return match ($this) {
            self::Navy => ['ink' => '#1f2f6b', 'ink2' => '#3b4a82', 'soft' => '#eef1fa', 'line' => '#c9d0e6'],
            self::Teal => ['ink' => '#134e6f', 'ink2' => '#3a6b88', 'soft' => '#ebf3f8', 'line' => '#c3d8e5'],
            self::Maroon => ['ink' => '#6e1f2a', 'ink2' => '#8a3b45', 'soft' => '#f8eef0', 'line' => '#e3c8cc'],
            self::Forest => ['ink' => '#1d5445', 'ink2' => '#3c6d5f', 'soft' => '#ecf4f1', 'line' => '#c3dad2'],
            self::Slate => ['ink' => '#2e3a4e', 'ink2' => '#4d5a70', 'soft' => '#eff1f5', 'line' => '#cfd5df'],
            self::Plum => ['ink' => '#4b2f7a', 'ink2' => '#65508f', 'soft' => '#f2eef9', 'line' => '#d6cbe8'],
        };
    }

    /**
     * Palette options for a select or swatch picker.
     *
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

    public static function default(): self
    {
        return self::Navy;
    }
}
