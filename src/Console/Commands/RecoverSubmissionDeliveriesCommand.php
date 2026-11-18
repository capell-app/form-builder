<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Console\Commands;

use Capell\FormBuilder\Actions\RecoverPendingSubmissionDeliveriesAction;
use Capell\FormBuilder\Models\Submission;
use Illuminate\Console\Command;

final class RecoverSubmissionDeliveriesCommand extends Command
{
    protected $signature = 'capell:form-builder:recover-deliveries {--grace=5 : Minutes a delivery may stay pending before it is retried}';

    protected $description = 'Retry form notification and autoresponder mail that failed to queue';

    public function handle(): int
    {
        $grace = $this->option('grace');
        $recovered = RecoverPendingSubmissionDeliveriesAction::run(is_numeric($grace) ? (int) $grace : 5);
        $stillFailing = Submission::query()->whereNotNull('delivery_failed_at')->whereNotNull('delivery_pending_at')->count();

        $this->info((string) __('capell-form-builder::messages.delivery_recovery_result', [
            'recovered' => $recovered,
            'failing' => $stillFailing,
        ]));

        return $stillFailing > 0 ? self::FAILURE : self::SUCCESS;
    }
}
