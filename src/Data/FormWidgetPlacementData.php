<?php

declare(strict_types=1);

namespace Capell\FormBuilder\Data;

/**
 * The form a Layout Builder widget occurrence shows: the form handle chosen in
 * the widget's settings and a stable, public-safe instance id for its markup.
 */
final readonly class FormWidgetPlacementData
{
    public function __construct(
        public string $handle,
        public string $instanceId,
    ) {}
}
