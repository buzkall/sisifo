<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailbox_tasks', function(Blueprint $table) {
            $table->boolean('is_urgent')->default(false)->after('notification_method');
        });
    }

    public function down(): void
    {
        Schema::table('mailbox_tasks', function(Blueprint $table) {
            $table->dropColumn('is_urgent');
        });
    }
};
