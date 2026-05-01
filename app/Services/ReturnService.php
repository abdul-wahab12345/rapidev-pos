<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;

class ReturnService
{
    /**
     * Process a return for a sale (full or partial).
     * Handles: stock restoration, customer balance, sale status update.
     * Caller is responsible for posting the accounting entry afterwards.
     */
    public static function process(Sale $sale, array $data, int $userId): SaleReturn
    {
        return DB::transaction(function () use ($sale, $data, $userId) {

            // --- Calculate totals and validate quantities ---
            $alreadyReturned = self::returnedQtyBySaleItem($sale->id);
            $totalRefund     = 0;
            $preparedItems   = [];

            foreach ($data['items'] as $d) {
                $qty = (int) $d['quantity_returned'];
                if ($qty <= 0) continue;

                $saleItem = SaleItem::findOrFail($d['sale_item_id']);

                $maxReturnable = $saleItem->quantity - ($alreadyReturned[$saleItem->id] ?? 0);
                if ($qty > $maxReturnable) {
                    throw new \InvalidArgumentException(
                        "Cannot return {$qty} of '{$saleItem->product_name}' — only {$maxReturnable} returnable."
                    );
                }

                $lineTotal    = $saleItem->unit_price * $qty;
                $totalRefund += $lineTotal;

                $preparedItems[] = [
                    'saleItem'  => $saleItem,
                    'qty'       => $qty,
                    'lineTotal' => $lineTotal,
                    'restock'   => (bool) ($d['restock'] ?? true),
                ];
            }

            if (empty($preparedItems)) {
                throw new \InvalidArgumentException('No items selected for return.');
            }

            // --- Create the return record ---
            $return = SaleReturn::create([
                'tenant_id'     => $sale->tenant_id,
                'branch_id'     => $sale->branch_id,
                'sale_id'       => $sale->id,
                'created_by'    => $userId,
                'return_number' => SaleReturn::nextNumber($sale->tenant_id),
                'return_date'   => now()->toDateString(),
                'reason'        => $data['reason'] ?? null,
                'refund_method' => $data['refund_method'],
                'total_refund'  => $totalRefund,
                'notes'         => $data['notes'] ?? null,
                'status'        => 'completed',
            ]);

            // --- Create return items + restore stock ---
            foreach ($preparedItems as $d) {
                SaleReturnItem::create([
                    'sale_return_id'    => $return->id,
                    'sale_item_id'      => $d['saleItem']->id,
                    'product_id'        => $d['saleItem']->product_id,
                    'variant_id'        => $d['saleItem']->variant_id,
                    'product_name'      => $d['saleItem']->product_name,
                    'variant_label'     => $d['saleItem']->variant_label,
                    'quantity_returned' => $d['qty'],
                    'unit_price'        => $d['saleItem']->unit_price,
                    'line_total'        => $d['lineTotal'],
                    'restock'           => $d['restock'],
                ]);

                if ($d['restock']) {
                    StockLevel::where('product_id', $d['saleItem']->product_id)
                        ->when($d['saleItem']->variant_id, fn ($q) => $q->where('variant_id', $d['saleItem']->variant_id))
                        ->increment('quantity', $d['qty']);
                }
            }

            // --- Customer balance update for store_credit ---
            if ($sale->customer_id && $data['refund_method'] === 'store_credit') {
                $currentBalance = (float) DB::table('customers')
                    ->where('id', $sale->customer_id)
                    ->value('current_balance') ?? 0;

                $newBalance = max(0.0, $currentBalance - $totalRefund);

                DB::table('customers')
                    ->where('id', $sale->customer_id)
                    ->update(['current_balance' => $newBalance]);

                CustomerLedgerEntry::create([
                    'tenant_id'       => $sale->tenant_id,
                    'customer_id'     => $sale->customer_id,
                    'sale_id'         => $sale->id,
                    'type'            => 'return',
                    'amount'          => -(int) $totalRefund,
                    'running_balance' => (int) $newBalance,
                    'description'     => "Return {$return->return_number}: store credit applied",
                ]);
            }

            // --- Update sale status ---
            $sale->load('items');
            $allReturned = self::allItemsFullyReturned($sale);
            $sale->update(['status' => $allReturned ? 'returned' : 'partially_returned']);

            return $return;
        });
    }

    /**
     * Map of sale_item_id → total quantity already returned across all returns for this sale.
     */
    public static function returnedQtyBySaleItem(string $saleId): array
    {
        return SaleReturnItem::whereHas('saleReturn', fn ($q) => $q->where('sale_id', $saleId))
            ->selectRaw('sale_item_id, SUM(quantity_returned) as total_returned')
            ->groupBy('sale_item_id')
            ->pluck('total_returned', 'sale_item_id')
            ->map(fn ($v) => (int) $v)
            ->toArray();
    }

    private static function allItemsFullyReturned(Sale $sale): bool
    {
        $returnedQtys = self::returnedQtyBySaleItem($sale->id);
        foreach ($sale->items as $item) {
            if (($returnedQtys[$item->id] ?? 0) < $item->quantity) {
                return false;
            }
        }
        return true;
    }
}
