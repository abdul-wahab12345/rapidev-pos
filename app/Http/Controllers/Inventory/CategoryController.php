<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Category::withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $categories = $query->get()->map(fn ($c) => [
            'id'             => $c->id,
            'name'           => $c->name,
            'slug'           => $c->slug,
            'color'          => $c->color,
            'sort_order'     => $c->sort_order,
            'is_active'      => $c->is_active,
            'products_count' => $c->products_count,
        ]);

        return Inertia::render('Inventory/Categories/Index', [
            'categories' => $categories,
            'filters'    => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'color'      => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        Category::create([
            'name'       => $validated['name'],
            'slug'       => Str::slug($validated['name']),
            'color'      => $validated['color'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active'  => $validated['is_active'] ?? true,
        ]);

        return back()->with('success', 'Category created successfully.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100',
            'color'      => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort_order' => 'nullable|integer|min:0',
            'is_active'  => 'boolean',
        ]);

        $category->update([
            'name'       => $validated['name'],
            'slug'       => Str::slug($validated['name']),
            'color'      => $validated['color'],
            'sort_order' => $validated['sort_order'] ?? $category->sort_order,
            'is_active'  => $validated['is_active'] ?? $category->is_active,
        ]);

        return back()->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->count() > 0) {
            return back()->with('error', "Cannot delete \"{$category->name}\" — it has {$category->products()->count()} product(s) assigned.");
        }

        $category->delete();

        return back()->with('success', 'Category deleted.');
    }
}
