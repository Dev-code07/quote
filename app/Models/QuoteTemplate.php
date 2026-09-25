<?php

namespace App\Models;

use App\Enums\AccentPalette;
use App\Enums\HeaderAlignment;
use Database\Factories\QuoteTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name', 'is_default', 'accent_color', 'header_alignment',
    'letterhead_display_name', 'doc_title', 'company_name', 'company_gstin',
    'tagline', 'address', 'email', 'mobile_1', 'mobile_2', 'stamp_place',
    'logo_path', 'signature_path', 'company_stamp_path', 'use_generated_seal',
    'authorized_person', 'designation',
    'default_gst_rate', 'intro_message', 'delivery_period', 'warranty',
    'validity_text', 'extra_terms', 'notes', 'last_used_at', 'created_by',
])]
class QuoteTemplate extends Model
{
    /** @use HasFactory<QuoteTemplateFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'use_generated_seal' => 'boolean',
            'accent_color' => AccentPalette::class,
            'header_alignment' => HeaderAlignment::class,
            'default_gst_rate' => 'decimal:2',
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * The user who created this template (audit trail).
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Quotes created from this template. Quotes keep their own snapshot, so
     * deleting a template never breaks a quote (BR-01).
     */
    public function quotes(): HasMany
    {
        // Explicit FK: Laravel would otherwise infer quote_template_id.
        return $this->hasMany(Quote::class, 'template_id');
    }

    /**
     * @param  Builder<QuoteTemplate>  $query
     */
    public function scopeDefault(Builder $query): void
    {
        $query->where('is_default', true);
    }

    /**
     * @param  Builder<QuoteTemplate>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('name', 'like', $like)
                ->orWhere('company_name', 'like', $like)
                ->orWhere('doc_title', 'like', $like);
        });
    }

    /**
     * Name printed on the letterhead; falls back to the company name.
     */
    public function displayName(): string
    {
        return $this->letterhead_display_name ?: ($this->company_name ?? '');
    }

    /**
     * Public URL for the uploaded logo, or null.
     */
    public function logoUrl(): ?string
    {
        return $this->assetUrl($this->logo_path);
    }

    /**
     * Public URL for the uploaded signature, or null.
     */
    public function signatureUrl(): ?string
    {
        return $this->assetUrl($this->signature_path);
    }

    /**
     * URL of the uploaded company stamp, if any.
     */
    public function companyStampUrl(): ?string
    {
        return $this->assetUrl($this->company_stamp_path);
    }

    /**
     * Build an asset URL against the current application host.
     *
     * Storage::url() uses APP_URL, which is commonly left as http://localhost
     * while local development is served on 127.0.0.1:8000. The latter must not
     * silently send image requests to Apache/WAMP on port 80.
     */
    private function assetUrl(?string $path): ?string
    {
        return $path ? asset('storage/'.ltrim($path, '/')) : null;
    }

    /**
     * Quoted value: the four A4 CSS custom properties for this palette.
     *
     * @return array{ink: string, ink2: string, soft: string, line: string}
     */
    public function inkColours(): array
    {
        return ($this->accent_color ?? AccentPalette::default())->colours();
    }
}
