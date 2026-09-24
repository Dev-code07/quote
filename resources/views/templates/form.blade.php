@use('App\Enums\AccentPalette')
@use('App\Enums\HeaderAlignment')

<x-app-layout :title="$template->exists ? 'Edit Template' : 'New Template'">
    <x-section-header
        :title="$template->exists ? 'Edit Template' : 'New Template'"
        :description="$template->exists ? 'Changes apply to new quotations only. Existing quotations keep their original snapshot.' : 'Build a reusable, branded quotation layout.'"
        back="{{ route('templates.index') }}"
    />

    @if ($template->exists)
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="rounded-full bg-app-accent-soft px-2.5 py-1 text-xs font-semibold text-app-accent">
                Editing: {{ $template->name }}
            </span>
            @if ($template->is_default)
                <x-badge status="accent">Default template</x-badge>
            @endif
        </div>
    @endif

    <form
        method="POST"
        enctype="multipart/form-data"
        action="{{ $template->exists ? route('templates.update', $template) : route('templates.store') }}"
        x-data="{
            accent: @js($template->accent_color?->value ?? 'navy'),
            alignment: @js($template->header_alignment?->value ?? 'center'),
            step: 1,
            total: 4,
            next() { if (this.step < this.total) { this.step++ ; this.$nextTick(() => this.$refs.form.scrollIntoView({behavior:'smooth', block:'start'})) } },
            back() { if (this.step > 1) { this.step-- ; this.$nextTick(() => this.$refs.form.scrollIntoView({behavior:'smooth', block:'start'})) } }
        }"
        x-ref="form"
        class="space-y-4"
    >
        @csrf
        @if ($template->exists)
            @method('PUT')
        @endif

        {{-- Step navigation --}}
        <x-card>
            <ol class="grid grid-cols-2 gap-2 sm:grid-cols-4" role="tablist" aria-label="Template steps">
                @foreach ([1 => 'Business Information', 2 => 'Company Branding', 3 => 'Quote Settings', 4 => 'Footer & Signature'] as $number => $label)
                    <li>
                        <button
                            type="button"
                            role="tab"
                            x-on:click="step = {{ $number }}"
                            x-bind:aria-selected="step === {{ $number }}"
                            class="flex w-full items-center gap-2 rounded-[8px] border px-3 py-2 text-left text-[13px] font-semibold transition-colors"
                            x-bind:class="step === {{ $number }} ? 'border-app-accent bg-app-accent-soft text-app-accent' : 'border-app-border bg-white text-app-muted hover:bg-app-neutral-soft'"
                        >
                            <span
                                class="flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold"
                                x-bind:class="step === {{ $number }} ? 'bg-app-accent text-white' : 'bg-app-neutral-soft text-app-muted'"
                            >{{ $number }}</span>
                            <span class="truncate">{{ $label }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>
        </x-card>

        {{-- STEP 1: Business Information --}}
        <x-card x-show="step === 1">
            <h3 class="mb-4 text-[15px] font-bold">1. Business Information</h3>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="name" label="Template Name" required class="sm:col-span-2" placeholder="e.g. Standard Business Quote" />

                <x-input name="company_name" label="Company / Business Name" required placeholder="e.g. ABC Technologies Pvt. Ltd." />

                <x-input name="company_gstin" label="Company GSTIN" maxlength="15" placeholder="22AAAAA0000A1Z5" />

                <x-input name="tagline" label="Business Category / Tagline" placeholder="e.g. IT Hardware, Software &amp; Networking" hint="Printed as “Deals in: …” under the company name." />

                <x-input name="email" type="email" label="Email" placeholder="sales@company.in" />

                <x-input name="mobile_1" label="Mobile 1" placeholder="98110-22334" />
                <x-input name="mobile_2" label="Mobile 2 (office)" placeholder="0120-4567890" />

                <x-input name="stamp_place" label="Stamp / City Place" placeholder="e.g. Noida (U.P.)" class="sm:col-span-2" hint="Appears inside the circular stamp on the quotation." />

                <x-textarea name="address" label="Address" rows="2" class="sm:col-span-2" placeholder="Full business address" />
            </div>
        </x-card>

        {{-- STEP 2: Company Branding --}}
        <x-card x-show="step === 2">
            <h3 class="mb-1 text-[15px] font-bold">2. Company Branding</h3>
            <p class="mb-4 text-[12.5px] text-app-muted">How the letterhead looks on every quotation from this template.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-app-muted">Logo <span class="text-app-faint">(optional)</span></label>
                    <div class="mt-1.5 flex items-start gap-4">
                        <div class="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-[8px] border border-dashed border-app-border bg-app-neutral-soft">
                            @if ($template->logoUrl())
                                <img src="{{ $template->logoUrl() }}" alt="Current logo" class="max-h-full max-w-full object-contain">
                            @else
                                <span class="text-[11px] text-app-faint">No logo</span>
                            @endif
                        </div>
                        <div class="flex-1">
                            <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                class="block w-full cursor-pointer rounded-[6px] border border-app-border bg-white text-[13px] file:mr-3 file:cursor-pointer file:rounded-l-[6px] file:border-0 file:bg-app-neutral-soft file:px-3 file:py-2 file:text-[13px] file:font-semibold file:text-app-muted hover:file:bg-app-border">
                            <p class="mt-1 text-xs text-app-faint">PNG, JPG, SVG or WebP, up to 2 MB.</p>
                            @if ($template->logo_path)
                                <label class="mt-2 inline-flex items-center gap-1.5 text-xs text-app-muted">
                                    <input type="checkbox" name="remove_logo" value="1" class="rounded-[4px] border-app-border text-app-danger focus:ring-app-danger/30">
                                    Remove current logo
                                </label>
                            @endif
                            @error('logo') <p class="mt-1 text-xs text-app-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                <x-input name="letterhead_display_name" label="Company name on letterhead" class="sm:col-span-2" placeholder="Leave blank to use the business name" hint="Use this for a shorter or stylised name." />

                <div>
                    <span class="text-xs font-medium text-app-muted">Header alignment</span>
                    <div class="mt-1.5 inline-flex rounded-[8px] border border-app-border bg-white p-0.5" role="radiogroup" aria-label="Header alignment">
                        @foreach (HeaderAlignment::cases() as $case)
                            <button
                                type="button"
                                role="radio"
                                x-on:click="alignment = '{{ $case->value }}'"
                                x-bind:aria-checked="alignment === '{{ $case->value }}'"
                                class="rounded-[6px] px-3 py-1.5 text-[13px] font-semibold transition-colors"
                                x-bind:class="alignment === '{{ $case->value }}' ? 'bg-app-accent-soft text-app-accent' : 'text-app-muted hover:text-app-text'"
                            >{{ $case->label() }}</button>
                        @endforeach
                    </div>
                    <input type="hidden" name="header_alignment" x-bind:value="alignment">
                </div>

                <div>
                    <span class="text-xs font-medium text-app-muted">Accent colour</span>
                    <div class="mt-1.5 flex flex-wrap gap-2" role="radiogroup" aria-label="Accent colour">
                        @foreach (AccentPalette::cases() as $palette)
                            <button
                                type="button"
                                role="radio"
                                title="{{ $palette->label() }}"
                                aria-label="{{ $palette->label() }}"
                                x-on:click="accent = '{{ $palette->value }}'"
                                x-bind:aria-checked="accent === '{{ $palette->value }}'"
                                class="size-7 rounded-full ring-offset-2 transition-all"
                                x-bind:class="accent === '{{ $palette->value }}' ? 'ring-2 ring-app-text scale-110' : 'ring-1 ring-app-border'"
                                style="background: {{ $palette->colours()['ink'] }}"
                            ></button>
                        @endforeach
                    </div>
                    <input type="hidden" name="accent_color" x-bind:value="accent">
                    <p class="mt-1 text-xs text-app-faint">Sets the ink colour of the whole quotation.</p>
                </div>
            </div>
        </x-card>

        {{-- STEP 3: Quote Settings --}}
        <x-card x-show="step === 3">
            <h3 class="mb-1 text-[15px] font-bold">3. Quote Settings</h3>
            <p class="mb-4 text-[12.5px] text-app-muted">Pre-filled on every new quotation created from this template.</p>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="doc_title" label="Default quotation title" required list="doc-title-list" placeholder="QUOTATION" />
                <datalist id="doc-title-list">
                    <option value="QUOTATION"></option>
                    <option value="QUOTATION / PROFORMA INVOICE"></option>
                    <option value="PROFORMA INVOICE"></option>
                    <option value="ESTIMATE"></option>
                </datalist>

                <x-select
                    name="default_gst_rate"
                    label="Default GST rate"
                    required
                    :options="['0' => '0% (Exempt)', '5' => '5%', '12' => '12%', '18' => '18%', '28' => '28%']"
                />

                <div class="flex items-end pb-1">
                    <label class="flex items-center gap-2 text-[13px] text-app-muted">
                        <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $template->is_default)) class="rounded-[4px] border-app-border text-app-accent focus:ring-app-accent/30">
                        Use as the default template
                    </label>
                </div>

                <x-textarea
                    name="intro_message"
                    label="Introductory message"
                    rows="3"
                    class="sm:col-span-2"
                    placeholder="While thanking you for your esteemed enquiry no. {enquiry_no} dated {enquiry_date}, we submit our lowest rates…"
                    hint="Placeholders: {client_name}, {enquiry_no}, {enquiry_date} are replaced automatically."
                />

                <x-input name="delivery_period" label="Delivery period" placeholder="2–3 weeks from purchase order" />
                <x-input name="warranty" label="Warranty" placeholder="One year (manufacturer)" />
                <x-input name="validity_text" label="Offer validity" placeholder="15 days from the above date" class="sm:col-span-2" />

                <x-textarea name="extra_terms" label="Extra terms &amp; conditions" rows="3" class="sm:col-span-2" hint="One condition per line." placeholder="Payment: 50% advance, balance before delivery." />
                <x-textarea name="notes" label="Notes" rows="2" class="sm:col-span-2" />
            </div>
        </x-card>

        {{-- STEP 4: Footer & Signature --}}
        <x-card x-show="step === 4">
            <h3 class="mb-4 text-[15px] font-bold">4. Footer &amp; Signature</h3>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-input name="authorized_person" label="Authorised person" placeholder="e.g. Rajesh Menon" />
                <x-input name="designation" label="Designation" placeholder="e.g. Director" />

                <div class="sm:col-span-2">
                    <label class="text-xs font-medium text-app-muted">Signature / stamp image <span class="text-app-faint">(optional)</span></label>
                    <div class="mt-1.5 flex items-start gap-4">
                        <div class="flex h-16 w-40 shrink-0 items-center justify-center overflow-hidden rounded-[8px] border border-dashed border-app-border bg-app-neutral-soft">
                            @if ($template->signatureUrl())
                                <img src="{{ $template->signatureUrl() }}" alt="Current signature" class="max-h-full max-w-full object-contain">
                            @else
                                <span class="text-[11px] text-app-faint">No signature</span>
                            @endif
                        </div>
                        <div class="flex-1">
                            <input type="file" name="signature" accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                class="block w-full cursor-pointer rounded-[6px] border border-app-border bg-white text-[13px] file:mr-3 file:cursor-pointer file:rounded-l-[6px] file:border-0 file:bg-app-neutral-soft file:px-3 file:py-2 file:text-[13px] file:font-semibold file:text-app-muted hover:file:bg-app-border">
                            <p class="mt-1 text-xs text-app-faint">PNG, JPG, SVG or WebP, up to 2 MB. A transparent PNG works best.</p>
                            @if ($template->signature_path)
                                <label class="mt-2 inline-flex items-center gap-1.5 text-xs text-app-muted">
                                    <input type="checkbox" name="remove_signature" value="1" class="rounded-[4px] border-app-border text-app-danger focus:ring-app-danger/30">
                                    Remove current signature
                                </label>
                            @endif
                            @error('signature') <p class="mt-1 text-xs text-app-danger">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>
            </div>
        </x-card>

        {{-- Sticky action bar --}}
        <div class="sticky bottom-0 flex flex-wrap items-center justify-between gap-3 rounded-[8px] border border-app-border bg-app-surface px-4 py-3 shadow-app-card">
            <div class="text-[12.5px] text-app-muted">
                Step <span class="font-semibold text-app-text" x-text="step"></span> of <span x-text="total"></span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-button type="button" variant="ghost" :href="route('templates.index')">Cancel</x-button>
                <x-button type="button" variant="ghost" x-on:click="back()" x-show="step > 1">&larr; Back</x-button>
                <x-button type="button" variant="subtle" x-on:click="next()" x-show="step < total">Next &rarr;</x-button>
                <x-button type="submit">{{ $template->exists ? 'Save Changes' : 'Create Template' }}</x-button>
            </div>
        </div>
    </form>
</x-app-layout>