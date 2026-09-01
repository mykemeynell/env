<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Tests\Concerns\ManagesEnvironment;

abstract class TestCase extends BaseTestCase
{
    use ManagesEnvironment;
}
