<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per Jubelio SKU.
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('jubelio_item_id')->unique();
            $table->string('sku', 100)->unique();
            $table->string('name');
            $table->json('variation_values')->nullable();
            $table->unsignedBigInteger('price')->default(0);
            // Signed: Jubelio can report negative stock; the API shows at least 0.
            $table->integer('available_qty')->default(0);
            $table->unsignedInteger('package_weight')->nullable();
            $table->string('barcode', 100)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->boolean('is_bundle')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('stock_synced_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
