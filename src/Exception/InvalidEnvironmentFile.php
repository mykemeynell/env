<?php

declare(strict_types=1);

namespace mykemeynell\Env\Exception;

use RuntimeException;
use Throwable;

final class InvalidEnvironmentFile extends RuntimeException
{
    /**
     * Report an environment file that cannot be read from disk.
     */
    public static function unreadable(string $filename): self
    {
        return new self(sprintf(
            'Environment file [%s] does not exist or is not readable.',
            $filename,
        ));
    }

    /**
     * Report invalid file contents while retaining the parser failure as context.
     */
    public static function because(
        string $filename,
        string $reason,
        ?Throwable $previous = null,
    ): self {
        return new self(sprintf(
            'Environment file [%s] is invalid: %s',
            $filename,
            $reason,
        ), previous: $previous);
    }
}
