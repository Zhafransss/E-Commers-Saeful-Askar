<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label', 50)->nullable();
            $table->string('recipient_name', 100);
            $table->string('phone', 15);
            $table->string('address_line');
            // Jubelio Region IDs (strings); names are filled by the backend, not the client.
            $table->string('province_id', 2);
            $table->string('province_name', 100);
            $table->string('city_id', 4);
            $table->string('city_name', 100);
            $table->string('district_id', 6);
            $table->string('district_name', 100);
            $table->string('subdistrict_id', 10);
            $table->string('subdistrict_name', 100);
            $table->char('post_code', 5);
            // Exactly one default per user, enforced in a transaction.
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
