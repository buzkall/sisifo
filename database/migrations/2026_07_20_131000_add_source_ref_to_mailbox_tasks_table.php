<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailbox_tasks', function(Blueprint $table) {
            // Which origin a non-email task draws from (e.g. `owner/repo` for a
            // github_releases task). Null for the default inbound_email source.
            $table->string('source_ref')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('mailbox_tasks', function(Blueprint $table) {
            $table->dropColumn('source_ref');
        });
    }
};
