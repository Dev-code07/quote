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
    Alpine.data('quoteBuilder', (seed) => ({
        templateId: seed.templateId ?? '',
        clientId: seed.clientId ?? '',
        gstRate: Number(seed.gstRate ?? 18),
        discount: Number(seed.discount ?? 0),
        dirty: false,

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
    }));
});

// Alpine must be started LAST so the components registered above are known.
Alpine.start();