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
        Schema::create(TableName::resolve('submissions'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->constrained(TableName::resolve('forms'))->cascadeOnDelete();
            $table->foreignId('site_id')->constrained(TableName::resolve('sites'))->cascadeOnDelete();
            $table->longText('payload')->nullable();
            $table->longText('meta')->nullable();
            $table->string('status')->default('new');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('form_id');
            $table->index('site_id');
            $table->index('status');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableName::resolve('submissions'));
    }
};
