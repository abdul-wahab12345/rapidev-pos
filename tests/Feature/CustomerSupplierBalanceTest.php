<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DefaultChartOfAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the customer/supplier opening-balance + charge + write-off flow and,
 * crucially, that every balance change posts a balanced journal entry so the GL
 * control accounts (AR 1030 / AP 2010) reconcile with the subsidiary balances.
 */
class CustomerSupplierBalanceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Test Tiles Co', 'subdomain' => 'test-tiles']);
        $this->user   = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);
        DefaultChartOfAccounts::seedForTenant($this->tenant->id);
        $this->actingAs($this->user);
    }

    /** GL balance for an account code (debit − credit) over posted lines. */
    private function glBalance(string $code): float
    {
        $accountId = Account::where('tenant_id', $this->tenant->id)->where('code', $code)->value('id');
        if (! $accountId) {
            return 0.0;
        }
        $q = JournalLine::where('account_id', $accountId)
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.status', 'posted');

        return (float) (clone $q)->sum('journal_lines.debit') - (float) (clone $q)->sum('journal_lines.credit');
    }

    /** Fetch the journal entry for a reference and assert it balances (Σ debit == Σ credit). */
    private function balancedEntryFor(string $refType, string $refId): JournalEntry
    {
        $entry = JournalEntry::where('reference_type', $refType)
            ->where('reference_id', $refId)
            ->with('lines')
            ->firstOrFail();

        $this->assertEqualsWithDelta(
            (float) $entry->lines->sum('debit'),
            (float) $entry->lines->sum('credit'),
            0.01,
            "Journal entry {$refType} is not balanced",
        );

        return $entry;
    }

    private function accountId(string $code): string
    {
        return Account::where('tenant_id', $this->tenant->id)->where('code', $code)->value('id');
    }

    public function test_customer_opening_balance_records_ledger_and_posts_dr_ar_cr_opening_equity(): void
    {
        $this->post(route('customers.store'), [
            'name' => 'Ahmed', 'phone' => '03001234567', 'opening_balance' => 5000,
        ])->assertRedirect();

        $customer = Customer::where('name', 'Ahmed')->firstOrFail();
        $this->assertEquals(5000, (float) $customer->current_balance);

        $ledger = CustomerLedgerEntry::where('customer_id', $customer->id)->where('type', 'opening')->firstOrFail();
        $this->assertEquals(5000, (float) $ledger->amount);
        $this->assertEquals(5000, (float) $ledger->running_balance);

        $entry = $this->balancedEntryFor('customer_opening', $customer->id);
        $this->assertEquals(5000, (float) $entry->lines->firstWhere('account_id', $this->accountId('1030'))->debit);
        $this->assertEquals(5000, (float) $entry->lines->firstWhere('account_id', $this->accountId('3040'))->credit);
    }

    public function test_charge_increases_udhaar_and_posts_dr_ar_cr_other_income(): void
    {
        $this->post(route('customers.store'), ['name' => 'Bilal', 'phone' => '03009999999']);
        $customer = Customer::where('name', 'Bilal')->firstOrFail();

        $this->post(route('customers.charge', $customer), ['amount' => 2000, 'reason' => 'old unbilled tiles'])
            ->assertRedirect();

        $this->assertEquals(2000, (float) $customer->fresh()->current_balance);
        $this->assertTrue(CustomerLedgerEntry::where('customer_id', $customer->id)->where('type', 'charge')->exists());

        $entry = $this->balancedEntryFor('customer_charge', $customer->id);
        $this->assertEquals(2000, (float) $entry->lines->sum('debit'));
        $this->assertEquals(2000, (float) $entry->lines->firstWhere('account_id', $this->accountId('4020'))->credit);
    }

    public function test_write_off_reduces_udhaar_and_posts_dr_bad_debt_cr_ar(): void
    {
        $this->post(route('customers.store'), ['name' => 'Chand', 'phone' => '03007777777', 'opening_balance' => 3000]);
        $customer = Customer::where('name', 'Chand')->firstOrFail();

        $this->post(route('customers.write-off', $customer), ['amount' => 1000, 'reason' => 'uncollectable'])
            ->assertRedirect();

        $this->assertEquals(2000, (float) $customer->fresh()->current_balance);
        $ledger = CustomerLedgerEntry::where('customer_id', $customer->id)->where('type', 'writeoff')->firstOrFail();
        $this->assertEquals(-1000, (float) $ledger->amount);

        $entry = $this->balancedEntryFor('customer_writeoff', $customer->id);
        $this->assertEquals(1000, (float) $entry->lines->firstWhere('account_id', $this->accountId('5110'))->debit);
        $this->assertEquals(1000, (float) $entry->lines->firstWhere('account_id', $this->accountId('1030'))->credit);
    }

    public function test_write_off_larger_than_balance_is_rejected(): void
    {
        $this->post(route('customers.store'), ['name' => 'Dawood', 'phone' => '03006666666', 'opening_balance' => 500]);
        $customer = Customer::where('name', 'Dawood')->firstOrFail();

        $this->post(route('customers.write-off', $customer), ['amount' => 5000])
            ->assertSessionHas('error');

        $this->assertEquals(500, (float) $customer->fresh()->current_balance); // unchanged
    }

    public function test_gl_accounts_receivable_reconciles_with_sum_of_customer_balances(): void
    {
        $this->post(route('customers.store'), ['name' => 'Ali',  'phone' => '03001', 'opening_balance' => 5000]);
        $this->post(route('customers.store'), ['name' => 'Sara', 'phone' => '03002', 'opening_balance' => 3000]);

        $ali = Customer::where('name', 'Ali')->firstOrFail();
        $this->post(route('customers.charge', $ali), ['amount' => 2000]);      // +2000
        $this->post(route('customers.write-off', $ali), ['amount' => 1000]);   // -1000

        // Subsidiary: 5000 + 2000 - 1000 + 3000 = 9000
        $sumBalances = (float) Customer::sum('current_balance');
        $this->assertEquals(9000, $sumBalances);

        // GL control account 1030 (debit-normal asset) must match exactly
        $this->assertEqualsWithDelta($sumBalances, $this->glBalance('1030'), 0.01);
    }

    public function test_supplier_opening_balance_posts_dr_opening_equity_cr_ap(): void
    {
        $this->post(route('purchasing.suppliers.store'), [
            'name' => 'Marble Importer', 'opening_balance' => 8000, 'payment_terms' => 30,
        ])->assertRedirect();

        $supplier = Supplier::where('name', 'Marble Importer')->firstOrFail();
        $this->assertEquals(8000, (float) $supplier->current_balance);

        $entry = $this->balancedEntryFor('supplier_opening', $supplier->id);
        $this->assertEquals(8000, (float) $entry->lines->firstWhere('account_id', $this->accountId('2010'))->credit);
        $this->assertEquals(8000, (float) $entry->lines->firstWhere('account_id', $this->accountId('3040'))->debit);

        // AP control (2010, credit-normal) reflects what we owe
        $this->assertEqualsWithDelta(8000, -$this->glBalance('2010'), 0.01);
    }
}
