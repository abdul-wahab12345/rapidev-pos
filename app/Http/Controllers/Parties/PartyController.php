<?php

namespace App\Http\Controllers\Parties;

use App\Http\Controllers\Controller;
use App\Models\Party;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public function show(Party $party): Response
    {
        $party->load([
            'customer.ledgerEntries' => fn ($q) => $q->orderByDesc('created_at')->limit(20),
            'customer.sales'         => fn ($q) => $q->where('status', '!=', 'voided')->orderByDesc('created_at')->limit(10),
            'supplier.purchaseOrders'=> fn ($q) => $q->orderByDesc('created_at')->limit(10),
        ]);

        $customer = $party->customer;
        $supplier = $party->supplier;

        return Inertia::render('Parties/Show', [
            'party' => [
                'id'          => $party->id,
                'name'        => $party->name,
                'phone'       => $party->phone,
                'email'       => $party->email,
                'address'     => $party->address,
                'is_customer' => $party->is_customer,
                'is_supplier' => $party->is_supplier,
            ],
            'receivable' => $customer ? [
                'customer_id'     => $customer->id,
                'current_balance' => (float) $customer->current_balance,
                'total_spend'     => (float) $customer->total_spend,
                'ledger'          => $customer->ledgerEntries->map(fn ($e) => [
                    'id'              => $e->id,
                    'type'            => $e->type,
                    'amount'          => (float) $e->amount,
                    'running_balance' => (float) $e->running_balance,
                    'description'     => $e->description,
                    'created_at'      => $e->created_at,
                ]),
            ] : null,
            'payable' => $supplier ? [
                'supplier_id'     => $supplier->id,
                'current_balance' => (float) $supplier->current_balance,
                'purchase_orders' => $supplier->purchaseOrders->map(fn ($po) => [
                    'id'             => $po->id,
                    'order_number'   => $po->order_number ?? $po->id,
                    'total'          => (float) $po->total,
                    'status'         => $po->status,
                    'created_at'     => $po->created_at,
                ]),
            ] : null,
            'net_balance' => $party->net_balance,
        ]);
    }
}
