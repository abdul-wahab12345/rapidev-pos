<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockAdjustment;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StockReportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Branch $branch;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::create(['name' => 'Tiles Co', 'subdomain' => 'tiles']);
        $this->user   = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);
        $this->actingAs($this->user);

        $this->branch  = Branch::create(['tenant_id' => $this->tenant->id, 'name' => 'Main']);
        $this->product = Product::create([
            'name' => 'Orient Tile', 'sku' => 'TILE-1', 'unit' => 'sq_m',
            'cost_price' => 100, 'selling_price' => 200, 'reorder_level' => 5,
            'has_variants' => false, 'is_active' => true,
        ]);

        // Current stock-on-hand = 105
        StockLevel::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
            'product_id' => $this->product->id, 'quantity' => 105,
        ]);
    }

    private function makeSale(float $qty, string $date, string $invoice): void
    {
        $sale = Sale::create([
            'branch_id' => $this->branch->id, 'user_id' => $this->user->id,
            'invoice_number' => $invoice, 'status' => 'completed',
            'subtotal' => 200 * $qty, 'total' => 200 * $qty, 'paid' => 200 * $qty,
            'payment_method' => 'cash',
        ]);
        $sale->forceFill(['created_at' => $date])->save();

        SaleItem::create([
            'sale_id' => $sale->id, 'product_id' => $this->product->id,
            'product_name' => $this->product->name, 'quantity' => $qty,
            'unit_price' => 200, 'cost_price' => 100, 'line_total' => 200 * $qty,
        ]);
    }

    private function makeAdjustment(float $change, string $reason, string $date): void
    {
        $adj = StockAdjustment::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
            'product_id' => $this->product->id, 'user_id' => $this->user->id,
            'quantity_before' => 0, 'quantity_change' => $change, 'quantity_after' => 0,
            'reason' => $reason,
        ]);
        $adj->forceFill(['created_at' => $date])->save();
    }

    public function test_ledger_captures_sales_and_adjustments_and_reconciles_to_current_stock(): void
    {
        $from = now()->startOfMonth()->format('Y-m-d');
        $to   = now()->format('Y-m-d');
        $mid  = now()->startOfMonth()->addDays(2)->format('Y-m-d');

        $this->makeSale(10, $mid, 'INV-1');             // OUT 10
        $this->makeAdjustment(-5, 'damage', $mid);       // OUT 5

        $ledger = StockReportService::productLedger($this->product, $from, $to);

        // Two movements captured
        $this->assertCount(2, $ledger['rows']);
        $types = array_column($ledger['rows'], 'type');
        $this->assertContains('sale', $types);
        $this->assertContains('adjustment', $types);

        // Totals
        $this->assertEquals(0, $ledger['total_in']);
        $this->assertEquals(15, $ledger['total_out']);

        // Anchored to current stock: closing == 105, opening == 105 + 15 = 120
        $this->assertEquals(105, $ledger['closing']);
        $this->assertEquals(105, $ledger['current_qty']);
        $this->assertEquals(120, $ledger['opening']);

        // Running balance ends exactly at closing (the reconciliation invariant)
        $lastBalance = end($ledger['rows'])['balance'];
        $this->assertEquals($ledger['closing'], $lastBalance);

        // opening + in - out == closing
        $this->assertEquals(
            $ledger['closing'],
            round($ledger['opening'] + $ledger['total_in'] - $ledger['total_out'], 2),
        );
    }

    public function test_date_range_excludes_movements_outside_the_window(): void
    {
        $lastMonth = now()->subMonth()->format('Y-m-d');
        $thisMonth = now()->startOfMonth()->addDay()->format('Y-m-d');

        $this->makeSale(10, $lastMonth, 'INV-OLD');   // outside range
        $this->makeSale(4,  $thisMonth, 'INV-NEW');   // inside range

        $ledger = StockReportService::productLedger(
            $this->product,
            now()->startOfMonth()->format('Y-m-d'),
            now()->format('Y-m-d'),
        );

        $refs = array_column($ledger['rows'], 'reference');
        $this->assertContains('INV-NEW', $refs);
        $this->assertNotContains('INV-OLD', $refs);
        $this->assertEquals(4, $ledger['total_out']);
    }

    public function test_snapshot_flags_products_manually_adjusted_in_range(): void
    {
        $this->makeAdjustment(-3, 'theft', now()->startOfMonth()->addDay()->format('Y-m-d'));

        $rows = StockReportService::snapshot(
            null,
            now()->startOfMonth()->format('Y-m-d'),
            now()->format('Y-m-d'),
            null,
        );

        $row = $rows->firstWhere('id', $this->product->id);
        $this->assertNotNull($row);
        $this->assertTrue($row['flagged']);
        $this->assertEquals(1, $row['manual_adjustments']);
        $this->assertEquals(105, $row['quantity']);
    }
}
