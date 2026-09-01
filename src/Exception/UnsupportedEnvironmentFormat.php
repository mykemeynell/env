<?php

declare(strict_types=1);

namespace mykemeynell\Env\Exception;

use InvalidArgumentException;

final class UnsupportedEnvironmentFormat extends InvalidArgumentException
{
    /**
     * Report a filename that automatic source detection cannot classify.
     */
    public static function forFile(string $filename): self
    {
        return new self(sprintf(
            'Environment file [%s] has an unsupported format; expected dotenv, YAML, or YML.',
            $filename,
        ));
    }
}
