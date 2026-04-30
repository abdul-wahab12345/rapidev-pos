<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BusinessSettingsController extends Controller
{
    public function index(): Response
    {
        $tenant = auth()->user()->tenant;

        $settings = array_merge([
            'business_name'    => $tenant->name,
            'business_phone'   => '',
            'business_email'   => '',
            'business_address' => '',
            'business_city'    => '',
            'logo_url'         => '',
            'currency'         => 'PKR',
            'currency_symbol'  => 'Rs',
            'language'         => 'en',
            'tax_enabled'      => false,
            'tax_name'         => 'GST',
            'tax_rate'         => 17,
            'receipt_header'   => '',
            'receipt_footer'   => 'Thank you for your business!',
            'receipt_show_tax' => true,
            'receipt_show_logo'=> true,
        ], $tenant->settings ?? []);

        return Inertia::render('settings/Business', [
            'settings'    => $settings,
            'tenant_name' => $tenant->name,
            'plan'        => $tenant->plan,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name'    => 'required|string|max:100',
            'business_phone'   => 'nullable|string|max:30',
            'business_email'   => 'nullable|email|max:100',
            'business_address' => 'nullable|string|max:300',
            'business_city'    => 'nullable|string|max:100',
            'currency'         => 'required|string|max:10',
            'currency_symbol'  => 'required|string|max:5',
            'language'         => 'required|in:en,ur',
            'tax_enabled'      => 'boolean',
            'tax_name'         => 'nullable|string|max:20',
            'tax_rate'         => 'nullable|numeric|min:0|max:100',
            'receipt_header'   => 'nullable|string|max:300',
            'receipt_footer'   => 'nullable|string|max:300',
            'receipt_show_tax' => 'boolean',
            'receipt_show_logo'=> 'boolean',
        ]);

        $tenant = auth()->user()->tenant;
        $existing = $tenant->settings ?? [];
        $tenant->update(['settings' => array_merge($existing, $validated)]);

        return back()->with('success', 'Settings saved successfully.');
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate(['logo' => 'required|image|mimes:png,jpg,jpeg,webp|max:1024']);

        $tenant = auth()->user()->tenant;

        // Delete previous logo if one exists
        $existing = $tenant->settings ?? [];
        if (!empty($existing['logo_path'])) {
            Storage::disk('public')->delete($existing['logo_path']);
        }

        $path = $request->file('logo')->store("tenants/{$tenant->id}/logos", 'public');
        $url  = Storage::url($path);

        $tenant->update(['settings' => array_merge($existing, [
            'logo_url'  => $url,
            'logo_path' => $path,   // store raw path for future deletion
        ])]);

        return response()->json(['logo_url' => $url]);
    }
}
