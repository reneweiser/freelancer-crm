<?php

namespace App\Models;

use App\Enums\EmailLogStatus;
use App\Enums\EmailLogType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'emailable_type',
        'emailable_id',
        'type',
        'recipient_email',
        'recipient_name',
        'subject',
        'body',
        'has_attachment',
        'attachment_filename',
        'status',
        'sent_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'type' => EmailLogType::class,
            'status' => EmailLogStatus::class,
            'has_attachment' => 'boolean',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * Global scope to ensure users only see their own email logs.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('user', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function emailable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope for failed emails.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', EmailLogStatus::Failed);
    }

    /**
     * Scope for sent emails.
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', EmailLogStatus::Sent);
    }

    /**
     * Scope for queued emails.
     */
    public function scopeQueued(Builder $query): Builder
    {
        return $query->where('status', EmailLogStatus::Queued);
    }

    /**
     * Mark this email as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => EmailLogStatus::Sent,
            'sent_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark this email as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => EmailLogStatus::Failed,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Reset to queued status for retry.
     */
    public function resetForRetry(): void
    {
        $this->update([
            'status' => EmailLogStatus::Queued,
            'error_message' => null,
        ]);
    }
}
