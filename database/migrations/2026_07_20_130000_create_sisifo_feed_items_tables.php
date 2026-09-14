<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sisifo_feed_items', function(Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('title');
            $table->string('url');
            $table->string('source_ref')->index();
            $table->longText('body');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        // Own pivot mirroring `mailbox_task_inbound_email`'s shape. Kept separate
        // per source on purpose; unifying the pivots polymorphically is deferred.
        Schema::create('mailbox_task_feed_item', function(Blueprint $table) {
            $table->id();
            $table->foreignId('mailbox_task_id')->constrained('mailbox_tasks')->cascadeOnDelete();
            $table->foreignId('feed_item_id')->constrained('sisifo_feed_items')->cascadeOnDelete();
            $table->timestamp('processed_at');

            $table->unique(['mailbox_task_id', 'feed_item_id'], 'task_feed_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailbox_task_feed_item');
        Schema::dropIfExists('sisifo_feed_items');
    }
};
