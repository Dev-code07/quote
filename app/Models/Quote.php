<?php

namespace App\Models;

use App\Enums\QuoteStatus;
use Database\Factories\QuoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'quote_number', 'quote_date', 'valid_until', 'enquiry_no', 'enquiry_date',
    'status', 'client_id', 'template_id', 'client_snapshot',
    'template_snapshot', 'terms', 'subtotal', 'discount_amount', 'gst_rate',
    'gst_amount', 'grand_total', 'amount_in_words', 'created_by',
])]
class Quote extends Model
{
    /** @use HasFactory<QuoteFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'quote_date' => 'date',
            'valid_until' => 'date',
            'enquiry_date' => 'date',
            'client_snapshot' => 'array',
            'template_snapshot' => 'array',
            'terms' => 'array',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'gst_rate' => 'decimal:2',
            'gst_amount' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(QuoteTemplate::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position');
    }

    /**
     * @param  Builder<Quote>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('quote_number', 'like', $like)
                ->orWhere('client_snapshot->name', 'like', $like);
        });
    }

    /**
     * Client name from the snapshot, falling back to the live relation.
     */
    public function clientName(): string
    {
        return $this->client_snapshot['name'] ?? $this->client?->name ?? '—';
    }

    /**
     * Company name from the template snapshot.
     */
    public function companyName(): string
    {
        return $this->template_snapshot['name'] ?? '—';
    }

    /**
     * Whether validity has passed and the system may expire this quote.
     */
    public function isPastValidity(): bool
    {
        return $this->valid_until !== null
            && $this->valid_until->isPast()
            && in_array($this->status, [QuoteStatus::Draft, QuoteStatus::Sent], true);
    }
}
