<?php

namespace App\Models;

use App\Enums\ClientType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'company_name',
        'vat_id',
        'contact_name',
        'email',
        'phone',
        'street',
        'postal_code',
        'city',
        'country',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => ClientType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function reminders(): MorphMany
    {
        return $this->morphMany(Reminder::class, 'remindable');
    }

    public function recurringTasks(): HasMany
    {
        return $this->hasMany(RecurringTask::class);
    }

    /**
     * Get display name (company name or contact name for individuals).
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->type === ClientType::Company && $this->company_name) {
            return $this->company_name;
        }

        return $this->contact_name ?: "Kunde #{$this->id}";
    }

    /**
     * Get full address as a single string.
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->street,
            trim($this->postal_code.' '.$this->city),
            $this->country !== 'DE' ? $this->country : null,
        ]);

        return implode(', ', $parts);
    }
}
