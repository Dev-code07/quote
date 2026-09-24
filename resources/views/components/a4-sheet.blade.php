{{--
    A4 quotation sheet.

    ONE component for the template editor preview, the quote preview and the
    on-screen print view, so the three can never disagree (Architecture.md 5.4).
    The PDF uses the same structure via resources/views/pdf/quote.blade.php and
    the same stylesheet (resources/css/quotation.css).

    Markup mirrors docs/quote_preview.html, function firstHeader():

      q-strip   GSTIN (left) | document title (centre) | mobile (right)
      q-head    brand + company rule + address
      q-meta    reference number + date
      q-parties To / client ................ valid-until, phone, email
      q-letter  salutation + intro
      q-table   items + totals
      q-closing amount in words, terms, seal + signature, thank-you line
      q-pagefoot company name | page count

    Expected $doc shape:
      company : name, display_name, company_gstin, tagline, address, email,
                mobile_1, mobile_2, stamp_place, logo_url, signature_url,
                stamp_url, generated_seal, authorized_person, designation
      accent  : ink, ink2, soft, line
      align   : left|center|right
      doc_title
      client  : name, address, gstin, email, phone
      meta    : no, date, valid, enquiry_no, enquiry_date
      items   : [position, description, qty, rate, amount]
      totals  : subtotal, discount, gst_rate, gst_amount, grand_total, words
      terms   : intro, delivery, warranty, validity, extra[], notes

    Every interpolated value is escaped with {{ }}. The only raw output is
    nl2br(e(...)) on user text that legitimately contains line breaks.
--}}
@props(['doc', 'preview' => true])

@php
    $ink = $doc['accent'] ?? ['ink' => '#1f2f6b', 'ink2' => '#3b4a82', 'soft' => '#eef1fa', 'line' => '#c9d0e6'];
    $align = $doc['align'] ?? 'center';
    $company = $doc['company'] ?? [];
    $client = $doc['client'] ?? [];
    $meta = $doc['meta'] ?? [];
    $items = $doc['items'] ?? [];
    $totals = $doc['totals'] ?? [];
    $terms = $doc['terms'] ?? [];
    $extra = array_values(array_filter($terms['extra'] ?? []));

    $style = sprintf(
        '--ink:%s;--ink-2:%s;--ink-soft:%s;--ink-line:%s;',
        $ink['ink'], $ink['ink2'], $ink['soft'], $ink['line']
    );

    $money = fn ($v) => '₹'.number_format((float) $v, 2);
    $rate = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $sampleClient = $client['name'] ?: 'Govt. Senior Secondary School';

    /*
     * Intro tokens. Enquiry no./date are shown as blank dotted fill-ins, the
     * client name as a highlighted sample, exactly as the prototype does.
     */
    $intro = $terms['intro'] ?? 'While thanking you for your esteemed enquiry no. {enquiry_no} dated {enquiry_date}, we submit our lowest rates as under for favour of acceptance, subject to the terms and conditions given below.';
    $intro = strtr(e($intro), [
        '{enquiry_no}' => '<span class="q-fill"></span>',
        '{enquiry_date}' => '<span class="q-fill"></span>',
        '{client_name}' => '<span class="q-sample">'.e($sampleClient).'</span>',
    ]);

    /* Terms list, GST line first and emphasised (prototype li.key). */
    $keyTerm = ($totals['gst_rate'] ?? 0) > 0
        ? 'GST extra @ '.$rate($totals['gst_rate']).'% (included in Grand Total above)'
        : null;
    $otherTerms = array_values(array_filter([
        ! empty($terms['delivery']) ? 'Delivery period: '.$terms['delivery'] : null,
        ! empty($terms['warranty']) ? 'Warranty: '.$terms['warranty'] : null,
        ! empty($terms['validity']) ? 'Validity of this offer: '.$terms['validity'] : null,
        ...$extra,
        ! empty($terms['notes']) ? $terms['notes'] : null,
    ]));

    /* Seal: an uploaded stamp wins, otherwise a generated one when enabled. */
    $sealText = trim(($company['name'] ?? '').' '.($company['stamp_place'] ?? ''));
    $hasStampImage = ! empty($company['stamp_url']);
    $generatedSeal = ! $hasStampImage && ($company['generated_seal'] ?? false);
    $sealEmpty = ! $hasStampImage && ! $generatedSeal;
@endphp
@inject('paginator', 'App\Services\QuotationPaginator')

@php
    /*
     * One .q-sheet per A4 page. The screen preview, the browser print output
     * and the Dompdf PDF all render these same elements, so they cannot drift.
     * See App\Services\QuotationPaginator for the page-break model.
     */
    $pages = $paginator->paginate($doc);
    $docHasItems = $items !== [];
@endphp

@foreach ($pages as $page)
    @php($pageItems = $page['items'])

    <div class="q-page" data-q-page="{{ $page['n'] }}">
        @if ($preview)
            <div class="page-label">Page {{ $page['n'] }}</div>
        @endif

        <div class="q-sheet print-sheet" style="{{ $style }}" @if ($preview) data-preview-sheet @endif>
    {{-- A real <table>, not divs with display:table/table-row.
         Dompdf only lays out table rows that are genuine <tr> inside a genuine
         <table>; given a styled <div> it falls back to block formatting and
         every band drifts down the page, which pushed the closing block onto a
         second sheet. The bands inside are still divs, so the markup stays
         close to the prototype. --}}
    <table class="q-frame">
        <tr>
            <td class="q-flow">

            {{-- 1-5. First page only: strip, letterhead, ref band, parties, intro.
                 Continuation pages get the compact header below instead. --}}
            @if ($page['is_first'])
                {{-- 1. Top strip --}}
            <div class="q-strip">
                <div>GSTIN: <b>{{ $company['company_gstin'] ?: '—' }}</b></div>
                <div class="q-doc-title">{{ $doc['doc_title'] ?? 'QUOTATION' }}</div>
                <div class="q-mob">
                    Mob. <b>{{ $company['mobile_1'] ?: '—' }}</b>
                    @if (! empty($company['mobile_2']))
                        <br>{{ $company['mobile_2'] }}
                    @endif
                </div>
            </div>

            {{-- 2. Letterhead --}}
            <div class="q-head {{ $align }}">
                <div class="q-brand">
                    @if (! empty($company['logo_url']))
                        <img src="{{ $company['logo_url'] }}" alt="" class="q-logo" data-q-part="logo">
                    @endif
                    <div class="q-company">{{ $company['display_name'] ?: ($company['name'] ?? 'Company') }}</div>
                </div>

                <div class="q-company-rule">
                    @if (! empty($company['tagline']))
                        <div class="q-deals">Deals in: <span>{{ $company['tagline'] }}</span></div>
                    @endif
                </div>

                <div class="q-address">
                    {{ $company['address'] ?? '' }}
                    @if (! empty($company['email']))
                        &nbsp;|&nbsp; E-mail: {{ $company['email'] }}
                    @endif
                </div>
            </div>

            {{-- 3. Reference band --}}
            <div class="q-meta">
                <div>Ref. No. <b>{{ $meta['no'] ?? '—' }}</b></div>
                <div>Dated: <b>{{ $meta['date'] ?? '—' }}</b></div>
            </div>

            {{-- 4. Parties --}}
            <div class="q-parties">
                <div>
                    <div class="q-to-label">To</div>
                    <div class="q-to">
                        <div class="name">{{ $client['name'] ?? '—' }}</div>
                        @if (! empty($client['address']))
                            <p>{!! nl2br(e($client['address'])) !!}</p>
                        @endif
                        @if (! empty($client['gstin']))
                            <p>GSTIN: {{ $client['gstin'] }}</p>
                        @endif
                    </div>
                </div>
                <div class="q-side">
                    @if (! empty($client['phone']))
                        <div>Phone <b>{{ $client['phone'] }}</b></div>
                    @endif
                    @if (! empty($client['email']))
                        <div>Email <b>{{ $client['email'] }}</b></div>
                    @endif
                    <div>Valid until <b>{{ $meta['valid'] ?? '—' }}</b></div>
                </div>
            </div>

            {{-- 5. Intro --}}
                <div class="q-letter">
                    <div class="salute">Dear Sir/Madam,</div>
                    <p>{!! $intro !!}</p>
                </div>
            @else
                {{-- Continuation header, as in the prototype's contHeader(). --}}
                <div class="q-cont-head {{ $align }}">
                    <div class="n">{{ $company['display_name'] ?: ($company['name'] ?? 'Company') }}</div>
                    <div class="r">
                        Ref. No. <b>{{ $meta['no'] ?? '—' }}</b> &nbsp;&middot;&nbsp; continued
                    </div>
                </div>
            @endif

            {{-- 6. Items --}}
            <div class="q-table-wrap">
                {{-- Column widths as percentages of the sheet, not px.
                     The sheet is a fixed 210mm in both the preview and the PDF,
                     so a percentage lands on the same physical width in each,
                     while px would depend on the renderer's CSS pixel size. --}}
                <table class="q-table">
                    <colgroup>
                        <col style="width: 8%">
                        <col style="width: 45%">
                        <col style="width: 8%">
                        <col style="width: 17%">
                        <col style="width: 22%">
                    </colgroup>
                    <thead>
                        <tr>
                            <th class="c">Sr. No.</th>
                            <th>Item Description</th>
                            <th class="c">Qty.</th>
                            <th class="r">Rate (₹)</th>
                            <th class="r">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pageItems as $item)
                            <tr>
                                <td class="c">{{ str_pad((string) ($item['position'] ?? $loop->iteration), 2, '0', STR_PAD_LEFT) }}</td>
                                <td class="desc">{{ $item['description'] ?? '—' }}</td>
                                <td class="c">{{ $rate($item['qty'] ?? 0) }}</td>
                                <td class="r">{{ $money($item['rate'] ?? 0) }}</td>
                                <td class="r">{{ $money($item['amount'] ?? 0) }}</td>
                            </tr>
                        @empty
                            @if (! $docHasItems)
                                <tr>
                                    <td colspan="5" class="desc">No items added yet.</td>
                                </tr>
                            @endif
                        @endforelse

                        {{-- Stretched by JS so the ruled table reaches the page
                             foot, as in the prototype. Screen preview only. --}}
                        @if ($preview && ! $page['continues'])
                            <tr class="q-filler" data-q-fill><td></td><td></td><td></td><td></td><td></td></tr>
                        @endif
                    </tbody>
                    @if ($pageItems && $page['is_last'])
                        <tfoot>
                            <tr>
                                <td colspan="4" class="lbl">Sub Total</td>
                                <td class="r">{{ $money($totals['subtotal'] ?? 0) }}</td>
                            </tr>
                            @if (($totals['discount'] ?? 0) > 0)
                                <tr>
                                    <td colspan="4" class="lbl">Less: Discount</td>
                                    <td class="r">− {{ $money($totals['discount']) }}</td>
                                </tr>
                            @endif
                            @if (($totals['gst_rate'] ?? 0) > 0)
                                <tr>
                                    <td colspan="4" class="lbl">GST @ {{ $rate($totals['gst_rate']) }}%</td>
                                    <td class="r">{{ $money($totals['gst_amount'] ?? 0) }}</td>
                                </tr>
                            @endif
                            <tr class="grand">
                                <td colspan="4" class="lbl">Grand Total</td>
                                <td class="r">{{ $money($totals['grand_total'] ?? 0) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            {{-- 7. Closing, on the last page only --}}
            @if ($docHasItems && $page['is_last'])
                <div class="q-closing">
                    <div class="q-words">
                        Amount in words: <b>{{ $totals['words'] ?? '' }}</b>
                    </div>

                    <div class="q-foot">
                        <div class="q-terms">
                            <h4>Terms &amp; Conditions</h4>
                            <ol>
                                @if ($keyTerm)
                                    <li class="key">{{ $keyTerm }}</li>
                                @endif
                                @foreach ($otherTerms as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                                @if (! $keyTerm && ! $otherTerms)
                                    <li>—</li>
                                @endif
                            </ol>
                        </div>

                        <div class="q-sign">
                            <div class="for">For: <b>{{ $company['name'] ?? '' }}</b></div>

                            <div class="q-stamp-slot">
                                @if ($hasStampImage)
                                    <img src="{{ $company['stamp_url'] }}" alt="" class="q-stamp-img" data-q-part="stamp">
                                @elseif ($generatedSeal)
                                    <div class="q-stamp" data-q-part="generated-seal">
                                        <span class="q-stamp-ring">{{ $sealText ?: 'COMPANY' }}</span>
                                        <span class="q-stamp-role">AUTHORISED</span>
                                        <span class="q-stamp-rule"></span>
                                        <span class="q-stamp-role">SIGNATORY</span>
                                    </div>
                                @else
                                    <div class="q-stamp-empty">No stamp</div>
                                @endif

                                @if (! empty($company['signature_url']))
                                    <img src="{{ $company['signature_url'] }}" alt="" class="q-sig-img" data-q-part="signature">
                                @endif
                            </div>

                            <div class="auth">Auth. Signatory</div>

                            @if (! empty($company['authorized_person']) || ! empty($company['designation']))
                                <div class="person">
                                    @if (! empty($company['authorized_person']))
                                        <b>{{ $company['authorized_person'] }}</b>
                                    @endif
                                    {{ $company['designation'] ?? '' }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="q-thanks">Thank you for the opportunity to quote. This is a computer-generated quotation.</div>
                </div>
            @endif

            {{-- "Continued on page n", when the items run over --}}
            @if ($page['continues'])
                <div class="q-carry">Continued on page {{ $page['n'] + 1 }}</div>
            @endif

            {{-- 8. Page footer --}}
            <div class="q-pagefoot">
                <span>{{ $company['name'] ?? '' }}</span>
                <span>Page {{ $page['n'] }} of {{ $page['total'] }}</span>
            </div>
            </td>{{-- .q-flow --}}
        </tr>
    </table>{{-- .q-frame --}}
    </div>{{-- .q-sheet --}}
    </div>{{-- .q-page --}}
@endforeach