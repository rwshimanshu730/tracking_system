<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('website_logs')) {
            DB::statement('ALTER TABLE website_logs MODIFY domain VARCHAR(191) NOT NULL');

            $domainIndex = DB::select("SHOW INDEX FROM website_logs WHERE Key_name = 'website_logs_domain_index'");
            if ($domainIndex === []) {
                DB::statement('ALTER TABLE website_logs ADD INDEX website_logs_domain_index (domain)');
            }

            $recordedOnIndex = DB::select("SHOW INDEX FROM website_logs WHERE Key_name = 'website_logs_recorded_on_index'");
            if ($recordedOnIndex === []) {
                DB::statement('ALTER TABLE website_logs ADD INDEX website_logs_recorded_on_index (recorded_on)');
            }

            return;
        }

        Schema::create('website_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_session_id')->constrained()->cascadeOnDelete();
            $table->string('browser_name', 120);
            $table->string('page_title')->nullable();
            $table->text('url');
            $table->string('domain', 191)->index();
            $table->string('category', 100)->nullable();
            $table->boolean('is_productive')->default(true);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->date('recorded_on')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_logs');
    }
};
