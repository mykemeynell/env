<?php

declare(strict_types=1);

namespace mykemeynell\Env\Source\Concerns;

use mykemeynell\Env\Exception\InvalidEnvironmentFile;

trait ReadsEnvironmentFiles
{
    /**
     * Read an environment file after verifying that it exists and is accessible.
     *
     * @throws InvalidEnvironmentFile When the requested file cannot be read.
     */
    private static function readEnvironmentFile(string $filename): string
    {
        if (! is_file($filename) || ! is_readable($filename)) {
            throw InvalidEnvironmentFile::unreadable($filename);
        }

        $contents = file_get_contents($filename);

        // The file may disappear between the readability check and the read operation.
        // @codeCoverageIgnoreStart
        if ($contents === false) {
            throw InvalidEnvironmentFile::unreadable($filename);
        }
        // @codeCoverageIgnoreEnd

        return $contents;
    }
}
