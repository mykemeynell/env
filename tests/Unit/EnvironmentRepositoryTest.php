<?php

declare(strict_types=1);

use mykemeynell\Env\Support\EnvironmentRepository;

it('reports whether native environment values exist', function (): void {
    $this->forgetEnvironment('REPOSITORY_NATIVE');
    $repository = new EnvironmentRepository;

    expect($repository->has('REPOSITORY_NATIVE'))->toBeFalse()
        ->and($repository->setNative('REPOSITORY_NATIVE', null))->toBeTrue()
        ->and($repository->has('REPOSITORY_NATIVE'))->toBeTrue()
        ->and($repository->get('REPOSITORY_NATIVE'))->toBe('');
});

it('protects unsupported host values without interpolating them', function (): void {
    $this->forgetEnvironment('REPOSITORY_UNSUPPORTED');
    $_ENV['REPOSITORY_UNSUPPORTED'] = ['host-value'];
    $repository = new EnvironmentRepository;

    expect($repository->has('REPOSITORY_UNSUPPORTED'))->toBeTrue()
        ->and($repository->get('REPOSITORY_UNSUPPORTED'))->toBeNull()
        ->and($repository->set('REPOSITORY_UNSUPPORTED', 'replacement'))->toBeFalse()
        ->and($_ENV['REPOSITORY_UNSUPPORTED'])->toBe(['host-value']);
});
