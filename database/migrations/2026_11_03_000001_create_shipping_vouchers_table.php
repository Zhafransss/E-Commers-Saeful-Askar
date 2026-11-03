<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping vouchers (docs/erd.md `shipping_vouchers`, revised scope 3 Nov 2026): a voucher only lowers the
 * shipping cost, never the product prices or the insurance. An order keeps the voucher's name and the discount
 * it got, so editing or deleting a voucher later does not change orders already placed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // free_shipping = the whole shipping cost; shipping_discount = at most `amount` off it
            $table->string('type', 20);
            $table->unsignedBigInteger('amount')->nullable();
            $table->unsignedBigInteger('min_subtotal')->default(0);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            // null = no limit
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_voucher_id')->nullable()->after('discount')->constrained('shipping_vouchers')->nullOnDelete();
            $table->string('shipping_voucher_name', 100)->nullable()->after('shipping_voucher_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_voucher_id');
            $table->dropColumn('shipping_voucher_name');
        });

        Schema::dropIfExists('shipping_vouchers');
    }
};
