<?php

declare(strict_types=1);

use Capell\FormBuilder\Tests\FormBuilderTestCase;

require_once __DIR__ . '/Support/FrontendSiteHelpers.php';

pest()->extend(FormBuilderTestCase::class)->group('form-builder')->in('.');
