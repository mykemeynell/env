<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

afterEach(function (): void {
    $this->restoreEnvironment();
})->in('Feature', 'Unit');
