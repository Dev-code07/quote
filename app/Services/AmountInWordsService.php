<?php

namespace App\Services;

/**
 * Converts a rupee amount into words using the Indian numbering system
 * (lakh / crore), as required by PRD FR-07 and the A4 document.
 *
 * Implemented in-app rather than pulled from a package so the wording stays
 * under our control and matches the client's expectations.
 */
class AmountInWordsService
{
    private const ONES = [
        0 => '', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five',
        6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
        11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
        15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen',
        19 => 'Nineteen',
    ];

    private const TENS = [
        2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
        6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
    ];

    /**
     * Convert to words, e.g. 123456.78 => "One Lakh Twenty Three Thousand
     * Four Hundred Fifty Six Rupees and Seventy Eight Paise Only".
     */
    public function convert(float|int|string $amount): string
    {
        $amount = round((float) $amount, 2);

        if ($amount < 0) {
            return 'Minus '.$this->convert(abs($amount));
        }

        $rupees = (int) floor($amount);
        $paise = (int) round(($amount - $rupees) * 100);

        // Guard against a rounding artefact producing 100 paise.
        if ($paise === 100) {
            $rupees++;
            $paise = 0;
        }

        if ($rupees === 0 && $paise === 0) {
            return 'Zero Rupees Only';
        }

        $words = '';

        if ($rupees > 0) {
            $words = $this->rupeesToWords($rupees)
                .' '.($rupees === 1 ? 'Rupee' : 'Rupees');
        }

        if ($paise > 0) {
            $words = ($words !== '' ? $words.' and ' : '')
                .$this->underHundred($paise).' Paise';
        }

        return $words.' Only';
    }

    /**
     * Indian grouping: crore, lakh, thousand, hundred.
     */
    private function rupeesToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $parts = [];

        $crores = intdiv($number, 10000000);
        $number %= 10000000;
        if ($crores > 0) {
            $parts[] = $this->underThousand($crores).' Crore';
        }

        $lakhs = intdiv($number, 100000);
        $number %= 100000;
        if ($lakhs > 0) {
            $parts[] = $this->underThousand($lakhs).' Lakh';
        }

        $thousands = intdiv($number, 1000);
        $number %= 1000;
        if ($thousands > 0) {
            $parts[] = $this->underThousand($thousands).' Thousand';
        }

        if ($number > 0) {
            $parts[] = $this->underThousand($number);
        }

        return implode(' ', $parts);
    }

    /**
     * Words for a number below 1000 (e.g. 999 => "Nine Hundred Ninety Nine").
     */
    private function underThousand(int $number): string
    {
        if ($number < 100) {
            return $this->underHundred($number);
        }

        $hundreds = intdiv($number, 100);
        $remainder = $number % 100;

        return self::ONES[$hundreds].' Hundred'
            .($remainder > 0 ? ' '.$this->underHundred($remainder) : '');
    }

    /**
     * Words for a number below 100 (e.g. 42 => "Forty Two").
     */
    private function underHundred(int $number): string
    {
        if ($number < 20) {
            return self::ONES[$number];
        }

        $tens = (int) floor($number / 10);
        $ones = $number % 10;

        return self::TENS[$tens].($ones > 0 ? ' '.self::ONES[$ones] : '');
    }
}
