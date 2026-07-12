<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submissions', static function (Blueprint $table): void {
            $table->boolean('legal_hold')->default(false)->index();
            $table->timestamp('retention_until')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', static function (Blueprint $table): void {
            $table->dropColumn(['legal_hold', 'retention_until']);
        });
    }
};
