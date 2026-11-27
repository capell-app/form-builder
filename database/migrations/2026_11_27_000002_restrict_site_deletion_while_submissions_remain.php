<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a site used to delete the submissions recorded against it in the database, past the legal hold and the
 * model hooks, leaving their uploaded files behind. The database now refuses to delete a site that still has
 * submissions; Form Builder clears them first, one by one, through the submission delete routine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(TableName::resolve('submissions'), static function (Blueprint $table): void {
            $table->dropForeign(['site_id']);
            $table->foreign('site_id')->references('id')->on(TableName::resolve('sites'))->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table(TableName::resolve('submissions'), static function (Blueprint $table): void {
            $table->dropForeign(['site_id']);
            $table->foreign('site_id')->references('id')->on(TableName::resolve('sites'))->cascadeOnDelete();
        });
    }
};
