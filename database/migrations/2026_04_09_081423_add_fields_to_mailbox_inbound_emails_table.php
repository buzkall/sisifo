<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mailbox_inbound_emails')) {
            throw new RuntimeException(
                'mailbox_inbound_emails table is missing. Publish beyondcode/laravel-mailbox migrations first: '
                . 'php artisan vendor:publish --provider="BeyondCode\\Mailbox\\MailboxServiceProvider" --tag="migrations"'
            );
        }

        Schema::table('mailbox_inbound_emails', function(Blueprint $table) {
            $table->string('subject')->nullable()->after('message_id');
            $table->string('from_address')->nullable()->after('subject');
            $table->string('from_name')->nullable()->after('from_address');
            $table->longText('message')->nullable()->change();
            $table->longText('text_body')->nullable()->after('message');
            $table->timestamp('received_at')->nullable()->after('text_body');
            $table->boolean('summarized')->default(false)->after('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('mailbox_inbound_emails', function(Blueprint $table) {
            $table->dropColumn(['subject', 'from_address', 'from_name', 'text_body', 'received_at', 'summarized']);
            $table->longText('message')->nullable(false)->change();
        });
    }
};
