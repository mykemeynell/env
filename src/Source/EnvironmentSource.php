<?php

declare(strict_types=1);

namespace mykemeynell\Env\Source;

use mykemeynell\Env\Support\EnvironmentRepository;

interface EnvironmentSource
{
    /**
     * Resolve and write this source through the shared environment repository.
     *
     * @return array<string, bool|float|int|string|null>
     */
    public function load(EnvironmentRepository $repository): array;
}
