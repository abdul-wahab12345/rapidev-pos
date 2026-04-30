<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Convert all monetary columns from bigInteger paisa to decimal(12,2) PKR.
        // PostgreSQL USING clause divides existing integer values by 100 in-place.

        DB::statement('ALTER TABLE products
            ALTER COLUMN cost_price     TYPE DECIMAL(12,2) USING cost_price::DECIMAL / 100,
            ALTER COLUMN selling_price  TYPE DECIMAL(12,2) USING selling_price::DECIMAL / 100');

        DB::statement('ALTER TABLE product_variants
            ALTER COLUMN cost_price     TYPE DECIMAL(12,2) USING cost_price::DECIMAL / 100,
            ALTER COLUMN selling_price  TYPE DECIMAL(12,2) USING selling_price::DECIMAL / 100');

        DB::statement('ALTER TABLE customers
            ALTER COLUMN current_balance TYPE DECIMAL(12,2) USING current_balance::DECIMAL / 100,
            ALTER COLUMN credit_limit    TYPE DECIMAL(12,2) USING credit_limit::DECIMAL / 100,
            ALTER COLUMN total_spend     TYPE DECIMAL(12,2) USING total_spend::DECIMAL / 100');

        DB::statement('ALTER TABLE sales
            ALTER COLUMN subtotal        TYPE DECIMAL(12,2) USING subtotal::DECIMAL / 100,
            ALTER COLUMN discount        TYPE DECIMAL(12,2) USING discount::DECIMAL / 100,
            ALTER COLUMN tax             TYPE DECIMAL(12,2) USING tax::DECIMAL / 100,
            ALTER COLUMN total           TYPE DECIMAL(12,2) USING total::DECIMAL / 100,
            ALTER COLUMN paid            TYPE DECIMAL(12,2) USING paid::DECIMAL / 100,
            ALTER COLUMN change_amount   TYPE DECIMAL(12,2) USING change_amount::DECIMAL / 100,
            ALTER COLUMN cash_amount     TYPE DECIMAL(12,2) USING cash_amount::DECIMAL / 100,
            ALTER COLUMN jazzcash_amount TYPE DECIMAL(12,2) USING jazzcash_amount::DECIMAL / 100,
            ALTER COLUMN easypaisa_amount TYPE DECIMAL(12,2) USING easypaisa_amount::DECIMAL / 100,
            ALTER COLUMN udhaar_amount   TYPE DECIMAL(12,2) USING udhaar_amount::DECIMAL / 100');

        DB::statement('ALTER TABLE sale_items
            ALTER COLUMN unit_price  TYPE DECIMAL(12,2) USING unit_price::DECIMAL / 100,
            ALTER COLUMN cost_price  TYPE DECIMAL(12,2) USING cost_price::DECIMAL / 100,
            ALTER COLUMN discount    TYPE DECIMAL(12,2) USING discount::DECIMAL / 100,
            ALTER COLUMN line_total  TYPE DECIMAL(12,2) USING line_total::DECIMAL / 100');

        DB::statement('ALTER TABLE customer_ledger_entries
            ALTER COLUMN amount          TYPE DECIMAL(12,2) USING amount::DECIMAL / 100,
            ALTER COLUMN running_balance TYPE DECIMAL(12,2) USING running_balance::DECIMAL / 100');
    }

    public function down(): void
    {
        // Reverse: multiply back by 100 and cast to bigint
        DB::statement('ALTER TABLE products
            ALTER COLUMN cost_price     TYPE BIGINT USING ROUND(cost_price * 100)::BIGINT,
            ALTER COLUMN selling_price  TYPE BIGINT USING ROUND(selling_price * 100)::BIGINT');

        DB::statement('ALTER TABLE product_variants
            ALTER COLUMN cost_price     TYPE BIGINT USING ROUND(cost_price * 100)::BIGINT,
            ALTER COLUMN selling_price  TYPE BIGINT USING ROUND(selling_price * 100)::BIGINT');

        DB::statement('ALTER TABLE customers
            ALTER COLUMN current_balance TYPE BIGINT USING ROUND(current_balance * 100)::BIGINT,
            ALTER COLUMN credit_limit    TYPE BIGINT USING ROUND(credit_limit * 100)::BIGINT,
            ALTER COLUMN total_spend     TYPE BIGINT USING ROUND(total_spend * 100)::BIGINT');

        DB::statement('ALTER TABLE sales
            ALTER COLUMN subtotal        TYPE BIGINT USING ROUND(subtotal * 100)::BIGINT,
            ALTER COLUMN discount        TYPE BIGINT USING ROUND(discount * 100)::BIGINT,
            ALTER COLUMN tax             TYPE BIGINT USING ROUND(tax * 100)::BIGINT,
            ALTER COLUMN total           TYPE BIGINT USING ROUND(total * 100)::BIGINT,
            ALTER COLUMN paid            TYPE BIGINT USING ROUND(paid * 100)::BIGINT,
            ALTER COLUMN change_amount   TYPE BIGINT USING ROUND(change_amount * 100)::BIGINT,
            ALTER COLUMN cash_amount     TYPE BIGINT USING ROUND(cash_amount * 100)::BIGINT,
            ALTER COLUMN jazzcash_amount TYPE BIGINT USING ROUND(jazzcash_amount * 100)::BIGINT,
            ALTER COLUMN easypaisa_amount TYPE BIGINT USING ROUND(easypaisa_amount * 100)::BIGINT,
            ALTER COLUMN udhaar_amount   TYPE BIGINT USING ROUND(udhaar_amount * 100)::BIGINT');

        DB::statement('ALTER TABLE sale_items
            ALTER COLUMN unit_price  TYPE BIGINT USING ROUND(unit_price * 100)::BIGINT,
            ALTER COLUMN cost_price  TYPE BIGINT USING ROUND(cost_price * 100)::BIGINT,
            ALTER COLUMN discount    TYPE BIGINT USING ROUND(discount * 100)::BIGINT,
            ALTER COLUMN line_total  TYPE BIGINT USING ROUND(line_total * 100)::BIGINT');

        DB::statement('ALTER TABLE customer_ledger_entries
            ALTER COLUMN amount          TYPE BIGINT USING ROUND(amount * 100)::BIGINT,
            ALTER COLUMN running_balance TYPE BIGINT USING ROUND(running_balance * 100)::BIGINT');
    }
};
