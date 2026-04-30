<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'journal_entry_id', 'account_id', 'debit', 'credit', 'description',
    ];

    protected $casts = [
        'debit'  => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (JournalLine $line) {
            if (empty($line->id)) {
                $line->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    public function entry(): BelongsTo   { return $this->belongsTo(JournalEntry::class, 'journal_entry_id'); }
    public function account(): BelongsTo { return $this->belongsTo(Account::class); }
}
