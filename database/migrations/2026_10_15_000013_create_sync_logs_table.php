<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 20);
            $table->string('trigger', 10)->default('schedule');
            $table->string('status', 10)->default('running');
            // orders.id for order_* types; no FK on purpose (light polymorphic reference).
            $table->unsignedBigInteger('reference_id')->nullable()->index();
            $table->unsignedInteger('items_processed')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
