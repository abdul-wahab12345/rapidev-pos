<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $q = trim($request->get('q', ''));

        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $results = [];

        // Customers
        Customer::where(function ($query) use ($q) {
            $query->where('name', 'ilike', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%");
        })->limit(5)->get(['id', 'name', 'phone'])
          ->each(function ($c) use (&$results) {
              $results[] = [
                  'type'     => 'customer',
                  'id'       => $c->id,
                  'title'    => $c->name,
                  'subtitle' => $c->phone,
                  'url'      => route('customers.show', $c->id),
              ];
          });

        // Suppliers
        Supplier::where(function ($query) use ($q) {
            $query->where('name', 'ilike', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%");
        })->limit(5)->get(['id', 'name', 'phone'])
          ->each(function ($s) use (&$results) {
              $results[] = [
                  'type'     => 'supplier',
                  'id'       => $s->id,
                  'title'    => $s->name,
                  'subtitle' => $s->phone,
                  'url'      => route('purchasing.suppliers.show', $s->id),
              ];
          });

        // Sales / invoices
        Sale::where('invoice_number', 'ilike', "%{$q}%")
            ->limit(5)
            ->get(['id', 'invoice_number', 'total', 'status', 'created_at'])
            ->each(function ($s) use (&$results) {
                $results[] = [
                    'type'     => 'sale',
                    'id'       => $s->id,
                    'title'    => $s->invoice_number,
                    'subtitle' => 'Rs ' . number_format((float) $s->total, 0) . ' · ' . $s->status,
                    'url'      => route('sales.show', $s->id),
                ];
            });

        // Purchase orders
        PurchaseOrder::where('po_number', 'ilike', "%{$q}%")
            ->limit(5)
            ->get(['id', 'po_number', 'total', 'status'])
            ->each(function ($o) use (&$results) {
                $results[] = [
                    'type'     => 'purchase_order',
                    'id'       => $o->id,
                    'title'    => $o->po_number,
                    'subtitle' => 'Rs ' . number_format((float) $o->total, 0) . ' · ' . $o->status,
                    'url'      => route('purchasing.orders.show', $o->id),
                ];
            });

        return response()->json($results);
    }
}
