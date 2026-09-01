<?php

declare(strict_types=1);

use mykemeynell\Env\Env;
use mykemeynell\Env\Exception\InvalidEnvironmentFile;

it('loads flat and nested yaml scalars as environment strings', function (): void {
    $names = [
        'APP_NAME',
        'DATABASE_READ_HOST',
        'FEATURE_ENABLED',
        'FEATURE_DISABLED',
        'MAX_RETRIES',
        'BACKOFF_RATIO',
        'NULLABLE_VALUE',
        'QUOTED_NUMBER',
    ];
    $this->forgetEnvironment(...$names);

    $filename = $this->temporaryEnvironmentFile(<<<'YAML'
        app-name: Environment package
        database:
          read-host: database.internal
        feature:
          enabled: true
          disabled: false
        max_retries: 3
        backoff-ratio: 1.25
        nullable-value: null
        quoted-number: "12"
        YAML, '.yaml');

    $loaded = Env::fromYaml($filename)->load();

    expect($loaded)->toBe([
        'APP_NAME' => 'Environment package',
        'DATABASE_READ_HOST' => 'database.internal',
        'FEATURE_ENABLED' => true,
        'FEATURE_DISABLED' => false,
        'MAX_RETRIES' => 3,
        'BACKOFF_RATIO' => 1.25,
        'NULLABLE_VALUE' => null,
        'QUOTED_NUMBER' => '12',
    ]);

    foreach ($loaded as $name => $value) {
        expect($_ENV[$name])->toBe($value)
            ->and($_SERVER[$name])->toBe($value)
            ->and(getenv($name))->toBeFalse();
    }
});

it('stringifies native yaml scalars only when interpolating them', function (): void {
    $this->forgetEnvironment(
        'YAML_BOOLEAN',
        'YAML_INTEGER',
        'YAML_FLOAT',
        'YAML_NULL',
        'YAML_INTERPOLATED',
    );

    $filename = $this->temporaryEnvironmentFile(<<<'YAML'
        yaml-boolean: true
        yaml-integer: 12
        yaml-float: 1.25
        yaml-null: null
        yaml-interpolated: ${YAML_BOOLEAN}/${YAML_INTEGER}/${YAML_FLOAT}/${YAML_NULL}
        YAML, '.yaml');

    expect(Env::fromYaml($filename)->load())->toBe([
        'YAML_BOOLEAN' => true,
        'YAML_INTEGER' => 12,
        'YAML_FLOAT' => 1.25,
        'YAML_NULL' => null,
        'YAML_INTERPOLATED' => 'true/12/1.25/',
    ]);
});

it('interpolates yaml values in source order and preserves unresolved references', function (): void {
    $this->forgetEnvironment('YAML_BASE_URL', 'YAML_API_URL', 'YAML_QUOTED_URL', 'YAML_FORWARD', 'YAML_LATER', 'YAML_PROCESS_HOST');
    $this->rememberEnvironment('PACKAGE_PROCESS_VALUE');
    putenv('PACKAGE_PROCESS_VALUE=from-process');

    $filename = $this->temporaryEnvironmentFile(<<<'YAML'
        yaml-base-url: https://example.test
        yaml-api-url: ${YAML_BASE_URL}/api
        yaml-quoted-url: '${YAML_BASE_URL}/quoted'
        yaml-forward: ${YAML_LATER}/forward
        yaml-later: available-later
        yaml-process-host: ${PACKAGE_PROCESS_VALUE}/host
        YAML, '.yml');

    expect(Env::fromYaml($filename)->load())->toBe([
        'YAML_BASE_URL' => 'https://example.test',
        'YAML_API_URL' => 'https://example.test/api',
        'YAML_QUOTED_URL' => 'https://example.test/quoted',
        'YAML_FORWARD' => '${YAML_LATER}/forward',
        'YAML_LATER' => 'available-later',
        'YAML_PROCESS_HOST' => 'from-process/host',
    ]);
});

it('accepts empty yaml configuration as a no-op', function (string $yaml): void {
    $filename = $this->temporaryEnvironmentFile($yaml, '.yaml');

    expect(Env::fromYaml($filename)->load())->toBe([]);
})->with([
    'empty file' => [''],
    'empty mapping' => ['{}'],
]);

it('protects a native null published by an earlier load', function (): void {
    $this->forgetEnvironment('YAML_PRESERVED_NULL');
    $first = $this->temporaryEnvironmentFile('yaml-preserved-null: null', '.yaml');
    $second = $this->temporaryEnvironmentFile('yaml-preserved-null: replacement', '.yaml');

    expect(Env::fromYaml($first)->load())->toBe(['YAML_PRESERVED_NULL' => null])
        ->and(Env::fromYaml($second)->load())->toBe([])
        ->and($_ENV)->toHaveKey('YAML_PRESERVED_NULL')
        ->and($_ENV['YAML_PRESERVED_NULL'])->toBeNull()
        ->and($_SERVER['YAML_PRESERVED_NULL'])->toBeNull();
});

it('rejects a missing yaml file with package context', function (): void {
    $filename = sys_get_temp_dir().'/missing-environment-'.bin2hex(random_bytes(6)).'.yaml';

    expect(fn (): Env => Env::fromYaml($filename))
        ->toThrow(InvalidEnvironmentFile::class, $filename);
});

it('rejects yaml sequences with their configuration path', function (string $yaml, string $path): void {
    $filename = $this->temporaryEnvironmentFile($yaml, '.yaml');

    expect(fn (): Env => Env::fromYaml($filename))
        ->toThrow(InvalidEnvironmentFile::class, $path);
})->with([
    'root sequence' => ['- api'.PHP_EOL.'- worker', '<root>'],
    'nested sequence' => ['services:'.PHP_EOL.'  - api'.PHP_EOL.'  - worker', 'services'],
]);

it('rejects malformed yaml and invalid root scalars', function (string $yaml): void {
    $filename = $this->temporaryEnvironmentFile($yaml, '.yaml');

    expect(fn (): Env => Env::fromYaml($filename))
        ->toThrow(InvalidEnvironmentFile::class, $filename);
})->with([
    'malformed yaml' => ['database: [unterminated'],
    'scalar root' => ['not-a-mapping'],
]);

it('rejects yaml keys that normalize to the same environment name', function (): void {
    $filename = $this->temporaryEnvironmentFile(<<<'YAML'
        database:
          host: first
        database-host: second
        YAML, '.yaml');

    expect(fn (): Env => Env::fromYaml($filename))
        ->toThrow(InvalidEnvironmentFile::class, 'DATABASE_HOST');
});

it('rejects invalid yaml environment names', function (string $key): void {
    $filename = $this->temporaryEnvironmentFile(sprintf("'%s': invalid", $key), '.yaml');

    expect(fn (): Env => Env::fromYaml($filename))
        ->toThrow(InvalidEnvironmentFile::class, $key);
})->with([
    'numeric key' => ['123'],
    'numeric prefix' => ['123abc'],
]);

it('rejects nested yaml keys that have no normalizable characters', function (): void {
    $filename = $this->temporaryEnvironmentFile(<<<'YAML'
        valid:
          '---': invalid
        YAML, '.yaml');

    expect(fn (): Env => Env::fromYaml($filename))
        ->toThrow(InvalidEnvironmentFile::class, 'valid.---');
});
