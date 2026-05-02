<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends TenantAware
{
    protected $fillable = [
        'tenant_id', 'party_id', 'name', 'phone', 'cnic', 'address', 'notes',
        'current_balance', 'credit_limit', 'discount_percent', 'total_spend',
    ];

    protected $casts = [
        'current_balance'  => 'decimal:2',
        'credit_limit'     => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'total_spend'      => 'decimal:2',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'party_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CustomerLedgerEntry::class);
    }

    public function hasOutstandingBalance(): bool
    {
        return $this->current_balance > 0;
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'ilike', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}
