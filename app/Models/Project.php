<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'client_id',
        'title',
        'description',
        'reference',
        'type',
        'hourly_rate',
        'fixed_price',
        'status',
        'offer_date',
        'offer_valid_until',
        'offer_sent_at',
        'offer_accepted_at',
        'start_date',
        'end_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProjectType::class,
            'status' => ProjectStatus::class,
            'hourly_rate' => 'decimal:2',
            'fixed_price' => 'decimal:2',
            'offer_date' => 'date',
            'offer_valid_until' => 'date',
            'offer_sent_at' => 'datetime',
            'offer_accepted_at' => 'datetime',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProjectItem::class)->orderBy('position');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get total value from items or fixed price.
     */
    public function getTotalValueAttribute(): float
    {
        if ($this->type === ProjectType::Fixed && $this->fixed_price) {
            return (float) $this->fixed_price;
        }

        return $this->items->sum(fn ($item) => $item->quantity * $item->unit_price);
    }

    /**
     * Scope for active projects (in progress).
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            ProjectStatus::Accepted,
            ProjectStatus::InProgress,
        ]);
    }

    /**
     * Scope for offer stage projects.
     */
    public function scopeOffers($query)
    {
        return $query->whereIn('status', [
            ProjectStatus::Draft,
            ProjectStatus::Sent,
        ]);
    }

    /**
     * Scope by status.
     */
    public function scopeByStatus($query, ProjectStatus $status)
    {
        return $query->where('status', $status);
    }
}
