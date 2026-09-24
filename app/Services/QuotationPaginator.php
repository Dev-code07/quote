<?php

namespace App\Services;

/**
 * Splits a quotation document into A4 page sheets.
 *
 * WHY THIS EXISTS
 * The screen preview, the browser print output and the Dompdf PDF must be the
 * same document. The client prototype (docs/quote_preview.html) guarantees
 * that by building one `.sheet` element per page and letting the screen and
 * the print dialog render those same elements. Our x-a4-sheet component used
 * to emit a single sheet with a hard-coded "Page 1 of 1", so a long quote
 * grew past one page: the screen showed one long sheet, the browser split it
 * wherever it happened to fit, and Dompdf paginated on its own — three
 * different documents from one source.
 *
 * Paginating inside the shared component makes all three identical.
 *
 * MEASUREMENT
 * PHP has no layout engine, so heights are estimated from the metrics declared
 * in resources/css/quotation.css; each constant cites the rule it comes from.
 * A safety margin absorbs the small differences between browser text shaping
 * and Dompdf's, so a page never overflows.
 */
class QuotationPaginator
{
    /** Usable height inside .q-frame: 297mm - 2x9mm padding - frame margin and border. */
    private const CONTENT_H = 1040.0;

    /** 5% of the page held back, guarding against font-metric drift. */
    private const SAFETY = 0.95;

    /**
     * Height of one line of text, in CSS pixels, as Dompdf lays it out.
     *
     * CALIBRATED AGAINST DOMPDF, NOT THE BROWSER.
     * Dompdf builds a line box from the font's own vertical metrics and only
     * honours `line-height` loosely, so for Hind it never goes below roughly
     * 30px at the sheet's type sizes. Measured on QT-2026-00006:
     *
     *   band                     browser      Dompdf
     *   .q-strip                   14.6mm      21.5mm
     *   .q-head                    31.8mm      40.7mm
     *   .q-meta                     9.2mm      13.2mm
     *   .q-parties + .q-letter     38.8mm      56.9mm
     *   .q-table                   71.3mm     107.0mm
     *   .q-closing                 69.6mm      69.1mm   (matches)
     *   .q-pagefoot                 7.7mm       7.7mm   (matches)
     *
     * The PDF is the binding constraint: it is the renderer that can overflow
     * an A4 page, so it decides where the breaks go. Sizing the paginator for
     * the browser instead produced a one-page preview and a two-page PDF.
     * Every constant below is therefore measured from real Dompdf output.
     */
    private const LINE_H = 30.0;

    /** .q-strip as Dompdf renders it: three cells, the tallest driving the row. */
    private const STRIP_H = 81.0;

    /** .q-pagefoot: 6px + 7px padding + 10.5px line + 1px border, 16px gutter. */
    private const FOOT_H = 30.0;

    /** .q-table-wrap top padding. */
    private const TABLE_PAD_H = 8.0;

    /** .q-table th: 6px + 6px padding + one Dompdf line + 1px border. */
    private const THEAD_H = 44.0;

    /** .q-closing top padding. */
    private const CLOSING_PAD_H = 7.0;

    /** .q-thanks, as Dompdf renders it. */
    private const THANKS_H = 34.0;

    /** .q-stamp-slot height; the signature block is at least this tall. */
    private const STAMP_SLOT_H = 100.0;

    /**
     * The sheet's horizontal gutter. Every band uses this same value so the
     * left and right margins read as one straight line down the page; the page
     * footer (14px) and the amount-in-words line (12px) used to differ, which
     * made the two edges visibly ragged.
     */
    private const GUTTER = 16.0;

    /** Characters that fit on one line of the description column (~95mm). */
    private const DESC_CHARS_PER_LINE = 52;

    /**
     * Split a $doc array (as produced by QuotationDocumentService) into pages.
     *
     * @param  array<string, mixed>  $doc
     * @return list<array<string, mixed>>
     */
    public function paginate(array $doc): array
    {
        $items = array_values($doc['items'] ?? []);

        $firstHeader = $this->firstHeaderHeight($doc);
        $contHeader = 63.0;
        $closing = $this->closingHeight($doc);
        $totals = $this->totalsHeight($doc);

        $rowHeights = array_map(fn (array $item): float => $this->rowHeight($item), $items);

        $pages = [];
        $index = 0;
        $count = count($items);
        $guard = 0;

        while ($index < $count && $guard++ < 500) {
            $isFirst = $pages === [];
            $fixed = ($isFirst ? $firstHeader : $contHeader)
                + self::FOOT_H + self::TABLE_PAD_H + self::THEAD_H;

            $budget = self::CONTENT_H * self::SAFETY;
            $remaining = array_sum(array_slice($rowHeights, $index));

            // Everything left, plus totals and the closing block, on this page?
            if ($remaining <= $budget - $fixed - $totals - $closing) {
                $pages[] = $this->page(array_slice($items, $index), $isFirst, true);
                $index = $count;

                break;
            }

            // Otherwise fill this page with as many rows as will fit.
            $available = $budget - $fixed;
            $used = 0.0;
            $placed = 0;

            while ($index < $count && $used + $rowHeights[$index] <= $available) {
                $used += $rowHeights[$index];
                $index++;
                $placed++;
            }

            // A single oversized row still has to go somewhere.
            if ($placed === 0) {
                $index++;
                $placed = 1;
            }

            // If that consumed the rest but leaves no room for the closing
            // block, carry rows over so the last page is never bare.
            //
            // `placed > 1` is load-bearing: a quotation whose closing block
            // cannot share a page with its items would otherwise carry every
            // row off, emitting a completely blank first page.
            if ($index >= $count && $placed > 1) {
                do {
                    $index--;
                    $placed--;
                } while (
                    $placed > 1
                    && $rowHeights[$index] > $budget - $fixed - $totals - $closing
                );
            }

            $pages[] = $this->page(array_slice($items, $index - $placed, $placed), $isFirst, false);
        }

        // No items at all, or rows left over: close the document out.
        if ($pages === [] || $index < $count) {
            $pages[] = $this->page([], $pages === [], true);
        }

        return $this->number($pages);
    }

    /**
     * Attach the page numbers the template renders.
     *
     * @param  list<array<string, mixed>>  $pages
     * @return list<array<string, mixed>>
     */
    private function number(array $pages): array
    {
        $total = count($pages);

        foreach ($pages as $i => $page) {
            $pages[$i]['n'] = $i + 1;
            $pages[$i]['total'] = $total;
            $pages[$i]['is_first'] = $i === 0;
            $pages[$i]['is_last'] = $i === $total - 1;
            $pages[$i]['continues'] = $i < $total - 1;
        }

        return $pages;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function page(array $items, bool $isFirst, bool $isLast): array
    {
        return [
            'items' => $items,
            'has_items' => $items !== [],
            'is_first' => $isFirst,
            'is_last' => $isLast,
            'continues' => ! $isLast,
        ];
    }

    /**
     * Height of the first page's masthead: strip, letterhead, meta row,
     * parties block and the intro letter.
     *
     * @param  array<string, mixed>  $doc
     */
    private function firstHeaderHeight(array $doc): float
    {
        $company = $doc['company'] ?? [];
        $client = $doc['client'] ?? [];
        $terms = $doc['terms'] ?? [];

        // .q-head: 25px padding + brand row (52px logo, else the 40px wordmark)
        // + rule + address lines. Dompdf line boxes, see self::LINE_H.
        $brand = ! empty($company['logo_url']) ? 52.0 : 40.0;
        $head = 25.0 + $brand + 19.0 + $this->lineCount((string) ($company['address'] ?? ''), 78) * self::LINE_H;

        // .q-meta: label and value on one baseline.
        $meta = 44.0;

        // .q-parties: whichever of the client block and validity block is taller.
        $clientBlock = 18.0 + $this->lineCount((string) ($client['address'] ?? ''), 62) * self::LINE_H + 44.0;
        $parties = 24.0 + max($clientBlock, 74.0);

        // .q-letter: salute plus the intro paragraph.
        $intro = strtr(
            trim((string) ($terms['intro'] ?? '')),
            ['{enquiry_no}' => ' ', '{enquiry_date}' => ' ', '{client_name}' => ' ']
        );
        $letter = 16.0 + 18.0 + $this->lineCount($intro, 96) * self::LINE_H;

        return self::STRIP_H + $head + $meta + $parties + $letter;
    }

    /**
     * Height of the totals block in the table's tfoot.
     *
     * @param  array<string, mixed>  $doc
     */
    private function totalsHeight(array $doc): float
    {
        $totals = $doc['totals'] ?? [];

        $rows = 1;                                                  // Sub Total
        $rows += ((float) ($totals['discount'] ?? 0)) > 0 ? 1 : 0;
        $rows += ((float) ($totals['gst_rate'] ?? 0)) > 0 ? 1 : 0;
        $rows += 1;                                                  // Grand Total

        // Regular rows are 5px + 5px padding + a Dompdf line; the Grand Total
        // row is 6px + 6px padding.
        return ($rows - 1) * (self::LINE_H + 11.0) + self::LINE_H + 13.0 + 2.0;
    }

    /**
     * Height of the closing block: amount in words, terms beside the stamp,
     * and the thank-you line.
     *
     * @param  array<string, mixed>  $doc
     */
    private function closingHeight(array $doc): float
    {
        $terms = $doc['terms'] ?? [];
        $totals = $doc['totals'] ?? [];

        $lines = ((float) ($totals['gst_rate'] ?? 0)) > 0 ? 1 : 0;

        foreach (['delivery', 'warranty', 'validity'] as $key) {
            if (! empty($terms[$key])) {
                $lines++;
            }
        }

        $lines += count($terms['extra'] ?? []);

        // .q-terms: heading plus one line per term. Dompdf line box.
        $termsBlock = 23.0 + $lines * self::LINE_H;

        return self::CLOSING_PAD_H
            + 17.0                                                 // .q-words
            + 24.0                                                 // .q-foot padding
            + max($termsBlock, self::STAMP_SLOT_H)
            + self::THANKS_H;
    }

    /**
     * Height of one items-table row, allowing the description to wrap.
     *
     * @param  array<string, mixed>  $item
     */
    private function rowHeight(array $item): float
    {
        $lines = max(
            $this->lineCount((string) ($item['description'] ?? ''), self::DESC_CHARS_PER_LINE),
            $this->lineCount((string) ($item['amount'] ?? ''), 18)
        );

        // .q-table td: 6px + 6px padding + a Dompdf line + 1px rule.
        return 14.0 + $lines * self::LINE_H;
    }

    /**
     * How many lines a string occupies in a column of the given character
     * width. A word longer than the column wraps on its own rather than being
     * truncated.
     */
    private function lineCount(string $text, int $charsPerLine): int
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if ($text === '') {
            return 1;
        }

        $lines = 1;
        $current = 0;

        foreach (explode(' ', $text) as $word) {
            $length = mb_strlen($word);

            if ($length > $charsPerLine) {
                if ($current > 0) {
                    $lines++;
                }

                $lines += (int) ceil($length / $charsPerLine) - 1;
                $current = $length % $charsPerLine ?: $charsPerLine;

                continue;
            }

            if ($current === 0) {
                $current = $length;
            } elseif ($current + 1 + $length <= $charsPerLine) {
                $current += 1 + $length;
            } else {
                $lines++;
                $current = $length;
            }
        }

        return $lines;
    }
}
