<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a form used to delete its submissions in the database, bypassing the legal hold and leaving their uploaded
 * files behind. The database now refuses to delete a form that still has submissions; they are cleared first, one by
 * one, through the submission delete routine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(TableName::resolve('submissions'), static function (Blueprint $table): void {
            $table->dropForeign(['form_id']);
            $table->foreign('form_id')->references('id')->on(TableName::resolve('forms'))->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table(TableName::resolve('submissions'), static function (Blueprint $table): void {
            $table->dropForeign(['form_id']);
            $table->foreign('form_id')->references('id')->on(TableName::resolve('forms'))->cascadeOnDelete();
        });
    }
};
