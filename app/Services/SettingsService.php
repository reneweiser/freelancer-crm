<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    public function __construct(
        protected User $user
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = $this->getCacheKey($key);

        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = $this->user->settings()->where('key', $key)->first();

            return $setting?->value ?? $default;
        });
    }

    public function set(string $key, mixed $value): void
    {
        $this->user->settings()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forget($this->getCacheKey($key));
    }

    /**
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->user->settings()
            ->pluck('value', 'key')
            ->toArray();
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function setMany(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }
    }

    protected function getCacheKey(string $key): string
    {
        return "settings.{$this->user->id}.{$key}";
    }
}
