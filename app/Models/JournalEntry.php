<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id', 'entry_number', 'entry_date', 'description',
        'reference_type', 'reference_id', 'status', 'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (JournalEntry $entry) {
            if (empty($entry->id)) {
                $entry->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public static function nextNumber(string $tenantId): string
    {
        $last = static::where('tenant_id', $tenantId)
            ->withTrashed()
            ->orderByDesc('entry_number')
            ->value('entry_number');

        $n = $last ? ((int) substr($last, 3)) + 1 : 1;
        return 'JE-' . str_pad($n, 5, '0', STR_PAD_LEFT);
    }

    public function lines(): HasMany    { return $this->hasMany(JournalLine::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function totalDebit(): float
    {
        return (float) $this->lines->sum('debit');
    }

    public function totalCredit(): float
    {
        return (float) $this->lines->sum('credit');
    }

    public function isBalanced(): bool
    {
        return abs($this->totalDebit() - $this->totalCredit()) < 0.01;
    }
}
