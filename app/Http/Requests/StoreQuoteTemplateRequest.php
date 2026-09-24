<?php

namespace App\Http\Requests;

use App\Enums\AccentPalette;
use App\Enums\HeaderAlignment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuoteTemplateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['nullable', 'boolean'],

            // Branding
            'accent_color' => ['required', Rule::enum(AccentPalette::class)],
            'header_alignment' => ['required', Rule::enum(HeaderAlignment::class)],
            'letterhead_display_name' => ['nullable', 'string', 'max:255'],
            'doc_title' => ['required', 'string', 'max:100'],
            'company_name' => ['required', 'string', 'max:255'],
            'company_gstin' => ['nullable', 'string', 'size:15', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'mobile_1' => ['nullable', 'string', 'max:32'],
            'mobile_2' => ['nullable', 'string', 'max:32'],
            'stamp_place' => ['nullable', 'string', 'max:100'],

            // Uploads: MIME + extension + size (rules.md section 9, FR-13)
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'signature' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_signature' => ['nullable', 'boolean'],

            'authorized_person' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],

            // Quote defaults
            'default_gst_rate' => ['required', 'numeric', Rule::in([0, 5, 12, 18, 28])],
            'intro_message' => ['nullable', 'string', 'max:2000'],
            'delivery_period' => ['nullable', 'string', 'max:255'],
            'warranty' => ['nullable', 'string', 'max:255'],
            'validity_text' => ['nullable', 'string', 'max:255'],
            'extra_terms' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Normalise input before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => filled($this->email) ? strtolower(trim($this->email)) : null,
            'company_gstin' => filled($this->company_gstin) ? strtoupper(trim($this->company_gstin)) : null,
            'is_default' => $this->boolean('is_default'),
            'remove_logo' => $this->boolean('remove_logo'),
            'remove_signature' => $this->boolean('remove_signature'),
        ]);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_gstin.regex' => 'Enter a valid 15-character GSTIN, for example 22AAAAA0000A1Z5.',
            'company_gstin.size' => 'GSTIN must be exactly 15 characters.',
            'default_gst_rate.in' => 'GST rate must be 0, 5, 12, 18 or 28.',
            'logo.max' => 'The logo must be 2 MB or smaller.',
            'signature.max' => 'The signature image must be 2 MB or smaller.',
        ];
    }
}
