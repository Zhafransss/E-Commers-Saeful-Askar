<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot of each variant at checkout time; unaffected by later catalog changes.
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('jubelio_item_id');
            $table->string('sku', 100);
            $table->string('product_name');
            $table->string('variant_name')->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('unit', 30)->default('Unit');
            $table->unsignedBigInteger('price');
            $table->unsignedSmallInteger('qty');
            // Weight per unit, in grams.
            $table->unsignedInteger('weight_gram')->default(0);
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
