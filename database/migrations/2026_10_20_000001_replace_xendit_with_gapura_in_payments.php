<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Payments moved from Xendit invoices to Gapura (DANA) hosted-checkout orders. No checkout has
| existed yet, so the table is empty: it is rebuilt instead of renamed column by column (MariaDB
| 10.4, as shipped with XAMPP, cannot rename columns).
|
|   xendit_invoice_id → gapura_reference_no   DANA's referenceNo
|   external_id       → partner_reference_no  our order number, sent as partnerReferenceNo
|   invoice_url       → checkout_url          DANA's webRedirectUrl, which can exceed 500 characters
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payments');

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gapura_reference_no', 64)->unique();
            $table->string('partner_reference_no', 64)->index();
            $table->text('checkout_url');
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
};
