<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id', 'code', 'name', 'type', 'sub_type',
        'parent_id', 'is_system', 'is_active', 'description',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Account $account) {
            if (empty($account->id)) {
                $account->id = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    // Type labels for display
    public const TYPE_LABELS = [
        'asset'     => 'Asset',
        'liability' => 'Liability',
        'equity'    => 'Equity',
        'income'    => 'Income',
        'expense'   => 'Expense',
    ];

    // Normal balance: asset/expense = debit, liability/equity/income = credit
    public function normalBalance(): string
    {
        return in_array($this->type, ['asset', 'expense']) ? 'debit' : 'credit';
    }

    public function parent(): BelongsTo  { return $this->belongsTo(Account::class, 'parent_id'); }
    public function children(): HasMany  { return $this->hasMany(Account::class, 'parent_id'); }
    public function lines(): HasMany     { return $this->hasMany(JournalLine::class); }

    public function balance(string $from = null, string $to = null): float
    {
        $q = $this->lines()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted');

        if ($from) $q->where('journal_entries.entry_date', '>=', $from);
        if ($to)   $q->where('journal_entries.entry_date', '<=', $to);

        $debit  = (float) $q->sum('journal_lines.debit');
        $credit = (float) $q->clone()->sum('journal_lines.credit');

        return $this->normalBalance() === 'debit'
            ? $debit - $credit
            : $credit - $debit;
    }
}
