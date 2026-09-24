{{--
    Dedicated A4 PDF template (PRD FR-11, Architecture.md 5.4).

    Rendered by Dompdf. Rules:
      - No JavaScript, no external requests (isRemoteEnabled is off).
      - Fonts are self-hosted in public/fonts (assumption A5).
      - Accent colours arrive as CSS custom properties from the snapshot.
      - The stylesheet is injected by PdfService from resources/css/quotation.css
        so the PDF and the on-screen preview can never diverge.
--}}
@php
    $ink = $doc['accent'] ?? ['ink' => '#1f2f6b', 'ink2' => '#3b4a82', 'soft' => '#eef1fa', 'line' => '#c9d0e6'];
    $company = $doc['company'] ?? [];
    $client = $doc['client'] ?? [];
    $meta = $doc['meta'] ?? [];
    $items = $doc['items'] ?? [];
    $totals = $doc['totals'] ?? [];
    $terms = $doc['terms'] ?? [];
    $extra = array_values(array_filter($terms['extra'] ?? []));

    $accentVars = sprintf(
        '--ink:%s;--ink-2:%s;--ink-soft:%s;--ink-line:%s;',
        $ink['ink'], $ink['ink2'], $ink['soft'], $ink['line']
    );

    $money = fn ($v) => '₹'.number_format((float) $v, 2);
    $align = $doc['align'] ?? 'center';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $meta['no'] ?? 'Quotation' }}</title>

    <style>
        @page { size: A4; margin: 9mm; }

        /* Self-hosted fonts: Dompdf cannot reach a CDN at render time. */
        @font-face { font-family: 'Hind'; font-style: normal; font-weight: 400; src: url('{{ public_path('fonts/Hind-Regular.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Hind'; font-style: normal; font-weight: 600; src: url('{{ public_path('fonts/Hind-SemiBold.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Hind'; font-style: normal; font-weight: 700; src: url('{{ public_path('fonts/Hind-Bold.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Zilla Slab'; font-style: normal; font-weight: 700; src: url('{{ public_path('fonts/ZillaSlab-Bold.ttf') }}') format('truetype'); }

        /* Injected shared A4 stylesheet (single source of truth). */
        {!! $styles !!}

        body { margin: 0; }
        .q-sheet { box-shadow: none; margin: 0; width: 100%; min-height: 0; padding: 0; }
        .q-frame { height: auto; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
<div class="q-sheet" style="{{ $accentVars }}">
    <div class="q-frame">
        <div class="q-flow">

            <div class="q-strip">
                <div>
                    <b>Ph.</b> {{ $company['mobile_1'] ?? '' }}
                    @if (! empty($company['email'])) &nbsp;|&nbsp; <b>E-mail:</b> {{ $company['email'] }} @endif
                </div>
                <div class="q-doc-title">{{ $doc['doc_title'] ?? 'QUOTATION' }}</div>
                <div style="text-align: right"><b>Date:</b> {{ $meta['date'] ?? '—' }}</div>
            </div>

            <div class="q-head {{ $align }}">
                @if (! empty($company['logo_url']))
                    <div style="text-align: {{ $align }}"><img src="{{ $company['logo_url'] }}" alt="" style="width:52px;height:52px;object-fit:contain"></div>
                @endif

                <div class="q-company">{{ $company['display_name'] ?? ($company['name'] ?? '') }}</div>
                <div class="q-company-rule"></div>

                @if (! empty($company['tagline']))
                    <div class="q-deals">Deals in: <span>{{ $company['tagline'] }}</span></div>
                @endif

                @if (! empty($company['address']) || ! empty($company['company_gstin']))
                    <div class="q-address">
                        {{ $company['address'] ?? '' }}
                        @if (! empty($company['company_gstin'])) &nbsp;|&nbsp; GSTIN: {{ $company['company_gstin'] }} @endif
                    </div>
                @endif
            </div>

            <div class="q-meta">
                <div>Ref. No. <b>{{ $meta['no'] ?? '—' }}</b></div>
                <div>Dated: <b>{{ $meta['date'] ?? '—' }}</b></div>
            </div>

            <div class="q-parties">
                <div class="q-to">
                    <div class="q-to-label">To</div>
                    <div class="name">{{ $client['name'] ?? '—' }}</div>
                    @if (! empty($client['address'])) <p>{!! nl2br(e($client['address'])) !!}</p> @endif
                    @if (! empty($client['gstin'])) <p>GSTIN: {{ $client['gstin'] }}</p> @endif
                </div>
                <div class="q-side">
                    @if (! empty($client['phone'])) <div>Phone <b>{{ $client['phone'] }}</b></div> @endif
                    @if (! empty($client['email'])) <div>Email <b>{{ $client['email'] }}</b></div> @endif
                    <div>Valid until <b>{{ $meta['valid'] ?? '—' }}</b></div>
                </div>
            </div>

            <div class="q-letter">
                <div class="salute">Dear Sir/Madam,</div>
                <p>{!! nl2br(e($terms['intro'] ?? '')) !!}</p>
            </div>

            <table class="q-table">
                <thead>
                    <tr>
                        <th class="c">Sr. No.</th>
                        <th class="desc">Item Description</th>
                        <th class="c">Qty.</th>
                        <th class="r">Rate (₹)</th>
                        <th class="r">Amount (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td class="c">{{ str_pad((string) $item['position'], 2, '0', STR_PAD_LEFT) }}</td>
                            <td class="desc">{{ $item['description'] }}</td>
                            <td class="c">{{ rtrim(rtrim(number_format((float) $item['qty'], 2), '0'), '.') }}</td>
                            <td class="r">{{ $money($item['rate']) }}</td>
                            <td class="r">{{ $money($item['amount']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
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
                            <td colspan="4" class="lbl">GST @ {{ rtrim(rtrim(number_format((float) $totals['gst_rate'], 2), '0'), '.') }}%</td>
                            <td class="r">{{ $money($totals['gst_amount'] ?? 0) }}</td>
                        </tr>
                    @endif
                    <tr class="grand">
                        <td colspan="4" class="lbl">Grand Total</td>
                        <td class="r">{{ $money($totals['grand_total'] ?? 0) }}</td>
                    </tr>
                </tfoot>
            </table>

            <div class="q-closing">
                <div class="q-words">Amount in words: <b>{{ $totals['words'] ?? '' }}</b></div>

                @php
                    $termLines = [];
                    if (($totals['gst_rate'] ?? 0) > 0) {
                        $termLines[] = 'GST extra @ '.$totals['gst_rate'].'% (included in Grand Total above)';
                    }
                    if (! empty($terms['delivery'])) { $termLines[] = 'Delivery period: '.$terms['delivery']; }
                    if (! empty($terms['warranty'])) { $termLines[] = 'Warranty: '.$terms['warranty']; }
                    if (! empty($terms['validity'])) { $termLines[] = 'Validity of this offer: '.$terms['validity']; }
                    foreach ($extra as $line) { $termLines[] = $line; }
                    if (! empty($terms['notes'])) { $termLines[] = $terms['notes']; }
                @endphp

                @if ($termLines)
                    <ul class="q-terms">
                        @foreach ($termLines as $line)<li>{{ $line }}</li>@endforeach
                    </ul>
                @endif

                <div class="q-sign">
                    @if (! empty($company['stamp_place']))
                        <div class="q-stamp">{{ $company['name'] ?? '' }}<br>{{ $company['stamp_place'] }}</div>
                    @endif

                    <div class="q-sign-block">
                        @if (! empty($company['signature_url']))
                            <img src="{{ $company['signature_url'] }}" alt="" class="q-sign-img">
                        @endif
                        <div class="q-sign-rule"></div>
                        <div style="font-size:12px">{{ $company['authorized_person'] ?? 'Authorised Signatory' }}</div>
                        @if (! empty($company['designation']))
                            <div style="font-size:11px; color:var(--ink-2)">{{ $company['designation'] }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="q-pagefoot">
                <span>{{ $company['name'] ?? '' }}</span>
            </div>
        </div>
    </div>
</div>
</body>
</html>