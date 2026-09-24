@use('App\Enums\AccentPalette')
@use('App\Enums\HeaderAlignment')

@php
    $isEdit = $template->exists;
    $palettes = collect(AccentPalette::cases())
        ->mapWithKeys(fn (AccentPalette $p) => [$p->value => $p->colours()])
        ->all();
@endphp

<x-app-layout :title="$isEdit ? 'Edit Quote Template' : 'Create Quote Template'">
    {{-- Breadcrumb + page head, matching docs/quoteflow_template_editor.html --}}
    <div class="mb-3 flex flex-wrap items-center gap-1.5 text-[12.5px] text-app-faint">
        <a href="{{ route('templates.index') }}" class="transition-colors hover:text-app-accent">Templates</a>
        <span aria-hidden="true">/</span>
        <span class="font-medium text-app-muted">{{ $isEdit ? 'Edit Template' : 'Create Template' }}</span>
    </div>

    <div class="mb-[22px] flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-[24px] font-bold tracking-[-0.3px] text-app-text">
                {{ $isEdit ? 'Edit Quote Template' : 'Create Quote Template' }}
            </h1>
            <p class="mt-1 text-[13px] text-app-muted">Set up the reusable quotation format.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <x-button :href="route('templates.index')" variant="ghost">Cancel</x-button>
            <x-button type="submit" form="template-form" icon="save">Save Template</x-button>
        </div>
    </div>

    @if ($isEdit)
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="rounded-full bg-app-accent-soft px-2.5 py-1 text-xs font-semibold text-app-accent">
                Editing: {{ $template->name }}
            </span>
            @if ($template->is_default)
                <x-badge status="accent">Default template</x-badge>
            @endif
        </div>
    @endif

    {{-- .editor: minmax(0,1fr) minmax(440px,1.05fr), 24px gap --}}
    <div
        class="grid grid-cols-1 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(440px,1.05fr)]"
        x-data="{
            accent: @js($template->accent_color?->value ?? 'navy'),
            alignment: @js($template->header_alignment?->value ?? 'center'),
            palettes: @js($palettes),
            applyInk() {
                const sheet = this.$refs.preview && this.$refs.preview.querySelector('[style*=\'--ink\']');
                const c = this.palettes[this.accent];
                if (! sheet || ! c) return;
                sheet.style.setProperty('--ink', c.ink);
                sheet.style.setProperty('--ink-2', c.ink2);
                sheet.style.setProperty('--ink-soft', c.soft);
                sheet.style.setProperty('--ink-line', c.line);
            },
        }"
        x-init="$nextTick(() => applyInk())"
    >

        <form
            id="template-form"
            method="POST"
            enctype="multipart/form-data"
            action="{{ $isEdit ? route('templates.update', $template) : route('templates.store') }}"
            class="min-w-0"
        >
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            {{-- 1. Template Information --}}
            <x-editor-section :number="1" title="Template Information" hint="Letterhead details">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <x-input name="name" label="Template Name" required placeholder="e.g. Hardware Quote" :value="old('name', $template->name)" />
                        <p class="mt-1 text-[12px] text-app-faint">Only you see this name — it isn't printed on the quote.</p>
                    </div>

                    <div class="sm:col-span-2">
                        <x-input name="company_name" label="Client / Company Name" required placeholder="e.g. Himalayan Computers" :value="old('company_name', $template->company_name)" />
                    </div>

                    <x-input name="company_gstin" label="GSTIN" maxlength="15" placeholder="22AAAAA0000A1Z5" class="uppercase" :value="old('company_gstin', $template->company_gstin)" />
                    <x-input name="email" type="email" label="Email" placeholder="sales@company.com" :value="old('email', $template->email)" />

                    <x-input name="mobile_1" label="Mobile Number" placeholder="98XXX-XXXXX" :value="old('mobile_1', $template->mobile_1)" />
                    <x-input name="mobile_2" label="Alternate / Landline (optional)" placeholder="0177-2654410" :value="old('mobile_2', $template->mobile_2)" />

                    <x-textarea name="address" label="Address" rows="2" class="sm:col-span-2" :value="old('address', $template->address)" />

                    <x-input name="tagline" label="Business Category / Tagline" class="sm:col-span-2" placeholder="e.g. Computers, Printers &amp; Peripherals" :value="old('tagline', $template->tagline)" />
                    <p class="-mt-2 text-[12px] text-app-faint sm:col-span-2">Printed as "Deals in: …" under the company name.</p>
                </div>
            </x-editor-section>

            {{-- 2. Company Branding --}}
            <x-editor-section :number="2" title="Company Branding" hint="How the header looks">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="text-[12.5px] font-semibold text-app-muted">
                            Logo <span class="font-medium text-app-faint">(optional)</span>
                        </label>

                        <div class="mt-1.5 flex items-stretch gap-3">
                            <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-[6px] border border-app-border bg-app-bg">
                                @if ($template->logoUrl())
                                    <img src="{{ $template->logoUrl() }}" alt="Current logo" class="size-full object-contain">
                                @else
                                    <span class="text-[11px] text-app-faint">No logo</span>
                                @endif
                            </div>

                            <div class="flex min-w-0 flex-1 flex-col justify-center gap-1 rounded-[6px] border-[1.5px] border-dashed border-[#cfd5df] bg-app-bg px-3 py-2 text-center">
                                <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                    class="block w-full cursor-pointer text-[12.5px] text-app-muted file:mr-2 file:cursor-pointer file:rounded-[5px] file:border-0 file:bg-transparent file:px-0 file:text-[12.5px] file:font-semibold file:text-app-accent">
                                <p class="text-[11.5px] text-app-faint">PNG, JPG, SVG or WebP, up to 2 MB</p>
                                @error('logo') <p class="text-[12px] text-app-danger">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @if ($template->logo_path)
                            <label class="mt-2 inline-flex items-center gap-1.5 text-[12px] text-app-muted">
                                <input type="checkbox" name="remove_logo" value="1" class="rounded-[4px] border-app-border text-app-danger focus:ring-app-danger/30">
                                Remove current logo
                            </label>
                        @endif
                    </div>

                    <div class="sm:col-span-2">
                        <x-input name="letterhead_display_name" label="Company name on letterhead" placeholder="Same as Client / Company Name" :value="old('letterhead_display_name', $template->letterhead_display_name)" />
                        <p class="mt-1 text-[12px] text-app-faint">Leave blank to print the company name above. Use this for a shorter or stylised name.</p>
                    </div>

                    {{-- Header alignment (prototype .seg) --}}
                    <div>
                        <span class="text-[12.5px] font-semibold text-app-muted">Header alignment</span>
                        <div class="mt-1.5 inline-flex gap-0.5 rounded-[6px] bg-app-neutral-soft p-[3px]" role="radiogroup" aria-label="Header alignment">
                            @foreach (HeaderAlignment::cases() as $option)
                                <button type="button" role="radio"
                                    x-on:click="alignment = '{{ $option->value }}'"
                                    x-bind:aria-checked="alignment === '{{ $option->value }}'"
                                    x-bind:class="alignment === '{{ $option->value }}' ? 'bg-white font-semibold text-app-text' : 'font-medium text-app-muted hover:text-app-text'"
                                    class="rounded-[5px] px-3 py-1.5 text-[12.5px] transition-colors"
                                >{{ $option->label() }}</button>
                            @endforeach
                        </div>
                        <input type="hidden" name="header_alignment" x-bind:value="alignment">
                    </div>

                    {{-- Accent colour (prototype .swatches) --}}
                    <div>
                        <span class="text-[12.5px] font-semibold text-app-muted">Accent colour</span>
                        <div class="mt-1.5 flex flex-wrap gap-2">
                            @foreach (AccentPalette::cases() as $option)
                                <button type="button" title="{{ $option->label() }}" aria-label="{{ $option->label() }}"
                                    x-on:click="accent = '{{ $option->value }}'"
                                    x-bind:style="`background: {{ $option->colours()['ink'] }}`"
                                    x-bind:class="accent === '{{ $option->value }}' ? 'ring-2 ring-app-text ring-offset-2' : ''"
                                    class="size-7 rounded-full border-2 border-white shadow-[0_0_0_1px_rgba(228,231,236,1)]"
                                ></button>
                            @endforeach
                        </div>
                        <input type="hidden" name="accent_color" x-bind:value="accent">
                    </div>
                </div>
            </x-editor-section>

            {{-- 3. Quote Settings --}}
            <x-editor-section :number="3" title="Quote Settings" hint="Pre-filled on every new quote">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-input name="doc_title" label="Default quotation title" placeholder="QUOTATION / PROFORMA INVOICE" :value="old('doc_title', $template->doc_title)" />

                    <x-select name="default_gst_rate" label="Default GST rate" :value="old('default_gst_rate', $template->default_gst_rate)">
                        @foreach ([0, 5, 12, 18, 28] as $rate)
                            <option value="{{ $rate }}">{{ $rate }}%</option>
                        @endforeach
                    </x-select>

                    <div class="sm:col-span-2">
                        <x-textarea name="intro_message" label="Default introductory message" rows="3"
                            placeholder="While thanking you for your esteemed enquiry no. {enquiry_no} dated {enquiry_date}, we submit our lowest rates…"
                            :value="old('intro_message', $template->intro_message)" />

                        <p class="mt-2 flex flex-wrap items-center gap-1.5 text-[12px] text-app-faint">
                            Insert:
                            <code class="rounded-[5px] border border-app-border bg-app-bg px-1.5 py-0.5 font-mono text-[11.5px] text-app-muted">{client_name}</code>
                            <code class="rounded-[5px] border border-app-border bg-app-bg px-1.5 py-0.5 font-mono text-[11.5px] text-app-muted">{enquiry_no}</code>
                            <code class="rounded-[5px] border border-app-border bg-app-bg px-1.5 py-0.5 font-mono text-[11.5px] text-app-muted">{enquiry_date}</code>
                        </p>
                    </div>

                    <div class="sm:col-span-2">
                        <x-textarea name="extra_terms" label="Default terms &amp; conditions" rows="3"
                            placeholder="Payment after installation against bill.&#10;Goods once sold will not be taken back."
                            :value="old('extra_terms', $template->extra_terms)" />
                        <p class="mt-1 text-[12px] text-app-faint">One condition per line. GST, delivery, warranty and validity are added automatically.</p>
                    </div>

                    <x-input name="delivery_period" label="Default delivery period" placeholder="30 days from purchase order" :value="old('delivery_period', $template->delivery_period)" />
                    <x-input name="warranty" label="Default warranty" placeholder="Three years onsite" :value="old('warranty', $template->warranty)" />

                    <x-input name="validity_text" label="Default offer validity" placeholder="10 days from the above date" :value="old('validity_text', $template->validity_text)" />
                    <x-input name="notes" label="Default notes (optional)" placeholder="e.g. Freight extra as applicable" :value="old('notes', $template->notes)" />
                </div>
            </x-editor-section>

            {{-- 4. Footer & Signature --}}
            <x-editor-section :number="4" title="Footer & Signature" hint="Bottom-right of the quote">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-input name="authorized_person" label="Authorized person name" placeholder="Vikram Thakur" :value="old('authorized_person', $template->authorized_person)" />
                    <x-input name="designation" label="Designation" placeholder="Proprietor" :value="old('designation', $template->designation)" />

                    <div class="sm:col-span-2">
                        <label class="text-[12.5px] font-semibold text-app-muted">Signature <span class="font-medium text-app-faint">(optional)</span></label>

                        <div class="mt-1.5 flex items-stretch gap-3">
                            <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-[6px] border border-app-border bg-app-bg">
                                @if ($template->signatureUrl())
                                    <img src="{{ $template->signatureUrl() }}" alt="Current signature" class="size-full object-contain">
                                @else
                                    <span class="text-[11px] text-app-faint">No signature</span>
                                @endif
                            </div>

                            <div class="flex min-w-0 flex-1 flex-col justify-center gap-1 rounded-[6px] border-[1.5px] border-dashed border-[#cfd5df] bg-app-bg px-3 py-2 text-center">
                                <input type="file" name="signature" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                    class="block w-full cursor-pointer text-[12.5px] text-app-muted file:mr-2 file:cursor-pointer file:rounded-[5px] file:border-0 file:bg-transparent file:px-0 file:text-[12.5px] file:font-semibold file:text-app-accent">
                                <p class="text-[11.5px] text-app-faint">Transparent PNG works best, up to 2 MB</p>
                                @error('signature') <p class="text-[12px] text-app-danger">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @if ($template->signature_path)
                            <label class="mt-2 inline-flex items-center gap-1.5 text-[12px] text-app-muted">
                                <input type="checkbox" name="remove_signature" value="1" class="rounded-[4px] border-app-border text-app-danger focus:ring-app-danger/30">
                                Remove current signature
                            </label>
                        @endif
                    </div>

                    <div class="sm:col-span-2">
                        <label class="text-[12.5px] font-semibold text-app-muted">Company stamp <span class="font-medium text-app-faint">(optional)</span></label>

                        <div class="mt-1.5 flex items-stretch gap-3">
                            <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-[6px] border border-app-border bg-app-bg">
                                @if ($template->companyStampUrl())
                                    <img src="{{ $template->companyStampUrl() }}" alt="Current stamp" class="size-full object-contain">
                                @else
                                    <span class="text-[11px] text-app-faint">No stamp</span>
                                @endif
                            </div>

                            <div class="flex min-w-0 flex-1 flex-col justify-center gap-1 rounded-[6px] border-[1.5px] border-dashed border-[#cfd5df] bg-app-bg px-3 py-2 text-center">
                                <input type="file" name="company_stamp" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                    class="block w-full cursor-pointer text-[12.5px] text-app-muted file:mr-2 file:cursor-pointer file:rounded-[5px] file:border-0 file:bg-transparent file:px-0 file:text-[12.5px] file:font-semibold file:text-app-accent">
                                <p class="text-[11.5px] text-app-faint">Round seal image, PNG or JPG, up to 2 MB</p>
                                @error('company_stamp') <p class="text-[12px] text-app-danger">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        @if ($template->company_stamp_path)
                            <label class="mt-2 inline-flex items-center gap-1.5 text-[12px] text-app-muted">
                                <input type="checkbox" name="remove_company_stamp" value="1" class="rounded-[4px] border-app-border text-app-danger focus:ring-app-danger/30">
                                Remove current stamp
                            </label>
                        @endif
                    </div>

                    <label class="flex items-center gap-2 text-[12.5px] text-app-muted sm:col-span-2">
                        <input type="checkbox" name="use_generated_seal" value="1"
                            @checked(old('use_generated_seal', $template->use_generated_seal ?? true))
                            class="rounded-[4px] border-app-border text-app-accent focus:ring-app-accent/30">
                        Use a generated seal when no stamp is uploaded
                    </label>

                    <x-input name="stamp_place" label="Seal city / text" class="sm:col-span-2" placeholder="Shimla (H.P.)" :value="old('stamp_place', $template->stamp_place)" />
                </div>
            </x-editor-section>
        </form>

        {{-- Live A4 preview (sticky, right column) --}}
        <div class="min-w-0 xl:sticky xl:top-[76px]">
            <div class="overflow-hidden rounded-[8px] border border-app-border bg-app-surface">
                <div class="flex items-center justify-between gap-2 border-b border-app-border px-4 py-3">
                    <h3 class="flex items-center gap-2 text-[13px] font-bold text-app-text">
                        <span class="size-2 rounded-full bg-app-success" aria-hidden="true"></span>
                        Live Preview
                    </h3>

                    <div class="flex items-center gap-1" x-data="{ zoom: 100 }">
                        <button type="button"
                            class="rounded-[5px] border border-app-border px-2 py-1 text-[12px] font-semibold text-app-muted transition-colors hover:bg-app-neutral-soft"
                            x-on:click="zoom = 100">Fit</button>
                        <span class="w-11 text-center text-[12px] font-semibold tabular-nums text-app-muted" x-text="zoom + '%'">100%</span>
                        <button type="button"
                            class="flex size-6 items-center justify-center rounded-[5px] border border-app-border text-app-muted transition-colors hover:bg-app-neutral-soft"
                            x-on:click="zoom = Math.min(140, zoom + 10)" aria-label="Zoom in">
                            <x-icon name="plus" size="13" />
                        </button>
                    </div>
                </div>

                <p class="border-b border-app-border px-4 py-2 text-[11.5px] text-app-faint">
                    A4, items and client are sample data
                </p>

                <div x-ref="preview" x-effect="applyInk()" class="max-h-[720px] overflow-auto bg-app-bg p-4">
                    <div class="mx-auto origin-top"
                        x-bind:style="`transform: scale(${zoom / 100}); width: ${210 * 96 / 25.4}px;`">
                        <x-a4-sheet :doc="$previewDoc" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Sticky bottom bar --}}
    <div class="sticky bottom-0 z-20 mt-5 flex flex-wrap items-center justify-between gap-3 rounded-[8px] border border-app-border bg-app-surface px-4 py-3 shadow-app-card">
        <p class="text-[12.5px] text-app-faint">
            @if ($isEdit)
                Changes apply to new quotations only. Existing quotations keep their original snapshot.
            @else
                No changes yet
            @endif
        </p>

        <div class="flex flex-wrap items-center gap-2">
            <x-button :href="route('templates.index')" variant="ghost">Cancel</x-button>
            <x-button type="submit" form="template-form" icon="save">Save Template</x-button>
        </div>
    </div>
</x-app-layout>
