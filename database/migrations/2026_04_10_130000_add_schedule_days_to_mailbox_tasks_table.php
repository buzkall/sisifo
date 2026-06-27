<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailbox_tasks', function(Blueprint $table) {
            $table->json('schedule_days')->nullable()->after('schedule_frequency');
        });

        DB::table('mailbox_tasks')->where('schedule_frequency', 'weekdays')->update([
            'schedule_frequency' => 'daily',
            'schedule_days'      => json_encode([1, 2, 3, 4, 5]),
        ]);

        DB::table('mailbox_tasks')->where('schedule_frequency', 'daily')->whereNull('schedule_days')->update([
            'schedule_days' => json_encode([1, 2, 3, 4, 5, 6, 7]),
        ]);
    }

    public function down(): void
    {
        Schema::table('mailbox_tasks', function(Blueprint $table) {
            $table->dropColumn('schedule_days');
        });
    }
};
