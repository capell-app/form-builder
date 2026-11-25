<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Contracts;

use Capell\FormBuilder\Models\Submission;

/** Optional payment rail bound by an installed integration. */
interface FormPaymentCheckoutRail
{
    /** Stable lowercase identifier persisted with a newly selected submission. */
    public function key(): string;

    /** Evaluate local policy against a validated, unsaved submission. No provider I/O. */
    public function selects(Submission $submission): bool;

    /** Start or resume checkout for the persisted submission. Null means unavailable. */
    public function checkoutUrl(Submission $submission): ?string;
}
