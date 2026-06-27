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
            $table->json('notification_methods')->nullable()->after('prompt');
        });

        DB::table('mailbox_tasks')->orderBy('id')->each(function($task) {
            DB::table('mailbox_tasks')
                ->where('id', $task->id)
                ->update([
                    'notification_methods' => json_encode([$task->notification_method]),
                ]);
        });

        Schema::table('mailbox_tasks', function(Blueprint $table) {
            $table->dropColumn('notification_method');
        });
    }

    public function down(): void
    {
        Schema::table('mailbox_tasks', function(Blueprint $table) {
            $table->string('notification_method')->nullable()->after('prompt');
        });

        DB::table('mailbox_tasks')->orderBy('id')->each(function($task) {
            $methods = json_decode($task->notification_methods ?? '[]', true);

            DB::table('mailbox_tasks')
                ->where('id', $task->id)
                ->update([
                    'notification_method' => $methods[0] ?? 'pushover',
                ]);
        });

        Schema::table('mailbox_tasks', function(Blueprint $table) {
            $table->dropColumn('notification_methods');
        });
    }
};
