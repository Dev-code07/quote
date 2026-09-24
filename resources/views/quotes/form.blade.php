@use('App\Services\CalculationService')

<x-app-layout :title="$quote->exists ? 'Edit Quotation '.$quote->quote_number : 'New Quotation'">
    <x-section-header
        :title="$quote->exists ? 'Edit Quotation' : 'New Quotation'"
        :description="$template->name.' — '.$template->company_name"
        back="{{ route('quotes.index') }}"
    />

    <form
        method="POST"
        action="{{ $quote->exists ? route('quotes.update', $quote) : route('quotes.store') }}"
        x-data="quoteBuilder(@js($builderSeed))"
        x-on:keydown.escape.window="if (dirty) { dirty = false }"
        class="space-y-4"
    >
        @csrf
        @if ($quote->exists)
            @method('PUT')
        @endif

        {{-- 1. Template --}}
        <x-card>
            <h2 class="mb-3 text-[14px] font-bold">
                <span class="mr-1.5 inline-flex size-5 items-center justify-center rounded-full bg-app-accent-soft text-[11px] font-bold text-app-accent">1</span>
                Template
            </h2>

            @if ($quote->exists && $quote->status->value !== 'draft')
                <p class="text-[13px] text-app-muted">
                    Using <span class="font-semibold">{{ $template->name }}</span>.
                    The template is locked because this quotation is {{ strtolower($quote->status->label()) }}.
                </p>
            @else
                <label for="template_id" class="text-xs font-medium text-app-muted">Quotation template</label>
                <select id="template_id" name="template_id" x-model="templateId" x-on:change="applyTemplateDefaults()" class="mt-1.5 block w-full rounded-[6px] border border-app-border bg-white px-3 py-2 text-[13px] focus:border-app-accent focus:ring-2 focus:ring-app-accent/25">
                    @foreach ($templates as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $template->id)>
                            {{ $option->name }}{{ $option->is_default ? ' (default)' : '' }}
                        </option>
                    @endforeach
                </select>
                @error('template_id') <p class="mt-1 text-xs text-app-danger">{{ $message }}</p> @enderror
            @endif
        </x-card>

        {{-- 2. Client --}}
        <x-card>
            <h2 class="mb-3 text-[14px] font-bold">
                <span class="mr-1.5 inline-flex size-5 items-center justify-center rounded-full bg-app-accent-soft text-[11px] font-bold text-app-accent">2</span>
                Client
            </h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="client_id" class="text-xs font-medium text-app-muted">
                        Saved client <span class="text-app-danger" aria-hidden="true">*</span>
                    </label>
                    <select id="client_id" name="client_id" x-model="clientId" class="mt-1.5 block w-full rounded-[6px] border border-app-border bg-white px-3 py-2 text-[13px] focus:border-app-accent focus:ring-2 focus:ring-app-accent/25">
                        <option value="">Select a client…</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-app-faint">
                        Need a new customer? Add them under
                        <a href="{{ route('clients.index') }}" class="text-app-accent hover:underline">Clients</a>
                        first — one-off clients are not created from this screen.
                    </p>
                    @error('client_id') <p class="mt-1 text-xs text-app-danger">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-card>

        {{-- 3. Dates --}}
        <x-card>
            <h2 class="mb-3 text-[14px] font-bold">
                <span class="mr-1.5 inline-flex size-5 items-center justify-center rounded-full bg-app-accent-soft text-[11px] font-bold text-app-accent">3</span>
                Quotation Details
            </h2>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <span class="text-xs font-medium text-app-muted">Quote No.</span>
                    <p class="mt-1.5 rounded-[6px] border border-app-border bg-app-neutral-soft px-3 py-2 font-mono text-[13px] font-semibold">{{ $nextNumber }}</p>
                    <p class="mt-1 text-xs text-app-faint">Generated automatically.</p>
                </div>

                <x-input name="quote_date" type="date" label="Quotation date" required />

                <x-input name="valid_until" type="date" label="Valid until" required />

                <x-input name="enquiry_no" label="Enquiry No. (optional)" placeholder="ENQ/2026/0142" />

                <x-input name="enquiry_date" type="date" label="Enquiry date (optional)" />
            </div>
        </x-card>

        {{-- 4. Items --}}
        <x-card :padding="false">
            <div class="flex items-center justify-between gap-2 border-b border-app-border px-5 py-3.5">
                <h2 class="text-[14px] font-bold">
                    <span class="mr-1.5 inline-flex size-5 items-center justify-center rounded-full bg-app-accent-soft text-[11px] font-bold text-app-accent">4</span>
                    Items
                </h2>
                <span class="text-xs text-app-faint">Amounts are calculated automatically</span>
            </div>

            @error('items') <p class="px-5 pt-3 text-xs text-app-danger">{{ $message }}</p> @enderror
            @error('items.*.description') <p class="px-5 pt-3 text-xs text-app-danger">{{ $message }}</p> @enderror
            @error('items.*.qty') <p class="px-5 pt-3 text-xs text-app-danger">{{ $message }}</p> @enderror

            <div class="overflow-x-auto">
                <table class="w-full text-left text-[13px]">
                    <thead>
                        <tr class="border-b border-app-border text-xs uppercase tracking-wide text-app-faint">
                            <th class="w-12 px-4 py-2.5 text-center font-semibold">Sr.</th>
                            <th class="px-2 py-2.5 font-semibold">Description</th>
                            <th class="w-28 px-2 py-2.5 text-right font-semibold">Qty.</th>
                            <th class="w-36 px-2 py-2.5 text-right font-semibold">Rate (₹)</th>
                            <th class="w-36 px-2 py-2.5 text-right font-semibold">Amount (₹)</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, index) in items" :key="item.key">
                            <tr class="border-b border-app-border last:border-0">
                                <td class="px-4 py-2 text-center tabular-nums text-app-faint" x-text="index + 1"></td>
                                <td class="px-2 py-2">
                                    <input type="text" x-model="item.description" @input="dirty = true"
                                        :name="`items[${index}][description]`"
                                        placeholder="Item description"
                                        class="block w-full rounded-[6px] border border-app-border px-2.5 py-1.5 text-[13px] focus:border-app-accent focus:ring-2 focus:ring-app-accent/25">
                                </td>
                                <td class="px-2 py-2">
                                    <input type="number" step="0.01" min="0" x-model.number="item.qty" @input="dirty = true"
                                        :name="`items[${index}][qty]`"
                                        class="block w-full rounded-[6px] border border-app-border px-2.5 py-1.5 text-right text-[13px] tabular-nums focus:border-app-accent focus:ring-2 focus:ring-app-accent/25">
                                </td>
                                <td class="px-2 py-2">
                                    <input type="number" step="0.01" min="0" x-model.number="item.rate" @input="dirty = true"
                                        :name="`items[${index}][rate]`"
                                        class="block w-full rounded-[6px] border border-app-border px-2.5 py-1.5 text-right text-[13px] tabular-nums focus:border-app-accent focus:ring-2 focus:ring-app-accent/25">
                                </td>
                                <td class="px-2 py-2 text-right font-semibold tabular-nums" x-text="fmt(item.qty * item.rate)"></td>
                                <td class="px-2 py-2 text-right">
                                    <button type="button" x-on:click="remove(index)" class="rounded-[6px] p-1.5 text-app-faint hover:bg-app-danger-soft hover:text-app-danger" aria-label="Remove item">
                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <p x-show="items.length === 0" class="px-5 py-6 text-center text-[13px] text-app-faint">
                No items added. Use “Add Item” to begin.
            </p>

            <div class="border-t border-app-border px-5 py-3">
                <x-button type="button" variant="ghost" icon="plus" x-on:click="addItem()">Add Item</x-button>
            </div>
        </x-card>

        {{-- 5. Pricing --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <x-card>
                <h2 class="mb-3 text-[14px] font-bold">
                    <span class="mr-1.5 inline-flex size-5 items-center justify-center rounded-full bg-app-accent-soft text-[11px] font-bold text-app-accent">5</span>
                    Pricing
                </h2>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-select
                        name="gst_rate"
                        label="GST Rate"
                        required
                        x-model.number="gstRate"
                        x-on:change="dirty = true"
                        :options="[
                            '0' => '0% (Exempt)',
                            '5' => '5%',
                            '12' => '12%',
                            '18' => '18%',
                            '28' => '28%',
                        ]"
                    />

                    <x-input
                        name="discount_amount"
                        type="number"
                        step="0.01"
                        min="0"
                        label="Discount (₹)"
                        x-model.number="discount"
                        x-on:input="dirty = true"
                        hint="Flat amount off the subtotal."
                    />
                </div>
            </x-card>

            <x-card>
                <h2 class="mb-3 text-[14px] font-bold">Summary</h2>

                <dl class="space-y-2 text-[13px]">
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-muted">Subtotal</dt>
                        <dd class="font-semibold tabular-nums" x-text="money(subtotal)">₹0.00</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-muted">GST (<span x-text="gstRate"></span>%)</dt>
                        <dd class="font-semibold tabular-nums" x-text="money(gstAmount)">₹0.00</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-app-muted">Discount</dt>
                        <dd class="font-semibold tabular-nums text-app-danger" x-text="'- ' + money(discountApplied)">− ₹0.00</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-t border-app-border pt-2">
                        <dt class="text-[14px] font-bold">Grand Total</dt>
                        <dd class="text-[16px] font-bold tabular-nums text-app-accent" x-text="money(grandTotal)">₹0.00</dd>
                    </div>
                </dl>

                <p class="mt-3 border-t border-app-border pt-3 text-xs text-app-faint">
                    Saved values are always recalculated by the server.
                </p>
            </x-card>
        </div>

        {{-- 6. Terms --}}
        <x-card>
            <h2 class="mb-1 text-[14px] font-bold">
                <span class="mr-1.5 inline-flex size-5 items-center justify-center rounded-full bg-app-accent-soft text-[11px] font-bold text-app-accent">6</span>
                Terms &amp; Conditions
            </h2>
            <p class="mb-3 text-xs text-app-muted">Pre-filled from the template. Edit anything that differs for this quotation.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="terms[delivery]" label="Delivery period" x-model="terms.delivery" x-on:input="dirty = true" />
                <x-input name="terms[warranty]" label="Warranty" x-model="terms.warranty" x-on:input="dirty = true" />
                <x-input name="terms[validity]" label="Offer validity" x-model="terms.validity" x-on:input="dirty = true" class="sm:col-span-2" />

                <x-textarea name="terms[extra]" label="Extra terms (one per line)" rows="3" class="sm:col-span-2" x-model="terms.extra" x-on:input="dirty = true" />
                <x-textarea name="terms[notes]" label="Notes" rows="2" class="sm:col-span-2" x-model="terms.notes" x-on:input="dirty = true" />
            </div>
        </x-card>

        {{-- Sticky action bar --}}
        <div class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 rounded-[8px] border border-app-border bg-app-surface px-4 py-3 shadow-app-card">
            <div class="text-[13px] text-app-muted">
                Grand Total
                <span class="ml-1 text-[16px] font-bold tabular-nums text-app-accent" x-text="money(grandTotal)">₹0.00</span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-button :href="route('quotes.index')" variant="ghost" icon="x">Cancel</x-button>
                <x-button type="submit" name="intent" value="draft" variant="subtle" icon="save">Save Draft</x-button>
                <x-button type="submit" name="intent" value="generate" icon="check">
                    {{ $quote->exists ? 'Update Quotation' : 'Generate Quotation' }}
                </x-button>
            </div>
        </div>
    </form>
</x-app-layout>