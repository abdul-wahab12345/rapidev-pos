<?php

namespace App\Http\Controllers\Tables;

use App\Http\Controllers\Controller;
use App\Models\DiningTable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiningTableController extends Controller
{
    public function index(): Response
    {
        $tenant  = auth()->user()->tenant;
        $branch  = $tenant->defaultBranch();

        $tables = DiningTable::where('tenant_id', $tenant->id)
            ->when($branch, fn ($q) => $q->where('branch_id', $branch->id))
            ->where('is_active', true)
            ->orderBy('section')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id'       => $t->id,
                'name'     => $t->name,
                'capacity' => $t->capacity,
                'section'  => $t->section,
            ]);

        return Inertia::render('Tables/Index', [
            'tables' => $tables,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'capacity' => 'required|integer|min:1|max:100',
            'section'  => 'nullable|string|max:100',
        ]);

        $tenant = auth()->user()->tenant;
        $branch = $tenant->defaultBranch();

        DiningTable::create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch?->id,
            'name'      => $data['name'],
            'capacity'  => $data['capacity'],
            'section'   => $data['section'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Table created.');
    }

    public function update(Request $request, DiningTable $diningTable): RedirectResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'capacity' => 'required|integer|min:1|max:100',
            'section'  => 'nullable|string|max:100',
        ]);

        $diningTable->update($data);

        return back()->with('success', 'Table updated.');
    }

    public function destroy(DiningTable $diningTable): RedirectResponse
    {
        $diningTable->update(['is_active' => false]);

        return back()->with('success', 'Table removed.');
    }
}
