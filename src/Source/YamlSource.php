<?php

declare(strict_types=1);

namespace mykemeynell\Env\Source;

use mykemeynell\Env\Exception\InvalidEnvironmentFile;
use mykemeynell\Env\Source\Concerns\ReadsEnvironmentFiles;
use mykemeynell\Env\Support\EnvironmentRepository;
use mykemeynell\Env\Support\Interpolator;
use stdClass;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final readonly class YamlSource implements EnvironmentSource
{
    use ReadsEnvironmentFiles;

    /**
     * Retain normalized YAML values so factory creation remains side-effect free.
     *
     * @param  array<string, bool|float|int|string|null>  $values
     */
    private function __construct(
        private array $values,
    ) {}

    /**
     * Read, validate, flatten, and normalize a YAML environment file.
     *
     * @throws InvalidEnvironmentFile When YAML syntax or environment data is invalid.
     */
    public static function fromFile(string $filename): self
    {
        $contents = self::readEnvironmentFile($filename);

        try {
            $configuration = Yaml::parse(
                $contents,
                Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE | Yaml::PARSE_OBJECT_FOR_MAP,
            );
        } catch (ParseException $exception) {
            throw InvalidEnvironmentFile::because(
                $filename,
                $exception->getMessage(),
                $exception,
            );
        }

        if ($configuration === null) {
            return new self([]);
        }

        if (! $configuration instanceof stdClass) {
            throw InvalidEnvironmentFile::because(
                $filename,
                'Expected a YAML mapping at [<root>].',
            );
        }

        $values = [];
        $origins = [];
        self::flatten($configuration, $filename, [], $values, $origins);

        return new self($values);
    }

    /**
     * Interpolate YAML strings in source order and write accepted values to the repository.
     *
     * @return array<string, bool|float|int|string|null>
     */
    #[\Override]
    public function load(EnvironmentRepository $repository): array
    {
        $loaded = [];

        foreach ($this->values as $name => $value) {
            $resolved = is_string($value)
                ? Interpolator::resolve($value, $repository)
                : $value;

            if ($repository->setNative($name, $resolved)) {
                $loaded[$name] = $resolved;
            }
        }

        return $loaded;
    }

    /**
     * Flatten a YAML mapping while retaining original paths for useful collision errors.
     *
     * @param  list<string>  $segments
     * @param  array<string, bool|float|int|string|null>  $values
     * @param  array<string, string>  $origins
     */
    private static function flatten(
        stdClass $mapping,
        string $filename,
        array $segments,
        array &$values,
        array &$origins,
    ): void {
        foreach (get_object_vars($mapping) as $key => $value) {
            if (! is_string($key)) {
                throw InvalidEnvironmentFile::because(
                    $filename,
                    sprintf('YAML path [%s] cannot form a valid environment name.', $key),
                );
            }

            $path = [...$segments, $key];
            $displayPath = implode('.', $path);

            if ($value instanceof stdClass) {
                self::flatten($value, $filename, $path, $values, $origins);

                continue;
            }

            if (is_array($value)) {
                throw InvalidEnvironmentFile::because(
                    $filename,
                    sprintf('YAML sequences are not supported at [%s].', $displayPath),
                );
            }

            $name = self::environmentName($path, $filename, $displayPath);

            if (array_key_exists($name, $origins)) {
                throw InvalidEnvironmentFile::because(
                    $filename,
                    sprintf(
                        'YAML paths [%s] and [%s] both normalize to [%s].',
                        $origins[$name],
                        $displayPath,
                        $name,
                    ),
                );
            }

            $values[$name] = self::environmentValue($value, $filename, $displayPath);
            $origins[$name] = $displayPath;
        }
    }

    /**
     * Convert YAML path segments into a portable uppercase environment name.
     *
     * @param  list<string>  $segments
     *
     * @throws InvalidEnvironmentFile When any path segment cannot form an environment name.
     */
    private static function environmentName(
        array $segments,
        string $filename,
        string $displayPath,
    ): string {
        $normalized = [];

        foreach ($segments as $segment) {
            $normalizedSegment = preg_replace('/[^a-zA-Z0-9]+/', '_', $segment) ?? '';
            $normalizedSegment = strtoupper(trim($normalizedSegment, '_'));

            if ($normalizedSegment === '') {
                throw InvalidEnvironmentFile::because(
                    $filename,
                    sprintf('YAML path [%s] cannot form a valid environment name.', $displayPath),
                );
            }

            $normalized[] = $normalizedSegment;
        }

        $name = implode('_', $normalized);

        if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $name) !== 1) {
            throw InvalidEnvironmentFile::because(
                $filename,
                sprintf('YAML path [%s] cannot form a valid environment name.', $displayPath),
            );
        }

        return $name;
    }

    /**
     * Preserve supported YAML scalar types for publication to the PHP environment.
     *
     * @throws InvalidEnvironmentFile When the YAML value is not a supported scalar.
     */
    private static function environmentValue(
        mixed $value,
        string $filename,
        string $displayPath,
    ): bool|float|int|string|null {
        if ($value === null || is_bool($value) || is_string($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        // Symfony YAML limits this boundary to scalars, arrays, maps, and null.
        // @codeCoverageIgnoreStart
        throw InvalidEnvironmentFile::because(
            $filename,
            sprintf('YAML value at [%s] must be a scalar or null.', $displayPath),
        );
        // @codeCoverageIgnoreEnd
    }
}
