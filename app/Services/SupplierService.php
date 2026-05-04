<?php

namespace App\Services;

use App\Models\Supplier;

class SupplierService
{
    /**
     * Net payable = what you owe the supplier minus what they owe you (if they're also a customer).
     */
    public static function netPayable(Supplier $supplier): float
    {
        $ap = (float) $supplier->current_balance;

        $ar = 0.0;
        if ($supplier->party_id) {
            $ar = (float) ($supplier->party?->customer?->current_balance ?? 0);
        }

        return max(0, $ap - $ar);
    }

    /**
     * Full data shape used by SupplierController::show and the Inertia page.
     */
    public static function showData(Supplier $supplier): array
    {
        $supplier->load([
            'party.customer.ledgerEntries' => fn ($q) => $q->orderByDesc('created_at')->limit(15),
            'districtCity:id,name,province',
            'locality:id,name',
            'purchaseOrders' => fn ($q) => $q->withSum('supplierReturns', 'total_amount')->orderByDesc('order_date')->limit(20),
        ]);

        $party = $supplier->party;
        $customer = $party?->customer;

        $ap = (float) $supplier->current_balance;
        $ar = (float) ($customer?->current_balance ?? 0);

        return [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'company' => $supplier->company,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'address' => $supplier->address,
                'city' => $supplier->districtCity?->name ?? $supplier->city,
                'city_id' => $supplier->city_id,
                'area_id' => $supplier->area_id,
                'province_label' => $supplier->districtCity?->province,
                'area_label' => $supplier->locality?->name,
                'ntn' => $supplier->ntn,
                'payment_terms' => $supplier->payment_terms,
                'current_balance' => $ap,
                'is_active' => $supplier->is_active,
                'notes' => $supplier->notes,
                'party_id' => $supplier->party_id,
            ],
            'customer_link' => $customer ? [
                'id' => $customer->id,
                'current_balance' => $ar,
            ] : null,
            'net_payable' => max(0, $ap - $ar),
            'is_also_customer' => $customer !== null,
            'purchase_orders' => $supplier->purchaseOrders->map(fn ($po) => [
                'id' => $po->id,
                'po_number' => $po->po_number,
                'order_date' => $po->order_date?->format('Y-m-d'),
                'status' => $po->status,
                'total' => (float) $po->total,
                'paid_amount' => (float) $po->paid_amount,
                'amount_due' => $po->amountDue(),
            ])->values(),
        ];
    }

    /**
     * Minimal row shape used in index listings (avoids N+1 — caller must eager-load party.customer).
     */
    public static function indexRow(Supplier $supplier): array
    {
        $ap = (float) $supplier->current_balance;
        $ar = (float) ($supplier->party?->customer?->current_balance ?? 0);

        return [
            'id' => $supplier->id,
            'name' => $supplier->name,
            'company' => $supplier->company,
            'phone' => $supplier->phone,
            'email' => $supplier->email,
            'city' => $supplier->districtCity?->name ?? $supplier->city,
            'area' => $supplier->locality?->name,
            'city_id' => $supplier->city_id,
            'area_id' => $supplier->area_id,
            'payment_terms' => $supplier->payment_terms,
            'current_balance' => $ap,
            'ar_balance' => $ar,
            'net_payable' => max(0, $ap - $ar),
            'is_also_customer' => $ar > 0,
            'is_active' => $supplier->is_active,
            'party_id' => $supplier->party_id,
            'customer_id' => $supplier->party?->customer?->id,
        ];
    }
}
