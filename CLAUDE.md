# RapiDev POS — Claude Reference

## Project Overview
Multi-tenant Point-of-Sale system for Pakistani retail businesses. Laravel 12 + Vue 3 + Inertia.js SPA, PostgreSQL, PKR (Pakistani Rupee).

## Tech Stack
- **Backend:** PHP 8.2, Laravel 12, Inertia.js (server-side)
- **Frontend:** Vue 3 (Composition API + TypeScript), Tailwind CSS, shadcn/ui components
- **Database:** PostgreSQL (uses `ilike` for case-insensitive search, `GREATEST()` etc.)
- **State:** Pinia (cart only), Inertia props for everything else
- **Routing:** Ziggy — exposes named Laravel routes to JS via `route('name', param)`
- **Icons:** lucide-vue-next
- **Dev servers:** `php artisan serve` (port 8000) + `npm run dev` (Vite, port 5173)

## Critical Conventions

### Money / Amounts
- **`SaleItem` amounts** (`unit_price`, `cost_price`, `line_total`, `discount`) are stored as **integers (whole rupees)**
- **`Sale` totals** (`total`, `subtotal`, `discount`, `tax`, etc.) are `decimal:2`
- **`CustomerLedgerEntry.amount`** and `running_balance` are **integers**
- **`formatMoney(n)`** in `resources/js/utils/format.ts` returns `"Rs 1,234"` — the "Rs " prefix is already included. **Never prepend "Rs " again in templates.** Use `{{ formatMoney(x) }}` not `Rs {{ formatMoney(x) }}`.
- `ExpenseService` and `SaleReturn.total_refund` also use integers for refund amounts.

### Multi-tenancy (`TenantAware`)
- **All major models extend `TenantAware`** (`app/Models/TenantAware.php`)
- `TenantAware` auto-sets `tenant_id` on create from `auth()->user()->tenant_id`
- Global Eloquent scope filters every query by current tenant automatically
- UUID primary keys + soft deletes on all TenantAware models
- When doing raw joins, qualify ambiguous columns: `expenses.tenant_id` not just `tenant_id`

### Controllers → Services pattern
- Controllers are thin: validate → call service → return Inertia response or redirect
- Services handle DB transactions and business logic
- Key services: `AccountingService`, `ExpenseService`, `ReturnService`, `SupplierService`

### Inertia (no separate API)
- Controllers return `Inertia::render('PageName', [...])` or `back()->with('success/error', '...')`
- No JSON API endpoints except `SalesController::receiptData()` (receipt printing)
- Frontend uses `router.get/post/patch/delete()` and `useForm()` from `@inertiajs/vue3`

---

## Data Model

### Core Models (all UUID PKs, soft deletes unless noted)
| Model | Table | Key fields |
|---|---|---|
| `Tenant` | `tenants` | `name`, `settings` (JSON) |
| `User` | `users` | `tenant_id`, `branch_id` |
| `Branch` | `branches` | `tenant_id`, `name` |
| `Product` | `products` | `tenant_id`, `name`, `sku`, `selling_price`, `cost_price` |
| `ProductVariant` | `product_variants` | `product_id`, `label`, `sku` |
| `StockLevel` | `stock_levels` | `product_id`, `variant_id`, `branch_id`, `quantity`, `reserved_qty` |
| `StockAdjustment` | `stock_adjustments` | `product_id`, reason: purchase/damage/theft/correction/return |
| `Customer` | `customers` | `tenant_id`, `party_id` (nullable FK), `current_balance` (udhaar owed) |
| `CustomerLedgerEntry` | `customer_ledger_entries` | `customer_id`, `sale_id`, `type`, `amount` (int), `running_balance` (int) |
| `Sale` | `sales` | `tenant_id`, `status` (completed/voided/partially_returned/returned), payment breakdown fields |
| `SaleItem` | `sale_items` | `sale_id`, `product_name` snapshot, `unit_price` (int), `line_total` (int) |
| `SaleReturn` | `sale_returns` | `sale_id`, `return_number` (RET-00001), `refund_method` (cash/bank/store_credit), `total_refund` (int) |
| `SaleReturnItem` | `sale_return_items` | `sale_return_id`, `sale_item_id`, `quantity_returned`, `restock` (bool) |
| `Supplier` | `suppliers` | `tenant_id`, `party_id` (nullable FK), `current_balance` (AP owed to them) |
| `PurchaseOrder` | `purchase_orders` | `tenant_id`, `supplier_id`, `status` (draft/pending/partially_received/received/cancelled) |
| `Account` | `accounts` | `tenant_id`, `code`, `type` (asset/liability/equity/revenue/expense), `is_active` |
| `JournalEntry` | `journal_entries` | `tenant_id`, `reference_type`, `reference_id`, `status` (posted) |
| `JournalLine` | `journal_lines` | `journal_entry_id`, `account_id`, `debit`, `credit` |
| `Expense` | `expenses` | `tenant_id`, `account_id` (expense-type account), `expense_number` (EXP-00001), `payment_method` (cash/bank) |
| `Party` | `parties` | `tenant_id`, `is_customer`, `is_supplier` — unified contact overlay |

### Party / Unified Contact Layer
- `parties` table is a transparent overlay — never has money posted directly to it
- Creating a Customer auto-creates a Party; toggling "Also a Supplier" creates a linked Supplier
- `Customer.current_balance` = udhaar (what customer owes us)
- `Supplier.current_balance` = AP (what we owe supplier)
- Net position shown in UI only: `AR − AP`

---

## Accounting (Double-Entry)

### Standard Account Codes (seeded via `DefaultChartOfAccounts`)
| Code | Account | Type |
|---|---|---|
| 1010 | Cash | Asset |
| 1020 | Bank | Asset |
| 1030 | Accounts Receivable | Asset |
| 1040 | Inventory | Asset |
| 2010 | Accounts Payable | Liability |
| 4010 | Sales Revenue | Revenue |
| 5020–5090 | Expenses (Rent, Salary, Electricity, etc.) | Expense |

### `AccountingService` static methods
```php
AccountingService::postSale(Sale)              // Dr Cash/Bank/Receivable, Cr Revenue
AccountingService::reverseSale(Sale)           // swap debit/credit of original entry
AccountingService::postPurchaseReceived(PO)    // Dr Inventory, Cr AP/Cash/Bank
AccountingService::postPurchasePayment(PO, amount, method) // Dr AP, Cr Cash/Bank
AccountingService::postCustomerPayment(...)    // Dr Cash, Cr Receivable
AccountingService::postReturn(SaleReturn)      // Dr Revenue, Cr Cash/Bank/Receivable
AccountingService::postExpense(Expense)        // Dr expense account, Cr Cash/Bank
AccountingService::reverseExpense(Expense)     // swap debit/credit of original entry
```
- If chart of accounts not seeded, methods return silently (no crash)
- `postReturn` refund method: `cash`→Cr 1010, `bank`→Cr 1020, `store_credit`→Cr 1030

---

## File Map

### Backend
```
app/
  Models/
    TenantAware.php          ← abstract base: UUID, soft deletes, tenant scope
    Sale.php / SaleItem.php
    SaleReturn.php / SaleReturnItem.php
    Expense.php
    Customer.php / CustomerLedgerEntry.php
    Supplier.php / Party.php
    PurchaseOrder.php / PurchaseOrderItem.php
    Account.php / JournalEntry.php / JournalLine.php
    StockLevel.php / StockAdjustment.php
    Product.php / ProductVariant.php
  Services/
    AccountingService.php    ← all double-entry journal posting
    ReturnService.php        ← process() handles full/partial returns
    ExpenseService.php       ← create/update/delete with accounting
    SupplierService.php      ← net payable (AP-AR), show data, index rows
    DefaultChartOfAccounts.php ← seeds accounts on first use
  Http/Controllers/
    Pos/PosController.php
    Sales/SalesController.php
    Returns/ReturnController.php
    Customers/CustomersController.php
    Purchasing/SupplierController.php
    Purchasing/PurchaseOrderController.php
    Expenses/ExpenseController.php
    Accounts/AccountsController.php
    Parties/PartyController.php
    Inventory/ProductController.php
    Inventory/StockController.php
    BusinessSettingsController.php
```

### Frontend Pages
```
resources/js/pages/
  Pos/                        ← POS cashier terminal
  Sales/Index.vue             ← sales list + stats + filters
  Sales/Show.vue              ← sale detail + void + process return modal + returns history
  Returns/Index.vue           ← returns list + stats
  Returns/Show.vue            ← return detail
  Customers/Index.vue / Show.vue
  Purchasing/Orders/          ← Create, Index, Show (with receive/pay/cancel)
  Purchasing/Suppliers/       ← Index (AP|AR|Net columns), Show
  Parties/Show.vue            ← net balance view (AR vs AP)
  Expenses/Index.vue          ← stats + filter + table + add/edit modal
  Accounts/Index.vue          ← chart of accounts + journal entries
  Accounts/Ledger.vue
  Accounts/Receivables.vue    ← AR customers + AP suppliers (3-column: AP|AR|Net)
  Accounts/Reports.vue
  Inventory/Products/ / Stock/
```

### Key Shared Frontend Files
```
resources/js/
  utils/format.ts             ← formatMoney(n) → "Rs X,XXX"  formatDateTime(dt)
  constants/badges.ts         ← paymentBadge map for status pills
  composables/useConfirm.ts   ← confirm dialog helper
  composables/useReceipt.ts   ← thermal receipt printer
  components/pos/StatCard.vue ← props: label, value, tone, icon, description
  components/AppSidebar.vue   ← nav groups; soon:true = disabled greyed link
  layouts/AppLayout.vue
```

---

## Routes Summary
```
GET  /pos                           pos.cashier
POST /pos/sales                     pos.sales.store

GET  /sales                         sales.index
GET  /sales/{sale}                  sales.show        ← now includes returns + quantity_returnable
GET  /sales/{sale}/receipt          sales.receipt     (JSON, for printing)
POST /sales/{sale}/void             sales.void
POST /sales/{sale}/returns          sales.returns.store

GET  /returns                       returns.index
GET  /returns/{return}              returns.show

GET  /customers                     customers.index
GET  /customers/{customer}          customers.show
POST /customers/{customer}/enable-supplier   customers.enable-supplier
POST /customers/{customer}/disable-supplier  customers.disable-supplier
POST /customers/{customer}/payment  customers.payment

GET  /parties/{party}               parties.show      ← net balance view

GET  /purchasing/suppliers          purchasing.suppliers.index
GET  /purchasing/suppliers/{id}     purchasing.suppliers.show
POST/PATCH/DELETE /purchasing/suppliers/...

GET  /purchasing/orders             purchasing.orders.index
POST /purchasing/orders             purchasing.orders.store  (mark_received checkbox)
GET  /purchasing/orders/{order}     purchasing.orders.show
POST /purchasing/orders/{order}/receive
POST /purchasing/orders/{order}/pay
POST /purchasing/orders/{order}/cancel

GET  /expenses                      expenses.index
POST /expenses                      expenses.store
PATCH /expenses/{expense}           expenses.update
DELETE /expenses/{expense}          expenses.destroy

GET  /accounts                      accounts.index
GET  /accounts/ledger               accounts.ledger
GET  /accounts/receivables          accounts.receivables
GET  /accounts/reports              accounts.reports
POST /accounts/journal              accounts.store-entry

GET  /inventory/products            inventory.products.index
GET  /inventory/stock               inventory.stock.index
POST /inventory/stock/adjust        inventory.stock.adjust

GET  /business-settings             business-settings.index
```

---

## Pending / Known State

### Migrations not yet run
```bash
php artisan migrate
# Runs: create_sale_returns_table + create_sale_return_items_table
```

### Modules marked `soon: true` in sidebar (not yet built)
- Udhaar Ledger (`/udhaar`)
- Employees (`/employees`)
- duplicate Reports link in Finance group (stale, can be removed)

### Known patterns to follow
- New modules: migration → TenantAware model → Service → thin Controller → Vue page
- Always copy expense/return pattern: DB transaction in service, accounting posted after transaction
- Expense accounts for seeding: codes 5020–5090 via `DefaultChartOfAccounts`
- `SupplierService::indexRow()` shape must stay in sync with `Supplier` TS interface in `Suppliers/Index.vue`
- `SalesController::show()` passes `returns` and `quantity_returnable` per item — keep this in sync if Sale/SaleReturn shape changes

---

## Common Commands
```bash
# Start dev
php artisan serve --port=8001
npm run dev

# Migrations
php artisan migrate
php artisan migrate:status

# Routes
php artisan route:list

# Lint/build
npm run build
```
