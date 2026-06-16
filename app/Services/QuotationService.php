<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use Illuminate\Support\Facades\DB;

class QuotationService
{
    /**
     * Create a new quotation with items.
     */
    public function create(array $data, int $userId, string $tenantId): Quotation
    {
        return DB::transaction(function () use ($data, $userId, $tenantId) {
            $quotation = Quotation::create([
                'tenant_id'        => $tenantId,
                'customer_id'      => $data['customer_id'] ?? null,
                'user_id'          => $userId,
                'quotation_number' => Quotation::generateNumber($tenantId),
                'status'           => 'draft',
                'site_address'     => $data['site_address'] ?? null,
                'valid_until'      => $data['valid_until'] ?? null,
                'subtotal'         => 0,
                'discount'         => $data['discount'] ?? 0,
                'tax'              => $data['tax'] ?? 0,
                'delivery_fee'     => $data['delivery_fee'] ?? 0,
                'total'            => 0,
                'advance_paid'     => $data['advance_paid'] ?? 0,
                'notes'            => $data['notes'] ?? null,
            ]);

            $subtotal = 0;
            foreach ($data['items'] as $itemData) {
                $lineTotal = ($itemData['quantity'] * $itemData['unit_price']) - ($itemData['discount'] ?? 0);
                $subtotal += $lineTotal;

                QuotationItem::create([
                    'quotation_id'  => $quotation->id,
                    'product_id'    => $itemData['product_id'] ?? null,
                    'variant_id'    => $itemData['variant_id'] ?? null,
                    'product_name'  => $itemData['product_name'],
                    'product_unit'  => $itemData['product_unit'] ?? 'piece',
                    'quantity'      => $itemData['quantity'],
                    'unit_price'    => $itemData['unit_price'],
                    'discount'      => $itemData['discount'] ?? 0,
                    'line_total'    => $lineTotal,
                    'notes'         => $itemData['notes'] ?? null,
                ]);
            }

            $deliveryFee = $data['delivery_fee'] ?? 0;
            $total = $subtotal - ($data['discount'] ?? 0) + ($data['tax'] ?? 0) + $deliveryFee;

            $quotation->update([
                'subtotal'      => $subtotal,
                'delivery_fee'  => $deliveryFee,
                'total'         => max(0, $total),
            ]);

            return $quotation;
        });
    }

    /**
     * Update an existing quotation (draft or sent only).
     */
    public function update(Quotation $quotation, array $data): Quotation
    {
        return DB::transaction(function () use ($quotation, $data) {
            $quotation->items()->delete();

            $subtotal = 0;
            foreach ($data['items'] as $itemData) {
                $lineTotal = ($itemData['quantity'] * $itemData['unit_price']) - ($itemData['discount'] ?? 0);
                $subtotal += $lineTotal;

                QuotationItem::create([
                    'quotation_id' => $quotation->id,
                    'product_id'   => $itemData['product_id'] ?? null,
                    'variant_id'   => $itemData['variant_id'] ?? null,
                    'product_name' => $itemData['product_name'],
                    'product_unit' => $itemData['product_unit'] ?? 'piece',
                    'quantity'     => $itemData['quantity'],
                    'unit_price'   => $itemData['unit_price'],
                    'discount'     => $itemData['discount'] ?? 0,
                    'line_total'   => $lineTotal,
                    'notes'        => $itemData['notes'] ?? null,
                ]);
            }

            $deliveryFee = $data['delivery_fee'] ?? 0;
            $total = $subtotal - ($data['discount'] ?? 0) + ($data['tax'] ?? 0) + $deliveryFee;

            $quotation->update([
                'customer_id'  => $data['customer_id'] ?? null,
                'site_address' => $data['site_address'] ?? null,
                'valid_until'  => $data['valid_until'] ?? null,
                'subtotal'     => $subtotal,
                'discount'     => $data['discount'] ?? 0,
                'tax'          => $data['tax'] ?? 0,
                'delivery_fee' => $deliveryFee,
                'total'        => max(0, $total),
                'advance_paid' => $data['advance_paid'] ?? 0,
                'notes'        => $data['notes'] ?? null,
            ]);

            return $quotation->fresh();
        });
    }

    /**
     * Convert an approved quotation to a sale.
     * This deducts stock and creates a proper Sale + SaleItems.
     */
    public function convertToSale(Quotation $quotation, array $paymentData, int $userId): Sale
    {
        return DB::transaction(function () use ($quotation, $paymentData, $userId) {
            $branch = auth()->user()->tenant?->defaultBranch();
            $tenantId = auth()->user()->tenant_id;

            // Determine payment method
            $cash    = (float) ($paymentData['cash'] ?? 0);
            $jazzcash= (float) ($paymentData['jazzcash'] ?? 0);
            $easypaisa=(float)($paymentData['easypaisa'] ?? 0);
            $bank    = (float) ($paymentData['bank'] ?? 0);
            $udhaar  = (float) ($paymentData['udhaar'] ?? 0);

            $nonZero = collect([$cash, $jazzcash, $easypaisa, $bank, $udhaar])->filter(fn($v) => $v > 0)->count();
            if ($nonZero > 1) {
                $method = 'mixed';
            } elseif ($jazzcash > 0) $method = 'jazzcash';
            elseif ($easypaisa > 0) $method = 'easypaisa';
            elseif ($bank > 0)      $method = 'bank';
            elseif ($udhaar > 0)    $method = 'udhaar';
            else                    $method = 'cash';

            $sale = Sale::create([
                'tenant_id'       => $tenantId,
                'branch_id'       => $branch?->id,
                'user_id'         => $userId,
                'customer_id'     => $quotation->customer_id,
                'quotation_id'    => $quotation->id,
                'invoice_number'  => Sale::generateInvoiceNumber($tenantId),
                'status'          => 'completed',
                'subtotal'        => $quotation->subtotal,
                'discount'        => $quotation->discount,
                'tax'             => $quotation->tax,
                'delivery_fee'    => $quotation->delivery_fee,
                'total'           => $quotation->total,
                'advance_paid'    => $quotation->advance_paid,
                'paid'            => $cash + $jazzcash + $easypaisa + $bank,
                'change_amount'   => 0,
                'cash_amount'     => $cash,
                'jazzcash_amount' => $jazzcash,
                'easypaisa_amount'=> $easypaisa,
                'bank_amount'     => $bank,
                'udhaar_amount'   => $udhaar,
                'payment_method'  => $method,
                'notes'           => $quotation->notes,
            ]);

            foreach ($quotation->items as $item) {
                SaleItem::create([
                    'sale_id'      => $sale->id,
                    'product_id'   => $item->product_id,
                    'variant_id'   => $item->variant_id,
                    'product_name' => $item->product_name,
                    'unit_price'   => (int) round($item->unit_price),
                    'cost_price'   => 0,
                    'quantity'     => (int) ceil($item->quantity),
                    'discount'     => (int) round($item->discount),
                    'line_total'   => (int) round($item->line_total),
                ]);

                // Deduct stock for products that have stock levels
                if ($item->product_id && $branch) {
                    StockLevel::where('product_id', $item->product_id)
                        ->where('branch_id', $branch->id)
                        ->when($item->variant_id, fn($q) => $q->where('variant_id', $item->variant_id))
                        ->decrement('quantity', (int) ceil($item->quantity));
                }
            }

            // Handle udhaar (customer balance)
            if ($udhaar > 0 && $quotation->customer_id) {
                $customer = Customer::find($quotation->customer_id);
                $customer?->increment('current_balance', $udhaar);

                \App\Models\CustomerLedgerEntry::create([
                    'customer_id'     => $customer->id,
                    'sale_id'         => $sale->id,
                    'type'            => 'sale',
                    'amount'          => (int) $udhaar,
                    'running_balance' => (int) $customer->current_balance,
                    'notes'           => "Sale {$sale->invoice_number} (from quotation {$quotation->quotation_number})",
                ]);
            }

            // Mark quotation as converted
            $quotation->update([
                'status'            => 'converted',
                'converted_sale_id' => $sale->id,
            ]);

            // Post accounting
            AccountingService::postSale($sale);

            return $sale;
        });
    }
}
