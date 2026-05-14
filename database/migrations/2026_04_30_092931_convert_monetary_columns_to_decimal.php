<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert all monetary columns from bigInteger paisa to decimal(12,2) PKR.
        // MySQL: MODIFY COLUMN changes the type first (bigint → decimal), then UPDATE scales the values.

        // products
        DB::statement('ALTER TABLE products
            MODIFY COLUMN cost_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN selling_price DECIMAL(12,2) NOT NULL DEFAULT 0');
        DB::statement('UPDATE products SET cost_price = cost_price / 100, selling_price = selling_price / 100');

        // product_variants
        DB::statement('ALTER TABLE product_variants
            MODIFY COLUMN cost_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN selling_price DECIMAL(12,2) NOT NULL DEFAULT 0');
        DB::statement('UPDATE product_variants SET cost_price = cost_price / 100, selling_price = selling_price / 100');

        // customers
        DB::statement('ALTER TABLE customers
            MODIFY COLUMN current_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN credit_limit    DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN total_spend     DECIMAL(12,2) NOT NULL DEFAULT 0');
        DB::statement('UPDATE customers SET current_balance = current_balance / 100, credit_limit = credit_limit / 100, total_spend = total_spend / 100');

        // sales
        DB::statement('ALTER TABLE sales
            MODIFY COLUMN subtotal         DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN discount         DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN tax              DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN total            DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN paid             DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN change_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN cash_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN jazzcash_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN easypaisa_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN udhaar_amount    DECIMAL(12,2) NOT NULL DEFAULT 0');
        DB::statement('UPDATE sales SET
            subtotal         = subtotal / 100,
            discount         = discount / 100,
            tax              = tax / 100,
            total            = total / 100,
            paid             = paid / 100,
            change_amount    = change_amount / 100,
            cash_amount      = cash_amount / 100,
            jazzcash_amount  = jazzcash_amount / 100,
            easypaisa_amount = easypaisa_amount / 100,
            udhaar_amount    = udhaar_amount / 100');

        // sale_items
        DB::statement('ALTER TABLE sale_items
            MODIFY COLUMN unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN discount   DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN line_total DECIMAL(12,2) NOT NULL DEFAULT 0');
        DB::statement('UPDATE sale_items SET unit_price = unit_price / 100, cost_price = cost_price / 100, discount = discount / 100, line_total = line_total / 100');

        // customer_ledger_entries
        DB::statement('ALTER TABLE customer_ledger_entries
            MODIFY COLUMN amount          DECIMAL(12,2) NOT NULL DEFAULT 0,
            MODIFY COLUMN running_balance DECIMAL(12,2) NOT NULL DEFAULT 0');
        DB::statement('UPDATE customer_ledger_entries SET amount = amount / 100, running_balance = running_balance / 100');
    }

    public function down(): void
    {
        // Reverse: scale back by 100 then change type back to BIGINT.

        DB::statement('UPDATE products SET cost_price = ROUND(cost_price * 100), selling_price = ROUND(selling_price * 100)');
        DB::statement('ALTER TABLE products
            MODIFY COLUMN cost_price    BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN selling_price BIGINT NOT NULL DEFAULT 0');

        DB::statement('UPDATE product_variants SET cost_price = ROUND(cost_price * 100), selling_price = ROUND(selling_price * 100)');
        DB::statement('ALTER TABLE product_variants
            MODIFY COLUMN cost_price    BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN selling_price BIGINT NOT NULL DEFAULT 0');

        DB::statement('UPDATE customers SET current_balance = ROUND(current_balance * 100), credit_limit = ROUND(credit_limit * 100), total_spend = ROUND(total_spend * 100)');
        DB::statement('ALTER TABLE customers
            MODIFY COLUMN current_balance BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN credit_limit    BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN total_spend     BIGINT NOT NULL DEFAULT 0');

        DB::statement('UPDATE sales SET
            subtotal         = ROUND(subtotal * 100),
            discount         = ROUND(discount * 100),
            tax              = ROUND(tax * 100),
            total            = ROUND(total * 100),
            paid             = ROUND(paid * 100),
            change_amount    = ROUND(change_amount * 100),
            cash_amount      = ROUND(cash_amount * 100),
            jazzcash_amount  = ROUND(jazzcash_amount * 100),
            easypaisa_amount = ROUND(easypaisa_amount * 100),
            udhaar_amount    = ROUND(udhaar_amount * 100)');
        DB::statement('ALTER TABLE sales
            MODIFY COLUMN subtotal         BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN discount         BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN tax              BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN total            BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN paid             BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN change_amount    BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN cash_amount      BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN jazzcash_amount  BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN easypaisa_amount BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN udhaar_amount    BIGINT NOT NULL DEFAULT 0');

        DB::statement('UPDATE sale_items SET unit_price = ROUND(unit_price * 100), cost_price = ROUND(cost_price * 100), discount = ROUND(discount * 100), line_total = ROUND(line_total * 100)');
        DB::statement('ALTER TABLE sale_items
            MODIFY COLUMN unit_price BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN cost_price BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN discount   BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN line_total BIGINT NOT NULL DEFAULT 0');

        DB::statement('UPDATE customer_ledger_entries SET amount = ROUND(amount * 100), running_balance = ROUND(running_balance * 100)');
        DB::statement('ALTER TABLE customer_ledger_entries
            MODIFY COLUMN amount          BIGINT NOT NULL DEFAULT 0,
            MODIFY COLUMN running_balance BIGINT NOT NULL DEFAULT 0');
    }
};
