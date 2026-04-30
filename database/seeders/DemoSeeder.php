<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Create demo tenant
        $tenant = Tenant::create([
            'id' => (string) Str::uuid(),
            'name' => 'Ahmed General Store',
            'subdomain' => 'ahmed',
            'plan' => 'standard',
            'status' => 'active',
            'trial_ends_at' => now()->addDays(14),
            'settings' => [
                'business_name' => 'Ahmed General Store',
                'currency' => 'PKR',
                'language' => 'en',
                'receipt_header' => 'Ahmed General Store\nMain Bazaar, Lahore\n0300-1234567',
                'receipt_footer' => 'Thank you for shopping with us!',
                'tax_rate' => 0,
            ],
        ]);

        // Create default branch
        $branch = Branch::withoutGlobalScope('tenant')->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'address' => 'Main Bazaar, Lahore',
            'phone' => '0300-1234567',
            'is_default' => true,
            'is_active' => true,
        ]);

        // Create owner user
        $owner = User::create([
            'name' => 'Ahmed Khan',
            'email' => 'admin@demo.com',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        // Create categories
        $categories = [
            ['name' => 'Grocery',    'color' => '#22c55e'],
            ['name' => 'Dairy',      'color' => '#3b82f6'],
            ['name' => 'Beverages',  'color' => '#f59e0b'],
            ['name' => 'Snacks',     'color' => '#ec4899'],
            ['name' => 'Household',  'color' => '#8b5cf6'],
            ['name' => 'Clothing',   'color' => '#f97316'],
        ];

        $createdCategories = [];
        foreach ($categories as $cat) {
            $createdCategories[$cat['name']] = Category::withoutGlobalScope('tenant')->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'name' => $cat['name'],
                'slug' => Str::slug($cat['name']),
                'color' => $cat['color'],
            ]);
        }

        // Create sample products (all prices in PKR decimal)
        $products = [
            ['name' => 'Basmati Rice 5kg',    'sku' => 'RIC-001', 'category' => 'Grocery',   'cost' => 850,   'price' => 950,   'unit' => 'bag',    'stock' => 45],
            ['name' => 'Cooking Oil 1L',       'sku' => 'OIL-001', 'category' => 'Grocery',   'cost' => 380,   'price' => 450,   'unit' => 'bottle', 'stock' => 30],
            ['name' => 'Sugar 1kg',            'sku' => 'SUG-001', 'category' => 'Grocery',   'cost' => 140,   'price' => 175,   'unit' => 'kg',     'stock' => 60],
            ['name' => 'Wheat Flour 10kg',     'sku' => 'FLR-001', 'category' => 'Grocery',   'cost' => 1050,  'price' => 1200,  'unit' => 'bag',    'stock' => 25],
            ['name' => 'Olper Milk 1L',        'sku' => 'MLK-001', 'category' => 'Dairy',     'cost' => 150,   'price' => 180,   'unit' => 'pack',   'stock' => 3,  'low' => true],
            ['name' => 'Desi Yogurt 400g',     'sku' => 'YOG-001', 'category' => 'Dairy',     'cost' => 95,    'price' => 120,   'unit' => 'cup',    'stock' => 12],
            ['name' => 'Eggs 12pc',            'sku' => 'EGG-001', 'category' => 'Dairy',     'cost' => 200,   'price' => 240,   'unit' => 'dozen',  'stock' => 8],
            ['name' => 'Pepsi 1.5L',           'sku' => 'PEP-001', 'category' => 'Beverages', 'cost' => 100,   'price' => 130,   'unit' => 'bottle', 'stock' => 24],
            ['name' => 'Mineral Water 1.5L',   'sku' => 'WAT-001', 'category' => 'Beverages', 'cost' => 40,    'price' => 55,    'unit' => 'bottle', 'stock' => 48],
            ['name' => 'Tapal Danedar 190g',   'sku' => 'TEA-001', 'category' => 'Beverages', 'cost' => 190,   'price' => 230,   'unit' => 'pack',   'stock' => 2,  'low' => true],
            ['name' => 'Lays Classic 100g',    'sku' => 'CHI-001', 'category' => 'Snacks',    'cost' => 50,    'price' => 65,    'unit' => 'pack',   'stock' => 36],
            ['name' => 'Marie Biscuits 200g',  'sku' => 'BIS-001', 'category' => 'Snacks',    'cost' => 65,    'price' => 85,    'unit' => 'pack',   'stock' => 20],
            ['name' => 'Surf Excel 500g',      'sku' => 'SRF-001', 'category' => 'Household', 'cost' => 190,   'price' => 240,   'unit' => 'pack',   'stock' => 15],
            ['name' => 'Lifebuoy Soap 175g',   'sku' => 'SAP-001', 'category' => 'Household', 'cost' => 60,    'price' => 80,    'unit' => 'piece',  'stock' => 0,  'low' => true],
        ];

        foreach ($products as $p) {
            $product = Product::withoutGlobalScope('tenant')->create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'category_id' => $createdCategories[$p['category']]->id,
                'name' => $p['name'],
                'sku' => $p['sku'],
                'barcode' => '69' . rand(10000000000, 99999999999),
                'unit' => $p['unit'],
                'cost_price' => $p['cost'],
                'selling_price' => $p['price'],
                'reorder_level' => 5,
                'is_active' => true,
            ]);

            StockLevel::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'product_id' => $product->id,
                'quantity' => $p['stock'],
            ]);
        }

        // One product with variants (clothing)
        $shirt = Product::withoutGlobalScope('tenant')->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'category_id' => $createdCategories['Clothing']->id,
            'name' => 'Cotton Kameez',
            'sku' => 'CLT-001',
            'unit' => 'piece',
            'cost_price'    => 450,
            'selling_price' => 650,
            'has_variants'  => true,
            'reorder_level' => 3,
            'is_active' => true,
        ]);

        foreach (['S', 'M', 'L', 'XL'] as $size) {
            $variant = $shirt->variants()->create([
                'id' => (string) Str::uuid(),
                'size' => $size,
                'color' => 'White',
                'sku' => 'CLT-001-' . $size,
                'cost_price'    => 450,
                'selling_price' => 650,
            ]);
            StockLevel::create([
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'product_id' => $shirt->id,
                'variant_id' => $variant->id,
                'quantity' => rand(2, 10),
            ]);
        }

        // Demo customers with udhaar balances (all amounts in PKR decimal)
        $customers = [
            ['name' => 'Khalid Mehmood',  'phone' => '0300-1234567', 'balance' => 2450.00],
            ['name' => 'Asif Rasheed',    'phone' => '0333-9876543', 'balance' => 0.00],
            ['name' => 'Nasreen Bibi',    'phone' => '0321-5551234', 'balance' => 800.00],
            ['name' => 'Tariq Hussain',   'phone' => '0311-7778899', 'balance' => 0.00],
            ['name' => 'Sana Ahmed',      'phone' => '0345-3334455', 'balance' => 1200.00],
        ];

        foreach ($customers as $c) {
            Customer::withoutGlobalScope('tenant')->create([
                'id'              => (string) Str::uuid(),
                'tenant_id'       => $tenant->id,
                'name'            => $c['name'],
                'phone'           => $c['phone'],
                'current_balance' => $c['balance'],
                'total_spend'     => rand(500, 50000),
            ]);
        }

        $this->command->info('Demo data seeded successfully!');
        $this->command->info('Login: admin@demo.com / password');
    }
}
