<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! TableName::existsAndIsOwned('submissions', '2026_05_10_190849_02_create_submissions_table')) {
            throw new LogicException('The owned submissions table is required for payment rail selection.');
        }

        $submissions = TableName::resolve('submissions');
        $connection = DB::connection();
        $existing = collect(Schema::getColumns($submissions))->firstWhere('name', 'payment_rail');
        if ($existing !== null) {
            $expectedType = $connection->getDriverName() === 'pgsql' ? 'character varying(64)' : 'varchar(64)';
            $default = $existing['default'];
            $implicitNull = $default === null || ($connection instanceof MySqlConnection && $connection->isMaria() && $default === 'NULL');
            if (strtolower($existing['type']) !== $expectedType || ! $existing['nullable']
                || ! $implicitNull || $existing['auto_increment'] || $existing['generation'] !== null) {
                throw new LogicException('The retained form payment rail column has an unexpected shape.');
            }

            return;
        }

        if ($connection->getDriverName() === 'sqlite') {
            // SQLite's schema builder omits the declared width from string columns.
            $table = $connection->getQueryGrammar()->wrapTable($submissions);
            DB::statement("ALTER TABLE {$table} ADD COLUMN payment_rail VARCHAR(64) NULL");

            return;
        }

        Schema::table($submissions, static function (Blueprint $table): void {
            $table->string('payment_rail', 64)->nullable();
        });
    }

    public function down(): void
    {
        // Keep the original rail for every retained submission, including concurrent writes.
        // A full rollback still removes the column with the owning submissions table.
    }
};
