<?php

declare(strict_types=1);

namespace mykemeynell\Env\Support;

final readonly class EnvironmentValue
{
    /**
     * Represent the result of looking in every supported environment location.
     */
    private function __construct(
        public bool $exists,
        public mixed $value,
    ) {}

    /**
     * Represent an environment name that is not defined by any reader.
     */
    public static function missing(): self
    {
        return new self(false, null);
    }

    /**
     * Retain an environment value while distinguishing a present null from a missing name.
     */
    public static function present(mixed $value): self
    {
        return new self(true, $value);
    }
}
