<?php

namespace App\Models;

use App\Enums\TaskFrequency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Number;

class RecurringTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'client_id',
        'title',
        'description',
        'frequency',
        'next_due_at',
        'last_run_at',
        'started_at',
        'ends_at',
        'amount',
        'billing_notes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'frequency' => TaskFrequency::class,
            'next_due_at' => 'date',
            'last_run_at' => 'date',
            'started_at' => 'date',
            'ends_at' => 'date',
            'amount' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    /**
     * Global scope to ensure users only see their own recurring tasks.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(RecurringTaskLog::class);
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    // Scopes

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('active', false);
    }

    public function scopeDueSoon(Builder $query, int $days = 7): Builder
    {
        return $query
            ->active()
            ->where('next_due_at', '<=', now()->addDays($days))
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->active()
            ->where('next_due_at', '<', now()->startOfDay());
    }

    // Attributes

    public function getIsOverdueAttribute(): bool
    {
        return $this->active && $this->next_due_at < now()->startOfDay();
    }

    public function getIsDueSoonAttribute(): bool
    {
        $threshold = $this->frequency->daysBefore();

        return $this->active && $this->next_due_at <= now()->addDays($threshold);
    }

    public function getHasEndedAttribute(): bool
    {
        return $this->ends_at && $this->ends_at < now();
    }

    public function getFormattedAmountAttribute(): ?string
    {
        return $this->amount
            ? Number::currency($this->amount, 'EUR', 'de_DE')
            : null;
    }

    // Actions

    public function advance(): void
    {
        $this->update([
            'last_run_at' => $this->next_due_at,
            'next_due_at' => $this->frequency->nextDueDate($this->next_due_at),
        ]);

        // Deactivate if past end date
        if ($this->has_ended) {
            $this->update(['active' => false]);
        }
    }

    public function pause(): void
    {
        $this->update(['active' => false]);
    }

    public function resume(): void
    {
        // Adjust next_due_at if it's in the past
        $nextDue = $this->next_due_at;
        while ($nextDue < now()) {
            $nextDue = $this->frequency->nextDueDate($nextDue);
        }

        $this->update([
            'active' => true,
            'next_due_at' => $nextDue,
        ]);
    }
}
