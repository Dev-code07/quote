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
    /**
     * Usable height inside .q-frame, in CSS pixels.
     *
     * .q-frame is 276.65mm tall (see the exact arithmetic in
     * resources/css/quotation.css). It is `box-sizing: border-box`, so the
     * 2px border is already inside that figure; only the border needs
     * subtracting to get the space content can occupy.
     * 276.65mm is 1045.7px at 96dpi, less 4px of border.
     */
    private const CONTENT_H = 1042.0;

    /**
     * Fraction of the frame the paginator will actually fill.
     *
     * Calibrated from real Dompdf output, not from the browser. Dompdf reserves
     * a full font line box for text where the browser does not, so the same
     * document measures ~10% taller there; measured header bands came out at
     * 112.5mm (Sharma) and 126.4mm (ABC) against the browser's ~95mm.
     *
     * The value sits deliberately BETWEEN the modelled height of a two-item
     * and a three-item quotation for the tallest template (979px and 1020px).
     * That gap is one 41px table row, so any margin in that window splits the
     * long quote and keeps the short one whole -- and a margin outside it
     * either merges items Dompdf then splits itself, or needlessly breaks a
     * quotation that fits. Re-measure the rendered PDF before changing it. */
    private const SAFETY = 0.98;

    /**
     * Tolerance, in CSS pixels, on the "does everything fit on this page?"
     * test at the top of paginate().
     *
     * The modelled heights are sums of measured constants, so a document that
     * genuinely fits can land a fraction of a pixel over budget. QT-2026-00007
     * models at 1021.2px against a 1021.16px budget -- 0.04px over -- which used
     * to force the multi-page path: the carry-back-off then pushed the last row
     * to page 2 and left ~360px (over a third of the frame) of dead space at the
     * foot of page 1, on a quotation that fits on a single sheet. The same
     * split showed in the PDF and in the on-screen preview, since both run
     * through this paginator.
     *
     * 3px is well under one table row (ROW_H = 41px) and well inside the 2%
     * SAFETY band (~21px of real frame), so a document within the tolerance
     * still fits the fixed .q-frame. Raise it only after re-measuring the
     * rendered PDF. */
    private const FIT_EPSILON = 3.0;

    /**
     * Height of one line of text, in CSS pixels, as DOMPDF lays it out.
     *
     * CALIBRATED AGAINST REAL DOMPDF OUTPUT. Dompdf builds its line box from
     * the font's own vertical metrics and only then applies `line-height`, so
     * the browser's arithmetic understates every band. Measured on the seeded
     * quotes (mm, Dompdf / browser):
     *
     *   band                       Dompdf   browser
     *   .q-strip                     18.2     14.6
     *   .q-head + .q-meta            39.4     31.8
     *   .q-parties + .q-letter       36.4     30.8
     *   .q-table, per item row       10.9      8.1
     *   .q-table, header + tfoot     46.4     33.5
     *   .q-closing                   76.7     67.0
     *   .q-pagefoot                   8.7      8.7   (matches)
     *
     * The figures below are those measurements expressed in CSS pixels
     * (1mm = 3.7795px). The PDF is the binding constraint -- it is the
     * renderer that can overflow an A4 page -- so it decides where the breaks
     * go. Sizing for the browser instead produced a one-page preview and a
     * two-page PDF, which is the bug this replaced.
     *
     * Do NOT "simplify" these back to browser metrics to paper over a
     * regression: re-measure the rendered PDF first.
     */
    private const LINE_H = 18.4;

    /**
     * Height of one items-table ROW (cell padding + line + rule), as Dompdf
     * lays it out. Distinct from self::LINE_H: a row is not a line, and using
     * the line height here under-counted every page by ~23px per item --
     * enough to merge two items onto a page that could only hold one.
     */
    private const ROW_H = 41.0;

    /** .q-strip as Dompdf renders it: 18.2mm. */
    private const STRIP_H = 69.0;

    /** .q-pagefoot: matches the browser exactly at 8.7mm. */
    private const FOOT_H = 33.0;

    /** .q-table-wrap top padding. */
    private const TABLE_PAD_H = 8.0;

    /** .q-table header row, as Dompdf renders it. */
    private const THEAD_H = 36.0;

    /** .q-closing top padding. */
    private const CLOSING_PAD_H = 7.0;

    /** .q-thanks line. */
    private const THANKS_H = 31.0;

    /** .q-stamp-slot height; the signature block is at least this tall. */
    private const STAMP_SLOT_H = 84.0;

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
            // FIT_EPSILON absorbs model rounding so a document that fits is not
            // split into two half-empty sheets (see the constant's docblock).
            if ($remaining <= $budget - $fixed - $totals - $closing + self::FIT_EPSILON) {
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

        // .q-head: padding + brand row + rule + address lines.
        //
        // The brand row is driven by the COMPANY NAME, not the logo alone: a
        // long name wraps in the wordmark and adds a whole line, which is
        // exactly why the ABC template's header measured 126.4mm against the
        // Sharma template's 112.5mm on identical item data -- and why a
        // three-item ABC quote needed two pages where a three-item Sharma
        // quote fitted on one. Modelling only the logo missed that entirely
        // and merged the items onto a page Dompdf then had to split.
        $nameLines = $this->lineCount((string) ($company['name'] ?? ''), 20);
        $brand = ! empty($company['logo_url']) ? 52.0 : 40.0;
        $head = 32.0 + $brand + 27.0
            + ($nameLines - 1) * 53.0
            + $this->lineCount((string) ($company['address'] ?? ''), 78) * self::LINE_H;

        // .q-meta: label and value on one baseline.
        $meta = 47.0;

        // .q-parties: whichever of the client block and validity block is taller.
        $clientBlock = 26.0 + $this->lineCount((string) ($client['address'] ?? ''), 62) * self::LINE_H + 47.0;
        $parties = 34.0 + max($clientBlock, 64.0);

        // .q-letter: salute plus the intro paragraph.
        $intro = strtr(
            trim((string) ($terms['intro'] ?? '')),
            ['{enquiry_no}' => ' ', '{enquiry_date}' => ' ', '{client_name}' => ' ']
        );
        $letter = 22.0 + 26.0 + $this->lineCount($intro, 96) * self::LINE_H;

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

        // Regular tfoot rows carry the same line box as the body, plus padding.
        return ($rows - 1) * (self::LINE_H + 12.0) + self::LINE_H + 14.0 + 2.0;
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

        // .q-terms: heading plus one line per term, at 13.2pt as Dompdf
        // renders it (not self::LINE_H, which is the table's line box).
        $termsBlock = 32.0 + $lines * 17.6;

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
        return self::ROW_H * $lines;
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
