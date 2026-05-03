<?php

namespace App\Http\Controllers\RateLists;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\RateList;
use App\Models\RateListItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RateListController extends Controller
{
    public function index(): Response
    {
        $rateLists = RateList::withCount('items')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn ($rl) => [
                'id'          => $rl->id,
                'name'        => $rl->name,
                'name_ur'     => $rl->name_ur,
                'description' => $rl->description,
                'is_active'   => $rl->is_active,
                'items_count' => $rl->items_count,
            ]);

        return Inertia::render('RateLists/Index', [
            'rateLists' => $rateLists,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'name_ur'     => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        RateList::create($validated);

        return back()->with('success', 'Rate list created.');
    }

    public function update(Request $request, RateList $rateList): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'name_ur'     => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ]);

        $rateList->update($validated);

        return back()->with('success', 'Rate list updated.');
    }

    public function destroy(RateList $rateList): RedirectResponse
    {
        $rateList->delete();

        return back()->with('success', 'Rate list deleted.');
    }

    public function activate(RateList $rateList): RedirectResponse
    {
        $rateList->activate();

        return back()->with('success', 'Rate list activated.');
    }

    public function deactivate(RateList $rateList): RedirectResponse
    {
        $rateList->deactivate();

        return back()->with('success', 'Rate list deactivated.');
    }

    public function show(RateList $rateList): Response
    {
        $items = RateListItem::where('rate_list_id', $rateList->id)
            ->with(['product:id,name,name_ur,sku,selling_price', 'variant:id,size,color,sku'])
            ->get()
            ->keyBy(fn ($item) => $item->product_id . '_' . ($item->variant_id ?? ''));

        $products = Product::active()
            ->with('variants:id,product_id,size,color,sku')
            ->orderBy('name')
            ->get(['id', 'name', 'name_ur', 'sku', 'selling_price', 'has_variants'])
            ->map(function ($p) use ($items) {
                if ($p->has_variants) {
                    $variants = $p->variants->map(function ($v) use ($p, $items) {
                        $key = $p->id . '_' . $v->id;
                        $label = trim(implode(' - ', array_filter([$v->size, $v->color]))) ?: 'Variant';
                        return [
                            'id'            => $v->id,
                            'label'         => $label,
                            'sku'           => $v->sku,
                            'default_price' => (float) $p->selling_price,
                            'rate_price'    => isset($items[$key]) ? (float) $items[$key]->price : null,
                        ];
                    });

                    return [
                        'id'           => $p->id,
                        'name'         => $p->name,
                        'name_ur'      => $p->name_ur,
                        'sku'          => $p->sku,
                        'has_variants' => true,
                        'variants'     => $variants,
                    ];
                }

                $key = $p->id . '_';
                return [
                    'id'            => $p->id,
                    'name'          => $p->name,
                    'name_ur'       => $p->name_ur,
                    'sku'           => $p->sku,
                    'has_variants'  => false,
                    'default_price' => (float) $p->selling_price,
                    'rate_price'    => isset($items[$key]) ? (float) $items[$key]->price : null,
                ];
            });

        return Inertia::render('RateLists/Show', [
            'rateList' => [
                'id'          => $rateList->id,
                'name'        => $rateList->name,
                'name_ur'     => $rateList->name_ur,
                'description' => $rateList->description,
                'is_active'   => $rateList->is_active,
            ],
            'products' => $products,
        ]);
    }

    public function savePrices(Request $request, RateList $rateList): RedirectResponse
    {
        $validated = $request->validate([
            'prices'             => 'required|array',
            'prices.*.product_id' => 'required|exists:products,id',
            'prices.*.variant_id' => 'nullable|exists:product_variants,id',
            'prices.*.price'      => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($rateList, $validated) {
            foreach ($validated['prices'] as $row) {
                $price = $row['price'];

                if ($price === null || $price === '') {
                    RateListItem::where('rate_list_id', $rateList->id)
                        ->where('product_id', $row['product_id'])
                        ->where('variant_id', $row['variant_id'] ?? null)
                        ->delete();
                } else {
                    RateListItem::updateOrCreate(
                        [
                            'rate_list_id' => $rateList->id,
                            'product_id'   => $row['product_id'],
                            'variant_id'   => $row['variant_id'] ?? null,
                        ],
                        ['price' => round((float) $price, 2)]
                    );
                }
            }
        });

        return back()->with('success', 'Prices saved.');
    }
}
