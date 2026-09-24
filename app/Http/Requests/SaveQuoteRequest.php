<?php

namespace App\Http\Requests;

use App\Services\CalculationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveQuoteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Note: no totals are validated because totals are never accepted from the
     * browser (BR-02). The backend recomputes everything.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'template_id' => ['required', Rule::exists('quote_templates', 'id')->whereNull('deleted_at')],
            'client_id' => ['required', Rule::exists('clients', 'id')->whereNull('deleted_at')],

            'quote_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:quote_date'],

            // Optional (decision #9); both must be supplied together to be useful.
            'enquiry_no' => ['nullable', 'string', 'max:100'],
            'enquiry_date' => ['nullable', 'date'],

            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'gst_rate' => ['required', 'numeric', Rule::in(CalculationService::GST_RATES)],

            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:1000'],
            'items.*.qty' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'items.*.rate' => ['required', 'numeric', 'min:0', 'max:9999999999'],

            'terms.intro' => ['nullable', 'string', 'max:2000'],
            'terms.delivery' => ['nullable', 'string', 'max:255'],
            'terms.warranty' => ['nullable', 'string', 'max:255'],
            'terms.validity' => ['nullable', 'string', 'max:255'],
            'terms.extra' => ['nullable', 'string', 'max:2000'],
            'terms.notes' => ['nullable', 'string', 'max:2000'],

            'intent' => ['nullable', 'in:draft,generate'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Add at least one line item to the quotation.',
            'items.*.description.required' => 'Each line item needs a description.',
            'items.*.qty.gt' => 'Quantity must be greater than zero.',
            'gst_rate.in' => 'GST rate must be 0, 5, 12, 18 or 28.',
            'valid_until.after_or_equal' => 'The valid-until date cannot be before the quotation date.',
        ];
    }

    /**
     * Line items as a clean array for the calculation service.
     *
     * @return array<int, array{description: string, qty: mixed, rate: mixed}>
     */
    public function lineItems(): array
    {
        return array_map(fn (array $item): array => [
            'description' => $item['description'] ?? '',
            'qty' => $item['qty'] ?? 0,
            'rate' => $item['rate'] ?? 0,
        ], $this->validated('items'));
    }
}
