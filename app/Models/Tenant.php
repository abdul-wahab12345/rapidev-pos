<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'name', 'subdomain', 'plan', 'status', 'trial_ends_at', 'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (empty($tenant->id)) {
                $tenant->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function defaultBranch()
    {
        return $this->branches()->where('is_default', true)->first();
    }

    // Helpers to read settings
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function getBusinessName(): string
    {
        return $this->getSetting('business_name', $this->name);
    }

    public function getCurrency(): string
    {
        return $this->getSetting('currency', 'PKR');
    }

    public function getLanguage(): string
    {
        return $this->getSetting('language', 'en');
    }

    public function isOnTrial(): bool
    {
        return $this->plan === 'trial' && $this->trial_ends_at?->isFuture();
    }
}
