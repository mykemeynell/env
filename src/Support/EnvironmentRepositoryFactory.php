<?php

declare(strict_types=1);

namespace mykemeynell\Env\Support;

enum EnvironmentRepositoryFactory
{
    /**
     * Start an isolated loading session that protects values supplied before it began.
     */
    public static function make(): EnvironmentRepository
    {
        return new EnvironmentRepository;
    }
}
