<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Product::with(['category', 'stockLevels'])
            ->when($request->search, fn ($q) => $q->search($request->search))
            ->when($request->category, fn ($q) => $q->where('category_id', $request->category))
            ->when($request->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->stock === 'low', fn ($q) => $q->lowStock())
            ->when($request->stock === 'out', fn ($q) => $q->whereHas('stockLevels', fn ($q) => $q->where('quantity', 0)));

        $products = $query->latest()->paginate(20)->withQueryString();

        $products->through(fn ($product) => [
            'id'         => $product->id,
            'name'       => $product->name,
            'name_ur'    => $product->name_ur,
            'sku'        => $product->sku,
            'barcode'    => $product->barcode,
            'unit'       => $product->unit,
            'cost_price'    => (float) $product->cost_price,
            'selling_price' => (float) $product->selling_price,
            'margin' => $product->margin,
            'has_variants' => $product->has_variants,
            'is_active' => $product->is_active,
            'reorder_level' => $product->reorder_level,
            'total_stock' => $product->stockLevels->sum('quantity'),
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'color' => $product->category->color,
            ] : null,
        ]);

        $categories = Category::active()->orderBy('name')->get(['id', 'name', 'color']);

        $stats = [
            'total' => Product::count(),
            'active' => Product::where('is_active', true)->count(),
            'with_variants' => Product::where('has_variants', true)->count(),
            'low_stock' => Product::whereHas('stockLevels', function ($q) {
                $q->whereRaw('stock_levels.quantity <= (SELECT reorder_level FROM products WHERE products.id = stock_levels.product_id)');
            })->count(),
            'out_of_stock' => Product::whereHas('stockLevels', fn ($q) => $q->where('quantity', 0))->count(),
        ];

        return Inertia::render('Inventory/Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'stats' => $stats,
            'filters' => $request->only(['search', 'category', 'status', 'stock']),
        ]);
    }

    public function create(): Response
    {
        $categories = Category::active()->orderBy('name')->get(['id', 'name', 'color']);

        return Inertia::render('Inventory/Products/Create', [
            'categories' => $categories,
            'units' => $this->getUnits(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ur' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'unit' => 'required|string',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'has_variants' => 'boolean',
            'is_active' => 'boolean',
            'initial_stock' => 'nullable|integer|min:0',
            'variants' => 'nullable|array',
            'variants.*.size' => 'nullable|string',
            'variants.*.color' => 'nullable|string',
            'variants.*.sku' => 'nullable|string',
            'variants.*.cost_price' => 'nullable|numeric|min:0',
            'variants.*.selling_price' => 'nullable|numeric|min:0',
        ]);

        $product = Product::create([
            'category_id' => $validated['category_id'] ?? null,
            'name' => $validated['name'],
            'name_ur' => $validated['name_ur'] ?? null,
            'sku' => $validated['sku'] ?? $this->generateSku($validated['name']),
            'barcode' => $validated['barcode'] ?? null,
            'description' => $validated['description'] ?? null,
            'unit' => $validated['unit'],
            'cost_price'    => (float) $validated['cost_price'],
            'selling_price' => (float) $validated['selling_price'],
            'reorder_level' => $validated['reorder_level'] ?? 5,
            'has_variants'  => $validated['has_variants'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Create stock level for default branch
        $branch = auth()->user()->tenant?->defaultBranch();
        if ($branch && !$product->has_variants) {
            StockLevel::create([
                'tenant_id' => auth()->user()->tenant_id,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'quantity' => $validated['initial_stock'] ?? 0,
            ]);
        }

        // Create variants if provided
        if ($product->has_variants && !empty($validated['variants'])) {
            foreach ($validated['variants'] as $variantData) {
                $variant = $product->variants()->create([
                    'size' => $variantData['size'] ?? null,
                    'color' => $variantData['color'] ?? null,
                    'sku' => $variantData['sku'] ?? null,
                    'cost_price'    => (float) ($variantData['cost_price'] ?? $validated['cost_price']),
                    'selling_price' => (float) ($variantData['selling_price'] ?? $validated['selling_price']),
                ]);

                if ($branch) {
                    StockLevel::create([
                        'tenant_id' => auth()->user()->tenant_id,
                        'branch_id' => $branch->id,
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'quantity' => 0,
                    ]);
                }
            }
        }

        return redirect()->route('inventory.products.index')
            ->with('success', "Product \"{$product->name}\" created successfully.");
    }

    public function edit(Product $product): Response
    {
        $product->load(['category', 'variants', 'stockLevels']);
        $categories = Category::active()->orderBy('name')->get(['id', 'name', 'color']);

        return Inertia::render('Inventory/Products/Edit', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'name_ur' => $product->name_ur,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'description' => $product->description,
                'unit' => $product->unit,
                'cost_price'    => (float) $product->cost_price,
                'selling_price' => (float) $product->selling_price,
                'reorder_level' => $product->reorder_level,
                'has_variants' => $product->has_variants,
                'is_active' => $product->is_active,
                'category_id' => $product->category_id,
                'category' => $product->category,
                'variants' => $product->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'size' => $v->size,
                    'color' => $v->color,
                    'sku' => $v->sku,
                    'cost_price'    => (float) $v->cost_price,
                    'selling_price' => (float) $v->selling_price,
                    'is_active' => $v->is_active,
                    'stock' => $v->stockLevels->sum('quantity'),
                ]),
                'total_stock' => $product->stockLevels->sum('quantity'),
            ],
            'categories' => $categories,
            'units' => $this->getUnits(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_ur' => 'nullable|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'unit' => 'required|string',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'reorder_level' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $product->update([
            'category_id' => $validated['category_id'] ?? null,
            'name' => $validated['name'],
            'name_ur' => $validated['name_ur'] ?? null,
            'sku' => $validated['sku'],
            'barcode' => $validated['barcode'] ?? null,
            'description' => $validated['description'] ?? null,
            'unit' => $validated['unit'],
            'cost_price'    => (float) $validated['cost_price'],
            'selling_price' => (float) $validated['selling_price'],
            'reorder_level' => $validated['reorder_level'] ?? 5,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return redirect()->route('inventory.products.index')
            ->with('success', "Product \"{$product->name}\" updated successfully.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $product->delete();

        return redirect()->route('inventory.products.index')
            ->with('success', "Product \"{$name}\" deleted.");
    }

    public function toggleStatus(Product $product): RedirectResponse
    {
        $product->update(['is_active' => !$product->is_active]);
        $status = $product->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "Product {$status}.");
    }

    // ── CSV template download ─────────────────────────────────────
    public function csvTemplate(): HttpResponse
    {
        $headers = [
            'name', 'name_ur', 'category', 'sku', 'barcode',
            'unit', 'cost_price', 'selling_price', 'initial_stock',
            'reorder_level', 'is_active',
        ];

        $example = [
            'Chicken Burger', 'چکن برگر', 'Burgers', 'BRG-001', '',
            'piece', '150', '250', '50', '10', '1',
        ];

        $csv  = implode(',', $headers) . "\n";
        $csv .= implode(',', $example) . "\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products_import_template.csv"',
        ]);
    }

    // ── CSV import ────────────────────────────────────────────────
    public function importCsv(Request $request): RedirectResponse
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file   = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        // Read and normalise header row
        $rawHeaders = fgetcsv($handle);
        if (!$rawHeaders) {
            fclose($handle);
            return back()->with('error', 'CSV file is empty or unreadable.');
        }
        $headers = array_map(fn ($h) => strtolower(trim($h)), $rawHeaders);

        $required = ['name', 'cost_price', 'selling_price'];
        foreach ($required as $col) {
            if (!in_array($col, $headers)) {
                fclose($handle);
                return back()->with('error', "CSV is missing required column: \"{$col}\".");
            }
        }

        $user   = auth()->user();
        $tenant = $user->tenant;
        $branch = $tenant?->defaultBranch();

        // Build category name → id map (tenant-scoped)
        $categoryMap = Category::pluck('id', 'name')->toArray();

        $imported = 0;
        $skipped  = 0;
        $errors   = [];
        $row      = 1;

        DB::transaction(function () use (
            $handle, $headers, $categoryMap, $user, $tenant, $branch,
            &$imported, &$skipped, &$errors, &$row
        ) {
            while (($data = fgetcsv($handle)) !== false) {
                $row++;
                if (count(array_filter($data)) === 0) continue; // skip blank rows

                $col = [];
                foreach ($headers as $i => $key) {
                    $col[$key] = isset($data[$i]) ? trim($data[$i]) : '';
                }

                $name = $col['name'] ?? '';
                if (empty($name)) {
                    $errors[] = "Row {$row}: name is empty — skipped.";
                    $skipped++;
                    continue;
                }

                $costPrice    = (float) ($col['cost_price']    ?? 0);
                $sellingPrice = (float) ($col['selling_price'] ?? 0);

                if ($sellingPrice <= 0) {
                    $errors[] = "Row {$row}: \"{$name}\" has invalid selling_price — skipped.";
                    $skipped++;
                    continue;
                }

                // Resolve category by name
                $categoryId = null;
                if (!empty($col['category'])) {
                    $catName = $col['category'];
                    if (!isset($categoryMap[$catName])) {
                        // Auto-create the category
                        $newCat = Category::create([
                            'name'  => $catName,
                            'slug'  => Str::slug($catName),
                            'color' => '#6366f1',
                        ]);
                        $categoryMap[$catName] = $newCat->id;
                    }
                    $categoryId = $categoryMap[$catName];
                }

                $sku = !empty($col['sku']) ? $col['sku'] : $this->generateSku($name);

                // Skip duplicate SKU within this tenant
                if (Product::where('sku', $sku)->exists()) {
                    $errors[] = "Row {$row}: SKU \"{$sku}\" already exists — skipped.";
                    $skipped++;
                    continue;
                }

                $product = Product::create([
                    'tenant_id'     => $tenant->id,
                    'category_id'   => $categoryId,
                    'name'          => $name,
                    'name_ur'       => $col['name_ur'] ?? null ?: null,
                    'sku'           => $sku,
                    'barcode'       => $col['barcode'] ?? null ?: null,
                    'unit'          => !empty($col['unit']) ? $col['unit'] : 'piece',
                    'cost_price'    => $costPrice,
                    'selling_price' => $sellingPrice,
                    'reorder_level' => isset($col['reorder_level']) && $col['reorder_level'] !== ''
                                        ? (int) $col['reorder_level'] : 5,
                    'is_active'     => isset($col['is_active']) && $col['is_active'] !== ''
                                        ? (bool)(int) $col['is_active'] : true,
                    'has_variants'  => false,
                ]);

                if ($branch) {
                    StockLevel::create([
                        'tenant_id'  => $tenant->id,
                        'branch_id'  => $branch->id,
                        'product_id' => $product->id,
                        'quantity'   => isset($col['initial_stock']) && $col['initial_stock'] !== ''
                                         ? (int) $col['initial_stock'] : 0,
                    ]);
                }

                $imported++;
            }
        });

        fclose($handle);

        $message = "Imported {$imported} product(s) successfully.";
        if ($skipped > 0) {
            $message .= " {$skipped} row(s) skipped.";
        }

        $sessionData = ['success' => $message];
        if (!empty($errors)) {
            $sessionData['import_warnings'] = array_slice($errors, 0, 10); // cap at 10 warnings
        }

        return back()->with($sessionData);
    }

    private function generateSku(string $name): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $name), 0, 3));
        return $prefix . '-' . strtoupper(Str::random(4));
    }

    private function getUnits(): array
    {
        return [
            ['value' => 'piece', 'label' => 'Piece (عدد)'],
            ['value' => 'kg', 'label' => 'Kilogram (کلو)'],
            ['value' => 'gram', 'label' => 'Gram (گرام)'],
            ['value' => 'liter', 'label' => 'Liter (لیٹر)'],
            ['value' => 'ml', 'label' => 'Milliliter (ملی لیٹر)'],
            ['value' => 'dozen', 'label' => 'Dozen (درجن)'],
            ['value' => 'meter', 'label' => 'Meter (میٹر)'],
            ['value' => 'box', 'label' => 'Box (ڈبہ)'],
            ['value' => 'pack', 'label' => 'Pack (پیک)'],
            ['value' => 'bag', 'label' => 'Bag (تھیلہ)'],
            ['value' => 'bottle', 'label' => 'Bottle (بوتل)'],
        ];
    }
}
