<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class RateList extends TenantAware
{
    protected $fillable = [
        'tenant_id',
        'name',
        'name_ur',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(RateListItem::class);
    }

    /** Deactivate all other rate lists for this tenant, then activate this one. */
    public function activate(): void
    {
        static::where('tenant_id', $this->tenant_id)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        $this->update(['is_active' => true]);
    }

    /** Deactivate this rate list. */
    public function deactivate(): void
    {
        $this->update(['is_active' => false]);
    }
}
