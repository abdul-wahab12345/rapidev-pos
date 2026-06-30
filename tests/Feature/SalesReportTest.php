<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SalesReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Branch $branch;
    private Product $product;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Tiles Co', 'subdomain' => 'tiles']);
        $this->user   = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);
        $this->actingAs($this->user);
        $this->branch  = Branch::create(['tenant_id' => $this->tenant->id, 'name' => 'Main']);
        $this->product = Product::create([
            'name' => 'Orient Tile', 'sku' => 'T1', 'unit' => 'sq_m',
            'cost_price' => 100, 'selling_price' => 200, 'reorder_level' => 5,
            'has_variants' => false, 'is_active' => true,
        ]);
    }

    private function makeSale(float $total, string $date, string $status = 'completed', float $qty = 1): Sale
    {
        $sale = Sale::create([
            'branch_id' => $this->branch->id, 'user_id' => $this->user->id,
            'invoice_number' => 'INV-' . (++$this->seq), 'status' => $status,
            'subtotal' => $total, 'total' => $total, 'paid' => $total, 'payment_method' => 'cash',
        ]);
        $sale->forceFill(['created_at' => $date])->save();
        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $this->product->id, 'product_name' => $this->product->name,
            'quantity' => $qty, 'unit_price' => $total, 'cost_price' => 100, 'line_total' => $total,
        ]);
        return $sale;
    }

    private function makeReturn(Sale $sale, float $refund, string $returnDate): void
    {
        SaleReturn::create([
            'branch_id' => $this->branch->id, 'sale_id' => $sale->id, 'created_by' => $this->user->id,
            'return_number' => 'RET-' . (++$this->seq), 'return_date' => $returnDate,
            'refund_method' => 'cash', 'total_refund' => $refund,
        ]);
    }

    public function test_summary_is_gross_by_sale_date_with_returns_as_memo_only(): void
    {
        $from = now()->startOfMonth()->format('Y-m-d');
        $to   = now()->format('Y-m-d');

        $this->makeSale(5000, now()->startOfMonth()->addDay()->format('Y-m-d'));      // this period
        $old = $this->makeSale(8000, now()->subMonth()->format('Y-m-d'));             // last period
        $this->makeReturn($old, 8000, now()->startOfMonth()->addDays(2)->format('Y-m-d')); // returned THIS period

        $summary = SalesReportService::summary($from, $to);

        // Gross counts only this period's sales — the old sale is NOT here
        $this->assertEquals(5000, $summary['gross_sales']);
        $this->assertEquals(1, $summary['count']);

        // The return shows as a memo (by return date), NOT subtracted from gross
        $this->assertEquals(8000, $summary['returns_in_period']);
    }

    public function test_voided_sales_are_excluded(): void
    {
        $from = now()->startOfMonth()->format('Y-m-d');
        $to   = now()->format('Y-m-d');

        $this->makeSale(5000, now()->startOfMonth()->addDay()->format('Y-m-d'), 'completed');
        $this->makeSale(3000, now()->startOfMonth()->addDay()->format('Y-m-d'), 'voided');

        $summary = SalesReportService::summary($from, $to);
        $this->assertEquals(5000, $summary['gross_sales']);
        $this->assertEquals(1, $summary['count']);
    }

    public function test_returns_report_flags_prior_period_returns(): void
    {
        $from = now()->startOfMonth()->format('Y-m-d');
        $to   = now()->format('Y-m-d');

        $old  = $this->makeSale(8000, now()->subMonth()->format('Y-m-d'));
        $this->makeReturn($old, 8000, now()->startOfMonth()->addDays(2)->format('Y-m-d'));

        $recent = $this->makeSale(4000, now()->startOfMonth()->addDay()->format('Y-m-d'));
        $this->makeReturn($recent, 4000, now()->startOfMonth()->addDays(3)->format('Y-m-d'));

        $report = SalesReportService::returns($from, $to, null);

        $this->assertEquals(2, $report['count']);
        $this->assertEquals(12000, $report['total']);

        $rows = collect($report['rows']);
        $priorRow  = $rows->firstWhere('total_refund', 8000.0);
        $recentRow = $rows->firstWhere('total_refund', 4000.0);

        $this->assertTrue($priorRow['prior_period']);   // sale was last month
        $this->assertFalse($recentRow['prior_period']);  // sale was this month
        $this->assertEquals($old->invoice_number, $priorRow['invoice_number']);
    }

    public function test_by_party_groups_customers_and_cashiers_without_ambiguous_columns(): void
    {
        $from = now()->startOfMonth()->format('Y-m-d');
        $to   = now()->format('Y-m-d');

        $this->makeSale(5000, now()->startOfMonth()->addDay()->format('Y-m-d')); // walk-in
        $this->makeSale(3000, now()->startOfMonth()->addDay()->format('Y-m-d'));

        $party = SalesReportService::byParty($from, $to);

        // Walk-in customer bucket totals both sales; cashier bucket has the owner
        $walkIn = collect($party['customers'])->firstWhere('name', 'Walk-in');
        $this->assertEquals(8000, $walkIn['amount']);
        $this->assertEquals(2, $walkIn['count']);

        $cashier = collect($party['cashiers'])->firstWhere('name', $this->user->name);
        $this->assertEquals(8000, $cashier['amount']);
    }

    public function test_by_product_aggregates_gross_quantity_and_revenue(): void
    {
        $from = now()->startOfMonth()->format('Y-m-d');
        $to   = now()->format('Y-m-d');

        $this->makeSale(2000, now()->startOfMonth()->addDay()->format('Y-m-d'), 'completed', 10);
        $this->makeSale(800,  now()->startOfMonth()->addDay()->format('Y-m-d'), 'completed', 4);

        $rows = SalesReportService::byProduct($from, $to);
        $row = collect($rows)->firstWhere('product_id', $this->product->id);

        $this->assertEquals(14, $row['qty']);
        $this->assertEquals(2800, $row['revenue']);
        $this->assertEquals(2, $row['invoices']);
    }
}
