<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $fillable = [
        'name', 'province', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    /** Areas visible to current tenant only (areas are tenant-scoped). */
    public function areasForTenant(string $tenantId): HasMany
    {
        return $this->areas()->where('tenant_id', $tenantId);
    }
}
