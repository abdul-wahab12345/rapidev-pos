<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $fillable = [
        'name', 'name_ur', 'province', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /** Urdu when $language is `ur` and `name_ur` is set; otherwise English `name`. */
    public function localizedName(?string $language = null): string
    {
        $language ??= 'en';

        return $language === 'ur' && $this->name_ur !== null && $this->name_ur !== ''
            ? $this->name_ur
            : $this->name;
    }

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
