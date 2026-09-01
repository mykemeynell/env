<?php

declare(strict_types=1);

namespace mykemeynell\Env\Exception;

use UnexpectedValueException;

final class InvalidEnvironmentValue extends UnexpectedValueException
{
    /**
     * Report a value that cannot be safely represented as the requested type.
     *
     * The value itself is deliberately excluded because environment variables may contain secrets.
     */
    public static function forType(string $name, string $expectedType): self
    {
        return new self(sprintf(
            'Environment variable [%s] cannot be read as %s.',
            $name,
            $expectedType,
        ));
    }
}
