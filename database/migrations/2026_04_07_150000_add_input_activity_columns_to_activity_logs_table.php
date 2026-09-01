<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->unsignedSmallInteger('keyboard_events')->default(0)->after('duration_seconds');
            $table->unsignedSmallInteger('mouse_events')->default(0)->after('keyboard_events');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn(['keyboard_events', 'mouse_events']);
        });
    }
};
