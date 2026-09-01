<?php

declare(strict_types=1);

use mykemeynell\Env\Env;
use mykemeynell\Env\Exception\InvalidEnvironmentFile;

it('parses dotenv files before safely loading their values', function (): void {
    $this->forgetEnvironment(
        'PACKAGE_NAME',
        'PACKAGE_BASE_URL',
        'PACKAGE_API_URL',
        'PACKAGE_EMPTY',
        'PACKAGE_UNSET',
    );

    $filename = $this->temporaryEnvironmentFile(<<<'ENV'
        PACKAGE_NAME="Environment package"
        PACKAGE_BASE_URL=https://example.test
        PACKAGE_API_URL=${PACKAGE_BASE_URL}/api
        PACKAGE_EMPTY=
        PACKAGE_UNSET
        ENV, '.env');

    $environment = Env::fromDotenv($filename);

    expect($_ENV)->not->toHaveKey('PACKAGE_NAME')
        ->and($_SERVER)->not->toHaveKey('PACKAGE_NAME');

    $loaded = $environment->load();

    expect($loaded)->toBe([
        'PACKAGE_NAME' => 'Environment package',
        'PACKAGE_BASE_URL' => 'https://example.test',
        'PACKAGE_API_URL' => 'https://example.test/api',
        'PACKAGE_EMPTY' => '',
        'PACKAGE_UNSET' => null,
    ])->and($_ENV['PACKAGE_API_URL'])->toBe('https://example.test/api')
        ->and($_SERVER['PACKAGE_API_URL'])->toBe('https://example.test/api')
        ->and($_ENV)->not->toHaveKey('PACKAGE_UNSET')
        ->and($_SERVER)->not->toHaveKey('PACKAGE_UNSET')
        ->and(getenv('PACKAGE_API_URL'))->toBeFalse();
});

it('does not clear an unset entry supplied by the host', function (): void {
    $this->forgetEnvironment('PACKAGE_HOST_UNSET');
    $_ENV['PACKAGE_HOST_UNSET'] = 'host-value';
    $filename = $this->temporaryEnvironmentFile('PACKAGE_HOST_UNSET', '.env');

    expect(Env::fromDotenv($filename)->load())->toBe([])
        ->and($_ENV['PACKAGE_HOST_UNSET'])->toBe('host-value');
});

it('does not treat values from a previous load as package-owned on a repeated load', function (): void {
    $this->forgetEnvironment('PACKAGE_REPEAT');

    $filename = $this->temporaryEnvironmentFile('PACKAGE_REPEAT=first', '.env');
    $environment = Env::fromDotenv($filename);

    expect($environment->load())->toBe(['PACKAGE_REPEAT' => 'first'])
        ->and($environment->load())->toBe([])
        ->and($_ENV['PACKAGE_REPEAT'])->toBe('first');
});

it('accepts an empty dotenv file as a no-op', function (): void {
    $filename = $this->temporaryEnvironmentFile('', '.env');

    expect(Env::fromDotenv($filename)->load())->toBe([]);
});

it('rejects a missing dotenv file with package context', function (): void {
    $filename = sys_get_temp_dir().'/missing-environment-'.bin2hex(random_bytes(6)).'.env';

    expect(fn (): Env => Env::fromDotenv($filename))
        ->toThrow(InvalidEnvironmentFile::class, $filename);
});

it('rejects malformed dotenv syntax before mutating the environment', function (): void {
    $this->forgetEnvironment('PACKAGE_BROKEN');
    $filename = $this->temporaryEnvironmentFile('PACKAGE_BROKEN=unexpected whitespace', '.env');

    expect(fn (): Env => Env::fromDotenv($filename))
        ->toThrow(InvalidEnvironmentFile::class, $filename)
        ->and($_ENV)->not->toHaveKey('PACKAGE_BROKEN')
        ->and($_SERVER)->not->toHaveKey('PACKAGE_BROKEN');
});
