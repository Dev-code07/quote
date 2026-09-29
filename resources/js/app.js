import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Quote builder (Phase 4, quoteflow_quote_builder.html).
 *
 * Totals here are INDICATIVE ONLY. The server recomputes everything through
 * CalculationService on save, so what is shown can never become what is stored
 * (rules.md section 5.1, BR-02).
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('quoteBuilder', (seed, previewEndpoint = '') => ({
        /* Coerce select-bound ids to strings: option values are strings, and
           Alpine's x-model uses strict comparison, so numeric ids would never
           match and Client/Template dropdowns would look empty on edit. */
        templateId: seed.templateId != null && seed.templateId !== '' ? String(seed.templateId) : '',
        clientId: seed.clientId != null && seed.clientId !== '' ? String(seed.clientId) : '',
        gstRate: Number(seed.gstRate ?? 18),
        discount: Number(seed.discount ?? 0),
        dirty: false,

        /* A4 preview overlay (docs/quoteflow_quote_builder (1).html). */
        previewEndpoint,
        previewOpen: false,
        previewLoading: false,
        previewError: '',
        previewController: null,

        items: (seed.items ?? []).map((item) => ({ ...item, key: Math.random() })),
        terms: { ...(seed.terms ?? {}) },

        /** Template defaults, used when the admin switches template. */
        templates: seed.templates ?? {},

        get subtotal() {
            return this.items.reduce(
                (sum, item) => sum + (Number(item.qty) || 0) * (Number(item.rate) || 0),
                0
            );
        },

        /** A discount can never exceed the subtotal (mirrors CalculationService). */
        get discountApplied() {
            return Math.min(Math.max(Number(this.discount) || 0, 0), this.subtotal);
        },

        get discounted() {
            return this.subtotal - this.discountApplied;
        },

        get gstAmount() {
            return this.discounted * (Number(this.gstRate) || 0) / 100;
        },

        get grandTotal() {
            return this.discounted + this.gstAmount;
        },

        addItem() {
            this.items.push({ key: Math.random(), description: '', qty: 1, rate: 0 });
            this.dirty = true;
        },

        remove(index) {
            this.items.splice(index, 1);
            this.dirty = true;
        },

        applyTemplateDefaults() {
            const preset = this.templates[String(this.templateId)];

            if (!preset) {
                return;
            }

            this.gstRate = Number(preset.gst_rate ?? this.gstRate);
            this.terms = { ...preset.terms, ...this.terms };

            if (this.items.length === 0) {
                this.items.push({ key: Math.random(), description: '', qty: 1, rate: 0 });
            }

            this.dirty = true;
        },

        /** Rupee formatting with Indian digit grouping. */
        money(value) {
            return new Intl.NumberFormat('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(Number(value) || 0);
        },

        fmt(value) {
            return this.money(value);
        },

        /* ---------------- A4 preview overlay ---------------- */

        /** POST the live form to QuoteController::preview and open overlay. */
        async openPreview() {
            this.previewOpen = true;
            this.previewError = '';
            this.previewLoading = true;
            document.body.style.overflow = 'hidden';

            if (this.previewController) {
                this.previewController.abort();
            }

            this.previewController = new AbortController();

            try {
                const form = this.$refs.form;
                const data = new FormData(form);

                // Drop framework + submit-only fields the preview must ignore.
                for (const key of ['_token', '_method', 'intent', 'quote_id']) {
                    data.delete(key);
                }

                if (this.templateId) {
                    data.set('template_id', this.templateId);
                }

                if (this.clientId) {
                    data.set('client_id', this.clientId);
                }

                const quoteId = form.querySelector('input[name="quote_id"]');

                if (quoteId && quoteId.value) {
                    data.append('quote_id', quoteId.value);
                }

                const response = await fetch(this.previewEndpoint, {
                    method: 'POST',
                    body: data,
                    signal: this.previewController.signal,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    throw new Error(`Preview failed (${response.status})`);
                }

                this.$refs.preview.innerHTML = await response.text();
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error('Preview failed', error);
                    this.previewError = 'Could not load the preview. Check the form and try again.';
                }
            } finally {
                this.previewLoading = false;
            }
        },

        closePreview() {
            this.previewOpen = false;
            this.previewError = '';
            document.body.style.overflow = '';

            if (this.previewController) {
                this.previewController.abort();
                this.previewController = null;
            }
        },

        /** Like the prototype: the saved quotation's real PDF downloads. */
        downloadPdf() {
            const form = this.$refs.form;
            const quoteId = form.querySelector('input[name="quote_id"]');

            if (quoteId && quoteId.value) {
                window.open(`/quotes/${quoteId.value}/pdf`, '_blank', 'noopener');
                return;
            }

            // Not saved yet: keep the prototype flow — save first, then the
            // show screen offers the real Download PDF.
            this.closePreview();
            this.$nextTick(() => {
                form.requestSubmit(
                    Object.assign(document.createElement('button'), {
                        type: 'submit',
                        name: 'intent',
                        value: 'generate',
                    }),
                );
            });
        },
    }));

    /**
     * Template editor live preview (docs/quoteflow_template_editor.html).
     *
     * The right-hand A4 sheet is re-rendered by the SERVER, not rebuilt here in
     * JavaScript. Every field posts to QuoteTemplateController::preview, which
     * runs the same QuotationDocumentService the PDF uses, so the preview cannot
     * drift away from the printed document (Architecture.md 5.4).
     *
     * Only two things are handled locally, because they cannot be:
     *   - uploaded images (read with FileReader, never uploaded per keystroke)
     *   - zoom / fit
     */
    Alpine.data('templateEditor', (config) => ({
        endpoint: config.endpoint,
        templateId: config.templateId ?? '',
        accent: config.accent ?? 'navy',
        alignment: config.alignment ?? 'center',
        palettes: config.palettes ?? {},

        /** 'fit' or 1 (= 100%). */
        zoom: 'fit',
        dirty: false,
        /** Long content that would spill onto a second printed page. */
        overflow: false,
        /** Images chosen in this session, as data URLs, keyed by form field. */
        pending: { logo: '', signature: '', stamp: '' },

        timer: null,
        controller: null,

        get hasPending() {
            return Object.values(this.pending).some(Boolean);
        },

        init() {
            this.$nextTick(() => {
                this.measure();
                this.fit();
            });

            if (document.fonts && document.fonts.ready) {
                // Metrics change once the real fonts land; re-measure after.
                document.fonts.ready.then(() => {
                    this.measure();
                    this.fit();
                });
            }

            window.addEventListener('resize', () => this.fit());
        },

        /* ---------------- form -> preview ---------------- */

        onFormInput(event) {
            const el = event.target;

            if (el.type === 'file') {
                this.readImage(el);
                return;
            }

            if (el.type === 'checkbox' || el.type === 'radio') {
                return;
            }

            this.schedule();
        },

        onFormChange(event) {
            const el = event.target;

            if (el.type === 'file') {
                this.readImage(el);
                return;
            }

            if (el.name === 'remove_logo') {
                this.pending.logo = '';
                this.paintThumb('logo', '');
            }

            if (el.name === 'remove_signature') {
                this.pending.signature = '';
                this.paintThumb('signature', '');
            }

            if (el.name === 'remove_company_stamp') {
                this.pending.stamp = '';
                this.paintThumb('stamp', '');
            }

            this.schedule();
        },

        /** Accent and alignment live in Alpine state, not in a real input event. */
        onAccentChange() {
            this.schedule();
        },

        markDirty() {
            this.dirty = true;
        },

        schedule() {
            this.markDirty();

            if (this.timer) {
                clearTimeout(this.timer);
            }

            this.timer = setTimeout(() => this.render(), 200);
        },

        /** Current form values, minus the file inputs (never upload per keystroke). */
        payload() {
            const data = new FormData(this.$refs.form);

            for (const key of ['logo', 'signature', 'company_stamp']) {
                data.delete(key);
            }

            if (this.templateId) {
                data.append('template_id', this.templateId);
            }

            return data;
        },

        async render() {
            if (this.controller) {
                this.controller.abort();
            }

            this.controller = new AbortController();

            try {
                const response = await fetch(this.endpoint, {
                    method: 'POST',
                    body: this.payload(),
                    signal: this.controller.signal,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    return;
                }

                const html = await response.text();

                this.$refs.preview.innerHTML = html;
                this.applyImages();
                this.$nextTick(() => {
                    this.fit();
                });
            } catch (error) {
                // An aborted request was superseded by a newer keystroke.
                if (error.name !== 'AbortError') {
                    console.error('Preview failed', error);
                }
            }
        },

        /* ---------------- uploaded images ---------------- */

        readImage(input) {
            const key = { logo: 'logo', signature: 'signature', company_stamp: 'stamp' }[input.name];
            const file = input.files && input.files[0];

            if (!key || !file) {
                return;
            }

            // Mirror the server rules (StoreQuoteTemplateRequest) so the admin
            // is told immediately rather than after a round trip.
            const allowed = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];

            if (!allowed.includes(file.type)) {
                this.showUploadError(input, 'Use a PNG, JPG, SVG or WebP image.');
                return;
            }

            if (file.size > 2 * 1024 * 1024) {
                this.showUploadError(input, 'This file is larger than 2 MB. Choose a smaller image.');
                return;
            }

            this.showUploadError(input, '');

            const reader = new FileReader();

            reader.onload = () => {
                this.pending[key] = reader.result;
                this.paintThumb(key, reader.result);
                this.applyImages();
                this.schedule();
            };

            reader.readAsDataURL(file);
        },

        showUploadError(input, message) {
            const box = input.closest('.upload-field');
            const target = box && box.querySelector('[data-upload-error]');

            if (target) {
                target.textContent = message;
            }
        },

        /** Update the little "No logo" thumbnail beside each upload control. */
        paintThumb(key, src) {
            const thumb = document.querySelector(`[data-thumb="${key}"]`);

            if (!thumb) {
                return;
            }

            const existing = thumb.querySelector('img');

            if (src) {
                if (existing) {
                    existing.src = src;
                } else {
                    const image = document.createElement('img');
                    image.src = src;
                    image.alt = 'Selected image';
                    image.className = 'size-full object-contain';
                    thumb.replaceChildren(image);
                }

                return;
            }

            if (existing) {
                existing.remove();
            }

            const label = { logo: 'No logo', signature: 'No signature', stamp: 'No stamp' }[key];

            if (label) {
                const span = document.createElement('span');
                span.className = 'text-[11px] text-app-faint';
                span.textContent = label;
                thumb.appendChild(span);
            }
        },

        /**
         * Overlay the just-picked images on the server-rendered sheet.
         *
         * The sheet arrives without them (nothing has been saved yet), so the
         * logo / signature / stamp nodes are inserted, replaced or removed here.
         */
        applyImages() {
            const root = this.$refs.preview;

            if (!root) {
                return;
            }

            const brand = root.querySelector('.q-brand');
            let logo = root.querySelector('[data-q-part="logo"]');

            if (this.pending.logo) {
                if (!logo && brand) {
                    logo = document.createElement('img');
                    logo.alt = '';
                    logo.className = 'q-logo';
                    logo.dataset.qPart = 'logo';
                    brand.prepend(logo);
                }

                if (logo) {
                    logo.src = this.pending.logo;
                }
            } else if (logo && !logo.getAttribute('src')) {
                logo.remove();
            }

            const slot = root.querySelector('.q-stamp-slot');

            if (slot) {
                this.overlay(slot, 'signature', 'q-sig-img');
                this.overlay(slot, 'stamp', 'q-stamp-img');
            }
        },

        overlay(slot, key, className) {
            const pending = this.pending[key];

            // Remove a previous local overlay. When a new file is selected,
            // also remove the server-rendered image for that same part; keeping
            // both makes the replacement appear to have been ignored.
            slot.querySelectorAll(`[data-q-overlay="${key}"]`).forEach((node) => node.remove());

            if (pending) {
                const serverSelector = key === 'stamp'
                    ? '[data-q-part="stamp"], .q-stamp, .q-stamp-empty'
                    : '[data-q-part="signature"], .q-sig-img';

                slot.querySelectorAll(serverSelector).forEach((node) => node.remove());

                const image = document.createElement('img');
                image.src = pending;
                image.alt = '';
                image.className = className;
                image.dataset.qOverlay = key;
                image.dataset.qPart = key === 'stamp' ? 'stamp' : 'signature';
                slot.appendChild(image);
            }
        },

        /* ---------------- metrics ---------------- */

        /** The sheet is paginated server-side: more than one .q-page is the
         *  only signal the editor needs that content will not fit one sheet. */
        measure() {
            const root = this.$refs.preview;

            this.overflow = Boolean(root && root.querySelectorAll('.q-page').length > 1);
        },

        fit() {
            const scroller = this.$refs.scroller;
            const slot = this.$refs.slot;
            const sheet = this.$refs.preview && this.$refs.preview.querySelector('.q-sheet');

            if (!scroller || !slot || !sheet) {
                return;
            }

            const available = scroller.clientWidth - 36;
            const scale = this.zoom === 'fit'
                ? Math.min(1, available / sheet.offsetWidth)
                : 1;

            sheet.style.transform = `scale(${scale})`;
            sheet.style.transformOrigin = 'top left';
            sheet.style.position = 'absolute';
            sheet.style.left = '0';
            sheet.style.top = '0';
            slot.style.width = `${sheet.offsetWidth * scale}px`;
            slot.style.height = `${sheet.offsetHeight * scale}px`;
        },

        setZoom(value) {
            this.zoom = value;
            this.$nextTick(() => this.fit());
        },

        /* ---------------- intro tokens ---------------- */

        insertToken(token, field) {
            const area = document.getElementById(field);

            if (!area) {
                return;
            }

            const start = area.selectionStart ?? area.value.length;
            const end = area.selectionEnd ?? start;

            area.value = area.value.slice(0, start) + token + area.value.slice(end);
            area.focus();
            area.selectionStart = area.selectionEnd = start + token.length;
            area.dispatchEvent(new Event('input', { bubbles: true }));
        },
    }));
    /**
     * Dashboard (docs/quoteflow_dashboard.html): quote list with a template
     * picker overlay and a transient toast. Filters themselves are
     * server-driven (the form GETs), so the URL stays shareable.
     */
    Alpine.data('dashboardPage', (seed) => ({
        search: seed?.search ?? '',
        status: seed?.status ?? '',
        date: seed?.date ?? '',
        templatePickerOpen: false,
        toastVisible: false,
        toastMessage: '',
        toastTimer: null,

        openTemplatePicker() {
            this.templatePickerOpen = true;
        },

        closeTemplatePicker() {
            this.templatePickerOpen = false;
        },

        /** Prototype showToast(): 2.6s auto-dismiss. */
        notify(message) {
            this.toastMessage = message;
            this.toastVisible = true;
            clearTimeout(this.toastTimer);
            this.toastTimer = setTimeout(() => {
                this.toastVisible = false;
            }, 2600);
        },
    }));
});

// Alpine must be started LAST so the components registered above are known.
Alpine.start();