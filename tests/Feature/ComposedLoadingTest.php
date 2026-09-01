<?php

declare(strict_types=1);

use mykemeynell\Env\Env;
use mykemeynell\Env\Exception\InvalidEnvironmentFile;
use mykemeynell\Env\Exception\UnsupportedEnvironmentFormat;

it('layers mixed files while preserving every kind of host variable', function (): void {
    $names = [
        'COMPOSED_SHARED',
        'COMPOSED_DOTENV_ONLY',
        'COMPOSED_YAML_ONLY',
        'COMPOSED_ENV_HOST',
        'COMPOSED_SERVER_HOST',
        'COMPOSED_PROCESS_HOST',
    ];
    $this->forgetEnvironment(...$names);

    $_ENV['COMPOSED_ENV_HOST'] = 'from-env';
    $_SERVER['COMPOSED_SERVER_HOST'] = 'from-server';
    putenv('COMPOSED_PROCESS_HOST=from-process');

    $dotenv = $this->temporaryEnvironmentFile(<<<'ENV'
        COMPOSED_SHARED=dotenv
        COMPOSED_DOTENV_ONLY=dotenv-value
        COMPOSED_ENV_HOST=file-env
        COMPOSED_SERVER_HOST=file-server
        COMPOSED_PROCESS_HOST=file-process
        ENV, '.env.local');
    $yaml = $this->temporaryEnvironmentFile(<<<'YAML'
        composed-shared: yaml
        composed-yaml-only: ${COMPOSED_DOTENV_ONLY}-yaml
        composed-env-host: yaml-env
        composed-server-host: yaml-server
        composed-process-host: yaml-process
        YAML, '.yaml');

    $environment = Env::fromFiles($dotenv, $yaml);

    expect($_ENV)->not->toHaveKey('COMPOSED_SHARED');

    expect($environment->load())->toBe([
        'COMPOSED_SHARED' => 'yaml',
        'COMPOSED_DOTENV_ONLY' => 'dotenv-value',
        'COMPOSED_YAML_ONLY' => 'dotenv-value-yaml',
    ])->and($_ENV['COMPOSED_SHARED'])->toBe('yaml')
        ->and($_ENV['COMPOSED_ENV_HOST'])->toBe('from-env')
        ->and($_SERVER['COMPOSED_SERVER_HOST'])->toBe('from-server')
        ->and(getenv('COMPOSED_PROCESS_HOST'))->toBe('from-process')
        ->and(getenv('COMPOSED_SHARED'))->toBeFalse();
});

it('allows a later dotenv file to interpolate a yaml value', function (): void {
    $this->forgetEnvironment('COMPOSED_PREFIX', 'COMPOSED_RESULT');

    $yaml = $this->temporaryEnvironmentFile('composed-prefix: yaml', '.yml');
    $dotenv = $this->temporaryEnvironmentFile('COMPOSED_RESULT=${COMPOSED_PREFIX}-dotenv', '.env');

    expect(Env::fromFiles($yaml, $dotenv)->load())->toBe([
        'COMPOSED_PREFIX' => 'yaml',
        'COMPOSED_RESULT' => 'yaml-dotenv',
    ]);
});

it('preserves the native type selected by the last composed source', function (): void {
    $this->forgetEnvironment('COMPOSED_NATIVE_YAML', 'COMPOSED_NATIVE_DOTENV');

    $dotenvFirst = $this->temporaryEnvironmentFile('COMPOSED_NATIVE_YAML=dotenv', '.env');
    $yamlLast = $this->temporaryEnvironmentFile('composed-native-yaml: 12', '.yaml');
    $yamlFirst = $this->temporaryEnvironmentFile('composed-native-dotenv: false', '.yaml');
    $dotenvLast = $this->temporaryEnvironmentFile('COMPOSED_NATIVE_DOTENV=dotenv', '.env.local');

    expect(Env::fromFiles($dotenvFirst, $yamlLast)->load())->toBe([
        'COMPOSED_NATIVE_YAML' => 12,
    ])->and($_ENV['COMPOSED_NATIVE_YAML'])->toBe(12)
        ->and(Env::fromFiles($yamlFirst, $dotenvLast)->load())->toBe([
            'COMPOSED_NATIVE_DOTENV' => 'dotenv',
        ])->and($_ENV['COMPOSED_NATIVE_DOTENV'])->toBe('dotenv');
});

it('rejects unsupported automatic file formats', function (): void {
    $filename = $this->temporaryEnvironmentFile('{"APP_NAME":"invalid"}', '.json');

    expect(fn (): Env => Env::fromFiles($filename))
        ->toThrow(UnsupportedEnvironmentFormat::class, $filename);
});

it('validates every composed source before loading any earlier source', function (): void {
    $this->forgetEnvironment('COMPOSED_ATOMIC');

    $dotenv = $this->temporaryEnvironmentFile('COMPOSED_ATOMIC=should-not-load', '.env');
    $invalidYaml = $this->temporaryEnvironmentFile('invalid: [unterminated', '.yaml');

    expect(fn (): Env => Env::fromFiles($dotenv, $invalidYaml))
        ->toThrow(InvalidEnvironmentFile::class)
        ->and($_ENV)->not->toHaveKey('COMPOSED_ATOMIC')
        ->and($_SERVER)->not->toHaveKey('COMPOSED_ATOMIC');
});

it('requires at least one file for automatic composition', function (): void {
    expect(fn (): Env => Env::fromFiles())
        ->toThrow(InvalidArgumentException::class, 'at least one');
});
