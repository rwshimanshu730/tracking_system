<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productivity_rules', function (Blueprint $table) {
            $table->id();
            $table->string('match_type', 30);
            $table->string('match_value');
            $table->string('category', 100);
            $table->string('productivity_type', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productivity_rules');
    }
};
