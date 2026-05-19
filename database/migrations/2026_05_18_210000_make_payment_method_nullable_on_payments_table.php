<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payments', 'payment_method_id')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE payments DROP FOREIGN KEY payments_payment_method_id_foreign');
        DB::statement('ALTER TABLE payments MODIFY payment_method_id BIGINT UNSIGNED NULL');
        DB::statement(
            'ALTER TABLE payments ADD CONSTRAINT payments_payment_method_id_foreign FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL'
        );
    }

    public function down(): void
    {
        if (! Schema::hasColumn('payments', 'payment_method_id')) {
            return;
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE payments DROP FOREIGN KEY payments_payment_method_id_foreign');
        DB::statement('ALTER TABLE payments MODIFY payment_method_id BIGINT UNSIGNED NOT NULL');
        DB::statement(
            'ALTER TABLE payments ADD CONSTRAINT payments_payment_method_id_foreign FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE CASCADE'
        );
    }
};
