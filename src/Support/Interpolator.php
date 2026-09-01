<?php

declare(strict_types=1);

namespace mykemeynell\Env\Support;

use Dotenv\Repository\RepositoryInterface;

enum Interpolator
{
    /**
     * Replace available variable expressions while preserving unresolved references literally.
     */
    public static function resolve(string $value, RepositoryInterface $repository): string
    {
        return preg_replace_callback(
            '/\$\{([a-zA-Z0-9_.]+)\}/',
            static function (array $matches) use ($repository): string {
                return $repository->get($matches[1]) ?? $matches[0];
            },
            $value,
        ) ?? $value;
    }
}
