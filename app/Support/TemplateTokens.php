<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Placeholder replacement for template intro messages (decision #9).
 *
 * Supported tokens: {client_name}, {enquiry_no}, {enquiry_date}.
 * Unresolved or absent values render as an empty string (assumption A7).
 */
class TemplateTokens
{
    public static function render(
        ?string $text,
        ?string $clientName,
        ?string $enquiryNo,
        ?CarbonInterface $enquiryDate
    ): string {
        $text ??= 'While thanking you for your esteemed enquiry, we submit our lowest rates as under for favour of acceptance, subject to the terms and conditions given below.';

        return strtr($text, [
            '{client_name}' => (string) $clientName,
            '{enquiry_no}' => (string) $enquiryNo,
            '{enquiry_date}' => $enquiryDate?->format('d M Y') ?? '',
        ]);
    }

    /**
     * Split a multi-line terms textarea into clean lines.
     *
     * @return array<int, string>
     */
    public static function lines(?string $text): array
    {
        if (blank($text)) {
            return [];
        }

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn (string $line) => $line !== ''));
    }
}
