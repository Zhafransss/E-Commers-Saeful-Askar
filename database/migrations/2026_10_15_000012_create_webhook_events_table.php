<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 10);
            $table->string('event_type', 50);
            // xendit:{id}:{status} | jubelio:{action}:{sha256 body} | invalid:{uuid}
            $table->string('idempotency_key', 191)->unique();
            $table->boolean('signature_valid');
            $table->string('status', 10)->default('received');
            $table->json('payload');
            $table->string('summary')->nullable();
            $table->text('error')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
