<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/*
| The blog was dropped from the product scope. The original create_articles_table migration was
| removed, so databases that already ran it lose the table here; fresh databases never had it.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('articles');
    }

    public function down(): void
    {
        // Intentionally empty: the feature no longer exists, so there is nothing to restore.
    }
};
