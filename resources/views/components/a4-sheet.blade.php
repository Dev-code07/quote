{{--
    A4 quotation sheet.

    Renders from a normalised `doc` array so the same markup serves the template
    preview (sample data), the quote preview and the PDF. Styling lives in
    resources/css/quotation.css.

    Expected $doc shape:
      company : name, display_name, gstin, tagline, address, email, mobile_1,
                mobile_2, stamp_place, logo_url, signature_url,
                authorized_person, designation
      accent  : ink, ink2, soft, line
      align   : left|center|right
      doc_title
      client  : name, address, gstin, email, phone
      meta    : no, date, valid, enquiry_no, enquiry_date
      items   : [position, description, qty, rate, amount]
      totals  : subtotal, discount, gst_rate, gst_amount, grand_total, words
      terms   : intro, delivery, warranty, validity, extra[], notes
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
@endphp

<div class="q-sheet" style="{{ $style }}" @if ($preview) data-preview-sheet @endif>
    <div class="q-frame">
        <div class="q-flow">

            {{-- 1. Top strip --}}
            <div class="q-strip">
                <div>
                    <b>Ph.</b> {{ $company['mobile_1'] ?? '' }}
                    @if (! empty($company['email']))
                        &nbsp;|&nbsp; <b>E-mail:</b> {{ $company['email'] }}
                    @endif
                </div>
                <div class="q-doc-title">{{ $doc['doc_title'] ?? 'QUOTATION' }}</div>
                <div style="text-align: right">
                    <b>Date:</b> {{ $meta['date'] ?? '—' }}
                </div>
            </div>

            {{-- 2. Letterhead --}}
            <div class="q-head {{ $align }}">
                <div style="display: flex; align-items: center; gap: 14px; justify-content: {{ $align === 'left' ? 'flex-start' : ($align === 'right' ? 'flex-end' : 'center') }};">
                    @if (! empty($company['logo_url']))
                        <img src="{{ $company['logo_url'] }}" alt="" class="q-logo">
                    @endif
                    <div>
                        <div class="q-company">{{ $company['display_name'] ?? ($company['name'] ?? 'Company') }}</div>
                    </div>
                </div>

                <div class="q-company-rule"></div>

                @if (! empty($company['tagline']))
                    <div class="q-deals">Deals in: <span>{{ $company['tagline'] }}</span></div>
                @endif

                @if (! empty($company['address']) || ! empty($company['company_gstin']))
                    <div class="q-address">
                        {{ $company['address'] ?? '' }}
                        @if (! empty($company['company_gstin']))
                            &nbsp;|&nbsp; GSTIN: {{ $company['company_gstin'] }}
                        @endif
                    </div>
                @endif
            </div>

            {{-- 3. Reference band --}}
            <div class="q-meta">
                <div>Ref. No. <b>{{ $meta['no'] ?? '—' }}</b></div>
                <div>Dated: <b>{{ $meta['date'] ?? '—' }}</b></div>
            </div>

            {{-- 4. Parties --}}
            <div class="q-parties">
                <div class="q-to">
                    <div class="q-to-label">To</div>
                    <div class="name">{{ $client['name'] ?? '—' }}</div>
                    @if (! empty($client['address']))
                        <p>{!! nl2br(e($client['address'])) !!}</p>
                    @endif
                    @if (! empty($client['gstin']))
                        <p>GSTIN: {{ $client['gstin'] }}</p>
                    @endif
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
                <p>{!! nl2br(e($terms['intro'] ?? 'While thanking you for your esteemed enquiry, we submit our lowest rates as under for favour of acceptance, subject to the terms and conditions given below.')) !!}</p>
            </div>

            {{-- 6. Items --}}
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
                    @forelse ($items as $item)
                        <tr>
                            <td class="c">{{ str_pad((string) ($item['position'] ?? $loop->iteration), 2, '0', STR_PAD_LEFT) }}</td>
                            <td class="desc">{{ $item['description'] ?? '—' }}</td>
                            <td class="c">{{ rtrim(rtrim(number_format((float) ($item['qty'] ?? 0), 2), '0'), '.') }}</td>
                            <td class="r">{{ $money($item['rate'] ?? 0) }}</td>
                            <td class="r">{{ $money($item['amount'] ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="text-align: center; color: #7a819c; padding: 14px;">
                                No items added yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($items)
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
                @endif
            </table>

            {{-- 7. Closing --}}
            @if ($items)
                <div class="q-closing">
                    <div class="q-words">
                        Amount in words: <b>{{ $totals['words'] ?? '' }}</b>
                    </div>

                    @php
                        $termLines = [];
                        if (($totals['gst_rate'] ?? 0) > 0) {
                            $termLines[] = 'GST extra @ '.$totals['gst_rate'].'% (included in Grand Total above)';
                        }
                        if (! empty($terms['delivery'])) {
                            $termLines[] = 'Delivery period: '.$terms['delivery'];
                        }
                        if (! empty($terms['warranty'])) {
                            $termLines[] = 'Warranty: '.$terms['warranty'];
                        }
                        if (! empty($terms['validity'])) {
                            $termLines[] = 'Validity of this offer: '.$terms['validity'];
                        }
                        foreach ($extra as $line) {
                            $termLines[] = $line;
                        }
                        if (! empty($terms['notes'])) {
                            $termLines[] = $terms['notes'];
                        }
                    @endphp

                    @if ($termLines)
                        <ul class="q-terms">
                            @foreach ($termLines as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="q-sign">
                        @if (! empty($company['stamp_place']))
                            <div class="q-stamp">
                                {{ $company['name'] ?? '' }}<br>{{ $company['stamp_place'] }}
                            </div>
                        @endif

                        <div class="q-sign-block">
                            @if (! empty($company['signature_url']))
                                <img src="{{ $company['signature_url'] }}" alt="" class="q-sign-img">
                            @endif
                            <div class="q-sign-rule"></div>
                            <div style="font-size: 12px">
                                {{ $company['authorized_person'] ?? 'Authorised Signatory' }}
                            </div>
                            @if (! empty($company['designation']))
                                <div style="font-size: 11px; color: var(--ink-2)">{{ $company['designation'] }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            {{-- 8. Page footer --}}
            <div class="q-pagefoot">
                <span>{{ $company['name'] ?? '' }}</span>
                <span>Page 1</span>
            </div>
        </div>
    </div>
</div>