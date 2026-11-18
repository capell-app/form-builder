<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(TableName::resolve('submissions'), static function (Blueprint $table): void {
            $table->string('subject_email_hash', 64)->nullable()->index();
            $table->timestamp('delivery_pending_at')->nullable()->index();
            $table->timestamp('delivery_failed_at')->nullable();
            $table->unsignedSmallInteger('delivery_attempts')->default(0);
            $table->timestamp('notification_queued_at')->nullable();
            $table->timestamp('autoresponder_queued_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table(TableName::resolve('submissions'), static function (Blueprint $table): void {
            $table->dropIndex(['subject_email_hash']);
            $table->dropIndex(['delivery_pending_at']);
            $table->dropColumn([
                'subject_email_hash',
                'delivery_pending_at',
                'delivery_failed_at',
                'delivery_attempts',
                'notification_queued_at',
                'autoresponder_queued_at',
            ]);
        });
    }
};
