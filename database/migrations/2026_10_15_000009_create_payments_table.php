<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('xendit_invoice_id', 64)->unique();
            $table->string('external_id', 20)->index();
            $table->string('invoice_url', 500);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('paid_amount')->nullable();
            $table->string('status', 10)->default('PENDING');
            $table->string('payment_method', 50)->nullable();
            $table->string('payment_channel', 50)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
