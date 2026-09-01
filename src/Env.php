<?php

declare(strict_types=1);

namespace mykemeynell\Env;

use InvalidArgumentException;
use mykemeynell\Env\Exception\InvalidEnvironmentFile;
use mykemeynell\Env\Exception\InvalidEnvironmentValue;
use mykemeynell\Env\Exception\UnsupportedEnvironmentFormat;
use mykemeynell\Env\Source\DotenvSource;
use mykemeynell\Env\Source\EnvironmentSource;
use mykemeynell\Env\Source\YamlSource;
use mykemeynell\Env\Support\EnvironmentLookup;
use mykemeynell\Env\Support\EnvironmentRepositoryFactory;

final readonly class Env
{
    /**
     * Hold the validated sources until the caller explicitly loads them.
     *
     * @param  list<EnvironmentSource>  $sources
     */
    private function __construct(
        private array $sources,
    ) {}

    /**
     * Create an environment loader from one dotenv file without changing the environment.
     *
     * @throws InvalidEnvironmentFile When the file cannot be parsed.
     */
    public static function fromDotenv(string $filename): self
    {
        return new self([
            DotenvSource::fromFile($filename),
        ]);
    }

    /**
     * Create an environment loader from one YAML file without changing the environment.
     *
     * @throws InvalidEnvironmentFile When the file cannot be parsed.
     */
    public static function fromYaml(string $filename): self
    {
        return new self([
            YamlSource::fromFile($filename),
        ]);
    }

    /**
     * Create a layered loader by detecting each file format from its filename.
     *
     * @throws InvalidArgumentException When no filenames are supplied.
     * @throws InvalidEnvironmentFile When any file cannot be parsed.
     * @throws UnsupportedEnvironmentFormat When a filename has no supported environment suffix.
     */
    public static function fromFiles(string ...$filenames): self
    {
        if ($filenames === []) {
            throw new InvalidArgumentException('Environment composition requires at least one file.');
        }

        return new self(array_values(array_map(
            self::sourceForFile(...),
            $filenames,
        )));
    }

    /**
     * Load all sources into safe PHP globals and report the values that were applied.
     *
     * Host-provided values remain unchanged. Later sources may replace values written by
     * earlier sources during this load operation.
     *
     * @return array<string, bool|float|int|string|null>
     */
    public function load(): array
    {
        $repository = EnvironmentRepositoryFactory::make();
        $loaded = [];

        foreach ($this->sources as $source) {
            foreach ($source->load($repository) as $name => $value) {
                $loaded[$name] = $value;
            }
        }

        return $loaded;
    }

    /**
     * Read an environment value through the package's consistent lookup and casting rules.
     *
     * Native YAML scalars remain typed. Dotenv strings use Laravel-style casting for explicit
     * boolean, empty, null, and quoted values; numeric strings remain strings.
     *
     * @throws InvalidArgumentException When the environment name is empty.
     */
    public static function get(string $name, mixed $default = null): mixed
    {
        $environment = EnvironmentLookup::find($name);

        if (! $environment->exists) {
            return $default;
        }

        if (! is_string($environment->value)) {
            return $environment->value;
        }

        return self::castString($environment->value);
    }

    /**
     * Read a native or explicitly written boolean without accepting numeric truthiness.
     *
     * @throws InvalidArgumentException When the environment name is empty.
     * @throws InvalidEnvironmentValue When a present value is not an explicit boolean.
     */
    public static function bool(string $name, ?bool $default = null): ?bool
    {
        $environment = EnvironmentLookup::find($name);

        if (! $environment->exists) {
            return $default;
        }

        if ($environment->value === null || is_bool($environment->value)) {
            return $environment->value;
        }

        return match ($environment->value) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            default => throw InvalidEnvironmentValue::forType($name, 'boolean'),
        };
    }

    /**
     * Read a native integer or safely convert a signed base-10 integer string.
     *
     * @throws InvalidArgumentException When the environment name is empty.
     * @throws InvalidEnvironmentValue When a present value is not a valid platform integer.
     */
    public static function int(string $name, ?int $default = null): ?int
    {
        $environment = EnvironmentLookup::find($name);

        if (! $environment->exists) {
            return $default;
        }

        if ($environment->value === null || is_int($environment->value)) {
            return $environment->value;
        }

        if (is_string($environment->value)) {
            $integer = self::integerFromString($environment->value);

            if ($integer !== null) {
                return $integer;
            }
        }

        throw InvalidEnvironmentValue::forType($name, 'integer');
    }

    /**
     * Read a finite native number or safely convert a finite numeric string.
     *
     * @throws InvalidArgumentException When the environment name is empty.
     * @throws InvalidEnvironmentValue When a present value is not a finite number.
     */
    public static function float(string $name, ?float $default = null): ?float
    {
        $environment = EnvironmentLookup::find($name);

        if (! $environment->exists) {
            return $default;
        }

        if ($environment->value === null) {
            return null;
        }

        if (is_int($environment->value) || is_float($environment->value)) {
            $float = (float) $environment->value;

            if (is_finite($float)) {
                return $float;
            }
        }

        if (
            is_string($environment->value)
            && preg_match('/^[+-]?(?:(?:\d+\.\d*|\d*\.\d+|\d+)(?:[eE][+-]?\d+)?)$/D', $environment->value) === 1
        ) {
            $float = (float) $environment->value;

            if (is_finite($float)) {
                return $float;
            }
        }

        throw InvalidEnvironmentValue::forType($name, 'float');
    }

    /**
     * Read a string without coercing native YAML scalar types.
     *
     * @throws InvalidArgumentException When the environment name is empty.
     * @throws InvalidEnvironmentValue When a present value is not a native string.
     */
    public static function string(string $name, ?string $default = null): ?string
    {
        $environment = EnvironmentLookup::find($name);

        if (! $environment->exists) {
            return $default;
        }

        if ($environment->value === null || is_string($environment->value)) {
            return $environment->value;
        }

        throw InvalidEnvironmentValue::forType($name, 'string');
    }

    /**
     * Cast only the reserved string forms that have explicit environment semantics.
     */
    private static function castString(string $value): bool|string|null
    {
        return match ($value) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => self::stripMatchingQuotes($value),
        };
    }

    /**
     * Remove one pair of matching single or double quotes from a stored string.
     */
    private static function stripMatchingQuotes(string $value): string
    {
        if (strlen($value) < 2) {
            return $value;
        }

        $quote = $value[0];

        if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote)) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * Parse a signed base-10 integer string without overflowing the current platform.
     */
    private static function integerFromString(string $value): ?int
    {
        if (preg_match('/^[+-]?\d+$/D', $value) !== 1) {
            return null;
        }

        $negative = str_starts_with($value, '-');
        $digits = ltrim($value, '+-0');
        $digits = $digits === '' ? '0' : $digits;
        $limit = $negative
            ? ltrim((string) PHP_INT_MIN, '-')
            : (string) PHP_INT_MAX;

        if (strlen($digits) > strlen($limit)) {
            return null;
        }

        if (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0) {
            return null;
        }

        return (int) $value;
    }

    /**
     * Select the appropriate parser for a filename supported by automatic composition.
     *
     * @throws UnsupportedEnvironmentFormat When the filename is not dotenv or YAML.
     */
    private static function sourceForFile(string $filename): EnvironmentSource
    {
        $basename = strtolower(basename($filename));

        if (str_ends_with($basename, '.yaml') || str_ends_with($basename, '.yml')) {
            return YamlSource::fromFile($filename);
        }

        if (
            $basename === '.env'
            || str_starts_with($basename, '.env.')
            || str_ends_with($basename, '.env')
        ) {
            return DotenvSource::fromFile($filename);
        }

        throw UnsupportedEnvironmentFormat::forFile($filename);
    }
}
