<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Erasures fulfilled before submissions recorded when their erasure was asked for kept held submissions with no marker,
 * so releasing those holds would never erase them. Privacy Center lists the ids each request kept; mark those that
 * still exist, so releasing the hold or the prune sweep finishes the erasure.
 *
 * Privacy Center is optional, so its table is read directly and only when it is installed.
 */
return new class extends Migration
{
    private const string RETAINED_KEY = 'retained.form-builder.legal_hold';

    private const string ERASED_KEY = 'retained_erased.form-builder.legal_hold';

    public function up(): void
    {
        $configured = config('capell-privacy-center.tables.privacy_requests');
        $requests = TableName::resolve(is_string($configured) ? $configured : 'privacy_requests');
        $submissions = TableName::resolve('submissions');

        if (! Schema::hasTable($requests) || ! Schema::hasColumn($submissions, 'erasure_requested_at')) {
            return;
        }

        DB::table($requests)->where('metadata', 'like', '%' . self::RETAINED_KEY . '%')->orderBy('id')
            ->select(['id', 'metadata', 'fulfilled_at', 'updated_at'])
            ->chunkById(100, static function ($rows) use ($submissions): void {
                foreach ($rows as $row) {
                    $metadata = is_string($row->metadata) ? json_decode($row->metadata, true) : null;

                    if (! is_array($metadata)) {
                        continue;
                    }

                    $pending = array_diff(self::ids($metadata[self::RETAINED_KEY] ?? null), self::ids($metadata[self::ERASED_KEY] ?? null));

                    if ($pending === []) {
                        continue;
                    }

                    DB::table($submissions)->whereIn('id', array_values($pending))->whereNull('erasure_requested_at')
                        ->update(['erasure_requested_at' => $row->fulfilled_at ?? $row->updated_at ?? now()]);
                }
            });
    }

    public function down(): void
    {
        // The markers cannot be told apart from ones set later, and keeping them only finishes erasures already asked for.
    }

    /** @return list<int> */
    private static function ids(mixed $list): array
    {
        if (! is_string($list)) {
            return [];
        }

        return array_values(array_map(intval(...), array_filter(array_map(trim(...), explode(',', $list)), is_numeric(...))));
    }
};
