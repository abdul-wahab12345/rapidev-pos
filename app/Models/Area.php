<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Area extends Model
{
    protected $fillable = [
        'tenant_id', 'city_id', 'name',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if (($user = auth()->user()) && isset($user->tenant_id) && $user->tenant_id) {
                $query->where($query->getModel()->getTable() . '.tenant_id', $user->tenant_id);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
