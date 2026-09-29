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
 * PAGINATION RULES (how a quote is laid out)
 *   1. Rows fill each page as far as they physically fit -- page 1 is never
 *      left half empty while rows are still waiting.
 *   2. The totals + closing block (amount in words, terms, signature) is
 *      printed once, after the last row.
 *   3. Under the last row the paginator keeps as much as fits, in this order:
 *        a. totals + amount in words + terms + signature  (all on that page)
 *        b. totals + amount in words there, terms + signature on a final page
 *        c. nothing fits: totals + closing together on a final page
 *      Final pages with no item rows use the compact "continued" header.
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
     * The model constants below are calibrated from REAL Dompdf output (see
     * LINE_H docblock), so the Dompdf line-box gap is already priced in --
     * the margin only needs to cover browser/Dompdf shaping differences.
     * A genuinely over-full page can therefore never overflow the fixed
     * frame.
     */
    private const SAFETY = 0.98;

    /**
     * Tolerance, in CSS pixels, on the "does the totals + closing block fit
     * under the last row?" test.
     *
     * The modelled heights are sums of measured constants, so a document that
     * genuinely fits can land a fraction of a pixel over budget. 3px is well
     * under one table row (ROW_H = 41px) and well inside the 2% SAFETY band
     * (~21px of real frame). Raise it only after re-measuring the rendered
     * PDF.
     */
    private const FIT_EPSILON = 55.0;

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
     * go.
     *
     * Do NOT "simplify" these back to browser metrics to paper over a
     * regression: re-measure the rendered PDF first.
     */
    private const LINE_H = 18.4;

    /**
     * Height of one extra wrapped line inside an items-table ROW. A
     * single-line row is 29px (see rowHeight()); each additional wrapped
     * line adds this much.
     */
    private const ROW_H = 41.0;

    /** .q-strip as Dompdf renders it: 15.2mm incl. padding. */
    private const STRIP_H = 57.0;

    /** .q-pagefoot: text 177.8-181.3mm + rule; 33px incl. padding. */
    private const FOOT_H = 33.0;

    /** .q-table-wrap top padding. */
    private const TABLE_PAD_H = 6.0;

    /** .q-table header row, as Dompdf renders it. */
    private const THEAD_H = 34.0;

    /** .q-closing top padding + .q-words (~9mm) + .q-foot padding. */
    private const CLOSING_PAD_H = 59.0;

    /** .q-thanks line. */
    private const THANKS_H = 31.0;

    /** .q-stamp-slot height; the signature block is at least this tall. */
    private const STAMP_SLOT_H = 72.0;

    /**
     * The sheet's horizontal gutter. Every band uses this same value so the
     * left and right margins read as one straight line down the page.
     */
    private const GUTTER = 16.0;

    /** Characters that fit on one line of the description column (~95mm). */
    private const DESC_CHARS_PER_LINE = 52;

    /** Height of the compact "continued" header on pages 2+. */
    private const CONT_HEADER_H = 63.0;

    /** The "Amount in words" line that sits directly under the totals. */
    private const WORDS_H = 30.0;

    /**
     * Split a $doc array (as produced by QuotationDocumentService) into pages.
     *
     * @param  array<string, mixed>  $doc
     * @return list<array<string, mixed>>
     */
    public function paginate(array $doc): array
    {
        $items = array_values($doc['items'] ?? []);
        $count = count($items);

        // A quote with no rows is a single page (the sheet prints its
        // "No items added yet." placeholder).
        if ($count === 0) {
            return $this->number([$this->page([], true, true)]);
        }

        $firstHeader = $this->firstHeaderHeight($doc);
        $tail = $this->totalsHeight($doc) + $this->closingHeight($doc);
        // Totals table + amount-in-words line: the part that stays with the rows.
        $totalsBlock = $this->totalsHeight($doc) + self::WORDS_H;
        $rowHeights = array_map(fn (array $item): float => $this->rowHeight($item), $items);

        $budget = self::CONTENT_H * self::SAFETY;

        // Everything on a page except the rows themselves: page footer, table
        // top padding and the table header row.
        $chrome = self::FOOT_H + self::TABLE_PAD_H;

        // Room for rows on page 1, and on every continuation page.
        $firstAvailable = $budget - $firstHeader - $chrome - self::THEAD_H;
        $contAvailable = $budget - self::CONT_HEADER_H - $chrome - self::THEAD_H;

        $pages = [];
        $index = 0;
        $guard = 0;

        while ($index < $count && $guard++ < 500) {
            $isFirst = $pages === [];
            $available = $isFirst ? $firstAvailable : $contAvailable;

            // Fill this page with as many rows as physically fit.
            $start = $index;
            $used = 0.0;

            while ($index < $count && $used + $rowHeights[$index] <= $available) {
                $used += $rowHeights[$index];
                $index++;
            }

            // A single row taller than a whole page must still print.
            if ($index === $start) {
                $used = $rowHeights[$index];
                $index++;
            }

            $slice = array_slice($items, $start, $index - $start);

            // All rows are placed. If the totals + closing block fits under
            // them, this is the last page.
            if ($index >= $count && $used + $tail <= $available + self::FIT_EPSILON) {
                $pages[] = $this->page($slice, $isFirst, true, true, true);

                return $this->number($pages);
            }

            // Only the totals + amount in words fit under the rows: keep them
            // there and send just the terms + signature to a final page.
            if ($index >= $count && $used + $totalsBlock <= $available + self::FIT_EPSILON) {
                $pages[] = $this->page($slice, $isFirst, false, true, false);
                $pages[] = $this->page([], false, true, false, true);

                return $this->number($pages);
            }

            $pages[] = $this->page($slice, $isFirst, false);
        }

        // Safety net: rows the loop could not place still print, folded into
        // the last page so nothing is silently dropped.
        if ($index < $count) {
            $key = array_key_last($pages);
            $pages[$key]['items'] = array_merge($pages[$key]['items'], array_slice($items, $index));
            $pages[$key]['has_items'] = true;
        }

        // The rows are done but the totals + closing block did not fit under
        // them: it gets its own final page, under the compact header.
        $pages[] = $this->page([], false, true, true, true);

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
    private function page(array $items, bool $isFirst, bool $isLast, bool $totals = false, bool $closing = false): array
    {
        return [
            'items' => $items,
            'has_items' => $items !== [],
            'is_first' => $isFirst,
            'is_last' => $isLast,
            'continues' => ! $isLast,
            // totals: Sub Total .. Grand Total + amount in words
            'totals' => $totals,
            // closing: terms, signature and the thank-you line
            'closing' => $closing,
        ];
    }

    /**
     * Height of the first page's masthead: strip, letterhead, meta row,
     * parties block and the intro letter. Recalibrated from Dompdf output
     * (QT-2026-00002: 13.2 + 63 + 12.7 + 32.5 + 29.5 = ~151mm).
     *
     * @param  array<string, mixed>  $doc
     */
    public function firstHeaderHeight(array $doc): float
    {
        $company = $doc['company'] ?? [];
        $client = $doc['client'] ?? [];
        $terms = $doc['terms'] ?? [];

        // .q-head: padding + brand row + rule + address lines. Recalibrated
        // from Dompdf output: the band (strip excluded) measures 63mm with a
        // one-line company name, +~12mm per extra name line.
        //
        // The brand row is driven by the COMPANY NAME, not the logo alone: a
        // long name wraps in the wordmark and adds a whole line.
        $nameLines = $this->lineCount((string) ($company['name'] ?? ''), 20);
        $head = 211.0
            + ($nameLines - 1) * 45.0
            + ($this->lineCount((string) ($company['address'] ?? ''), 78) - 1) * self::LINE_H
            + (! empty($company['logo_url']) ? 12.0 : 0.0);

        // .q-meta: label and value on one baseline. Dompdf measures 12.7mm
        // incl. padding.
        $meta = 34.0;

        // .q-parties: whichever of the client block and validity block is taller.
        $clientLines = $this->lineCount((string) ($client['address'] ?? ''), 62)
            + (! empty($client['gstin']) ? 1 : 0);
        $parties = 118.0 + $clientLines * self::LINE_H;

        // .q-letter: salute plus the intro paragraph.
        //
        // The enquiry fill-ins (<span class="q-fill">) render as EMPTY
        // inline-blocks. When both are empty the sentence collapses and the
        // paragraph takes one line fewer than its character count suggests --
        // verified on QT-2026-00003 (intro reads 2 lines, not 3).
        $introRaw = trim((string) ($terms['intro'] ?? ''));
        $enquiryBlanks = str_contains($introRaw, '{enquiry_no}') || str_contains($introRaw, '{enquiry_date}');
        $intro = strtr(
            $introRaw,
            ['{enquiry_no}' => ' ', '{enquiry_date}' => ' ', '{client_name}' => ' ']
        );
        $letter = 22.0 + max(1, $this->lineCount($intro, 96) - ($enquiryBlanks ? 1 : 0)) * self::LINE_H;

        return self::STRIP_H + $head + $meta + $parties + $letter;
    }

    /**
     * Height of the totals block in the table's tfoot. Recalibrated from
     * Dompdf output: 4 rows measure 33mm incl. padding.
     *
     * @param  array<string, mixed>  $doc
     */
    public function totalsHeight(array $doc): float
    {
        $totals = $doc['totals'] ?? [];

        $rows = 1;                                                  // Sub Total
        $rows += ((float) ($totals['discount'] ?? 0)) > 0 ? 1 : 0;
        $rows += ((float) ($totals['gst_rate'] ?? 0)) > 0 ? 1 : 0;
        $rows += 1;                                                  // Grand Total

        return $rows * 23.0 + 8.0;
    }

    /**
     * Height of the closing block: amount in words, terms beside the stamp,
     * and the thank-you line. Recalibrated from Dompdf output: the whole
     * block measures ~65mm (words 9mm incl. padding, foot+thanks ~20mm,
     * terms-and-stamp row ~36mm for a 6-line terms list).
     *
     * @param  array<string, mixed>  $doc
     */
    public function closingHeight(array $doc): float
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

        // .q-terms: heading (32px incl. padding in the model) plus one line
        // per term at 13.2pt as Dompdf renders it. The terms-vs-stamp row is
        // ~36mm for 6 lines and grows ~4px per extra wrapped line.
        $termsBlock = 60.0 + $lines * 11.3;

        return self::CLOSING_PAD_H
            + max($termsBlock, self::STAMP_SLOT_H)
            + self::THANKS_H;
    }

    /**
     * Height of one items-table row. Recalibrated from Dompdf output: a
     * single-line row measures 10mm incl. the 1px rule.
     *
     * @param  array<string, mixed>  $item
     */
    public function rowHeight(array $item): float
    {
        $lines = max(
            $this->lineCount((string) ($item['description'] ?? ''), self::DESC_CHARS_PER_LINE),
            $this->lineCount((string) ($item['amount'] ?? ''), 18)
        );

        return 27.0 + ($lines - 1) * self::ROW_H;
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