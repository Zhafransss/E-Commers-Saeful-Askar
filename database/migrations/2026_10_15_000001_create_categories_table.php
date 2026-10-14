<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('jubelio_category_id')->unique();
            // Jubelio parent_id 0 (root) is stored as NULL.
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 150);
            // Generated once; never changes when the name changes in Jubelio.
            $table->string('slug', 170)->unique();
            // False when removed in Jubelio but still referenced by products.
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
