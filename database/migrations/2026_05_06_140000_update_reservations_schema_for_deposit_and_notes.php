<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('deposit_amount', 12, 2)->default(0)->after('status');
        });

        DB::table('reservations')
            ->select(['id', 'special_requests', 'notes'])
            ->orderBy('id')
            ->chunkById(100, function ($reservations): void {
                foreach ($reservations as $reservation) {
                    $mergedNotes = collect([
                        $reservation->special_requests,
                        $reservation->notes,
                    ])
                        ->filter(fn ($value) => filled($value))
                        ->map(fn ($value) => trim((string) $value))
                        ->implode(PHP_EOL . PHP_EOL);

                    DB::table('reservations')
                        ->where('id', $reservation->id)
                        ->update([
                            'notes' => $mergedNotes !== '' ? $mergedNotes : null,
                        ]);
                }
            });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['source', 'special_requests']);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->string('source')->nullable()->after('status');
            $table->text('special_requests')->nullable()->after('source');
            $table->dropColumn('deposit_amount');
        });
    }
};
