<?php

declare(strict_types=1);

namespace mykemeynell\Env\Support;

use Dotenv\Repository\RepositoryInterface;
use InvalidArgumentException;

final class EnvironmentRepository implements RepositoryInterface
{
    /** @var array<string, true> */
    private array $loaded = [];

    /**
     * Determine whether an environment name exists in any supported location.
     *
     * @throws InvalidArgumentException When the environment name is empty.
     */
    #[\Override]
    public function has(string $name): bool
    {
        return EnvironmentLookup::find($name)->exists;
    }

    /**
     * Read an environment value in the string form required for interpolation.
     *
     * Native YAML scalars are converted only for this read boundary. Unsupported host values
     * are treated as unavailable for interpolation while remaining protected from writes.
     *
     * @throws InvalidArgumentException When the environment name is empty.
     */
    #[\Override]
    public function get(string $name): ?string
    {
        $environment = EnvironmentLookup::find($name);

        if (! $environment->exists) {
            return null;
        }

        return self::stringify($environment->value);
    }

    /**
     * Publish a dotenv string to both safe PHP environment globals.
     *
     * @throws InvalidArgumentException When the environment name is empty.
     */
    #[\Override]
    public function set(string $name, string $value): bool
    {
        return $this->setNative($name, $value);
    }

    /**
     * Publish a native environment scalar unless it existed before this load session.
     *
     * @throws InvalidArgumentException When the environment name is empty.
     */
    public function setNative(string $name, bool|float|int|string|null $value): bool
    {
        if ($this->isExternallyDefined($name)) {
            return false;
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        $this->loaded[$name] = true;

        return true;
    }

    /**
     * Remove a dotenv entry unless it was supplied before this load session.
     *
     * @throws InvalidArgumentException When the environment name is empty.
     */
    #[\Override]
    public function clear(string $name): bool
    {
        if ($this->isExternallyDefined($name)) {
            return false;
        }

        unset($_ENV[$name], $_SERVER[$name], $this->loaded[$name]);

        return true;
    }

    /**
     * Determine whether a value belongs to the host rather than the current load session.
     */
    private function isExternallyDefined(string $name): bool
    {
        return EnvironmentLookup::find($name)->exists && ! isset($this->loaded[$name]);
    }

    /**
     * Convert native scalars into the deterministic strings used by interpolation.
     */
    private static function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => '',
            $value === true => 'true',
            $value === false => 'false',
            is_string($value), is_int($value), is_float($value) => (string) $value,
            default => null,
        };
    }
}
