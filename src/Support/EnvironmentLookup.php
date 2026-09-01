<?php

declare(strict_types=1);

namespace mykemeynell\Env\Support;

use InvalidArgumentException;

enum EnvironmentLookup
{
    /**
     * Find an environment value using the package's deterministic reader precedence.
     *
     * Server globals win over environment globals, which win over the process environment.
     *
     * @throws InvalidArgumentException When the environment name is empty.
     */
    public static function find(string $name): EnvironmentValue
    {
        if ($name === '') {
            throw new InvalidArgumentException('Expected the environment name to be a non-empty string.');
        }

        if (array_key_exists($name, $_SERVER)) {
            return EnvironmentValue::present($_SERVER[$name]);
        }

        if (array_key_exists($name, $_ENV)) {
            return EnvironmentValue::present($_ENV[$name]);
        }

        $processValue = getenv($name);

        return $processValue === false
            ? EnvironmentValue::missing()
            : EnvironmentValue::present($processValue);
    }
}
