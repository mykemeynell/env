<?php

declare(strict_types=1);

use mykemeynell\Env\Env;
use mykemeynell\Env\Exception\InvalidEnvironmentValue;

it('reads values with deterministic precedence and laravel-style casting', function (): void {
    $names = [
        'ACCESS_PRECEDENCE',
        'ACCESS_TRUE',
        'ACCESS_PARENTHESIZED_FALSE',
        'ACCESS_EMPTY',
        'ACCESS_NULL',
        'ACCESS_DOUBLE_QUOTED',
        'ACCESS_SINGLE_QUOTED',
        'ACCESS_INTEGER_STRING',
        'ACCESS_NATIVE_INTEGER',
        'ACCESS_NATIVE_FALSE',
        'ACCESS_NATIVE_NULL',
        'ACCESS_MISSING',
    ];
    $this->forgetEnvironment(...$names);

    putenv('ACCESS_PRECEDENCE=process');
    $_ENV['ACCESS_PRECEDENCE'] = 'env';
    $_SERVER['ACCESS_PRECEDENCE'] = 'server';
    $_ENV['ACCESS_TRUE'] = 'true';
    $_ENV['ACCESS_PARENTHESIZED_FALSE'] = '(false)';
    $_ENV['ACCESS_EMPTY'] = '(empty)';
    $_ENV['ACCESS_NULL'] = 'null';
    $_ENV['ACCESS_DOUBLE_QUOTED'] = '"quoted"';
    $_ENV['ACCESS_SINGLE_QUOTED'] = "'single quoted'";
    $_ENV['ACCESS_INTEGER_STRING'] = '12';
    $_ENV['ACCESS_NATIVE_INTEGER'] = 12;
    $_ENV['ACCESS_NATIVE_FALSE'] = false;
    $_ENV['ACCESS_NATIVE_NULL'] = null;
    $default = new stdClass;

    expect(Env::get('ACCESS_PRECEDENCE'))->toBe('server')
        ->and(Env::get('ACCESS_TRUE'))->toBeTrue()
        ->and(Env::get('ACCESS_PARENTHESIZED_FALSE'))->toBeFalse()
        ->and(Env::get('ACCESS_EMPTY'))->toBe('')
        ->and(Env::get('ACCESS_NULL'))->toBeNull()
        ->and(Env::get('ACCESS_DOUBLE_QUOTED'))->toBe('quoted')
        ->and(Env::get('ACCESS_SINGLE_QUOTED'))->toBe('single quoted')
        ->and(Env::get('ACCESS_INTEGER_STRING'))->toBe('12')
        ->and(Env::get('ACCESS_NATIVE_INTEGER'))->toBe(12)
        ->and(Env::get('ACCESS_NATIVE_FALSE'))->toBeFalse()
        ->and(Env::get('ACCESS_NATIVE_NULL', 'fallback'))->toBeNull()
        ->and(Env::get('ACCESS_MISSING', $default))->toBe($default);

    unset($_SERVER['ACCESS_PRECEDENCE']);
    expect(Env::get('ACCESS_PRECEDENCE'))->toBe('env');

    unset($_ENV['ACCESS_PRECEDENCE']);
    expect(Env::get('ACCESS_PRECEDENCE'))->toBe('process');
});

it('leaves short and mismatched quoted strings unchanged', function (): void {
    $this->forgetEnvironment('ACCESS_SHORT_STRING', 'ACCESS_MISMATCHED_QUOTES');
    $_ENV['ACCESS_SHORT_STRING'] = 'x';
    $_ENV['ACCESS_MISMATCHED_QUOTES'] = '"value\'';

    expect(Env::get('ACCESS_SHORT_STRING'))->toBe('x')
        ->and(Env::get('ACCESS_MISMATCHED_QUOTES'))->toBe('"value\'');
});

it('casts every explicit reserved string form', function (string $value, mixed $expected): void {
    $this->forgetEnvironment('ACCESS_RESERVED');
    $_ENV['ACCESS_RESERVED'] = $value;

    expect(Env::get('ACCESS_RESERVED'))->toBe($expected);
})->with([
    'true' => ['true', true],
    'parenthesized true' => ['(true)', true],
    'false' => ['false', false],
    'parenthesized false' => ['(false)', false],
    'empty' => ['empty', ''],
    'parenthesized empty' => ['(empty)', ''],
    'null' => ['null', null],
    'parenthesized null' => ['(null)', null],
]);

it('requires a non-empty environment name', function (): void {
    expect(fn (): mixed => Env::get(''))
        ->toThrow(InvalidArgumentException::class, 'non-empty');
});

it('reads loaded dotenv values through the package api', function (): void {
    $this->forgetEnvironment('ACCESS_DOTENV_ENABLED', 'ACCESS_DOTENV_PORT');

    $filename = $this->temporaryEnvironmentFile(<<<'ENV'
        ACCESS_DOTENV_ENABLED=true
        ACCESS_DOTENV_PORT=8080
        ENV, '.env');

    Env::fromDotenv($filename)->load();

    expect(Env::get('ACCESS_DOTENV_ENABLED'))->toBeTrue()
        ->and(Env::get('ACCESS_DOTENV_PORT'))->toBe('8080')
        ->and(Env::bool('ACCESS_DOTENV_ENABLED'))->toBeTrue()
        ->and(Env::int('ACCESS_DOTENV_PORT'))->toBe(8080);
});

it('retrieves strict boolean values', function (mixed $value, bool $expected): void {
    $this->forgetEnvironment('ACCESS_BOOLEAN');
    $_ENV['ACCESS_BOOLEAN'] = $value;

    expect(Env::bool('ACCESS_BOOLEAN'))->toBe($expected);
})->with([
    'native true' => [true, true],
    'native false' => [false, false],
    'true string' => ['true', true],
    'parenthesized true' => ['(true)', true],
    'false string' => ['false', false],
    'parenthesized false' => ['(false)', false],
]);

it('rejects ambiguous boolean conversions', function (mixed $value): void {
    $this->forgetEnvironment('ACCESS_BOOLEAN');
    $_ENV['ACCESS_BOOLEAN'] = $value;

    expect(fn (): ?bool => Env::bool('ACCESS_BOOLEAN'))
        ->toThrow(InvalidEnvironmentValue::class, 'ACCESS_BOOLEAN');
})->with([
    'integer one string' => ['1'],
    'integer zero string' => ['0'],
    'float string' => ['0.5'],
    'arbitrary word' => ['yes'],
    'native integer' => [1],
    'native float' => [0.5],
]);

it('retrieves strict integer values', function (mixed $value, int $expected): void {
    $this->forgetEnvironment('ACCESS_INTEGER');
    $_ENV['ACCESS_INTEGER'] = $value;

    expect(Env::int('ACCESS_INTEGER'))->toBe($expected);
})->with([
    'native integer' => [12, 12],
    'maximum integer' => [PHP_INT_MAX, PHP_INT_MAX],
    'minimum integer string' => [(string) PHP_INT_MIN, PHP_INT_MIN],
    'integer string' => ['12', 12],
    'positive integer string' => ['+12', 12],
    'negative integer string' => ['-12', -12],
    'leading zeroes' => ['0012', 12],
]);

it('rejects invalid or overflowing integer conversions', function (mixed $value): void {
    $this->forgetEnvironment('ACCESS_INTEGER');
    $_ENV['ACCESS_INTEGER'] = $value;

    expect(fn (): ?int => Env::int('ACCESS_INTEGER'))
        ->toThrow(InvalidEnvironmentValue::class, 'ACCESS_INTEGER');
})->with([
    'decimal string' => ['1.5'],
    'exponent string' => ['1e3'],
    'native float' => [1.0],
    'native boolean' => [true],
    'non-numeric string' => ['twelve'],
    'positive overflow' => [(string) PHP_INT_MAX.'0'],
    'same-length positive overflow' => [substr((string) PHP_INT_MAX, 0, -1).(((int) substr((string) PHP_INT_MAX, -1)) + 1)],
]);

it('retrieves finite float values', function (mixed $value, float $expected): void {
    $this->forgetEnvironment('ACCESS_FLOAT');
    $_ENV['ACCESS_FLOAT'] = $value;

    expect(Env::float('ACCESS_FLOAT'))->toBe($expected);
})->with([
    'native float' => [1.25, 1.25],
    'native integer' => [12, 12.0],
    'decimal string' => ['1.25', 1.25],
    'integer string' => ['12', 12.0],
    'exponent string' => ['1e3', 1000.0],
]);

it('rejects invalid or non-finite float conversions', function (mixed $value): void {
    $this->forgetEnvironment('ACCESS_FLOAT');
    $_ENV['ACCESS_FLOAT'] = $value;

    expect(fn (): ?float => Env::float('ACCESS_FLOAT'))
        ->toThrow(InvalidEnvironmentValue::class, 'ACCESS_FLOAT');
})->with([
    'native boolean' => [true],
    'non-numeric string' => ['one point five'],
    'overflowing numeric string' => ['1e309'],
    'native infinity' => [INF],
]);

it('retrieves only native strings through the string accessor', function (): void {
    $this->forgetEnvironment('ACCESS_STRING');
    $_ENV['ACCESS_STRING'] = 'value';

    expect(Env::string('ACCESS_STRING'))->toBe('value');

    $_ENV['ACCESS_STRING'] = 12;

    expect(fn (): ?string => Env::string('ACCESS_STRING'))
        ->toThrow(InvalidEnvironmentValue::class, 'ACCESS_STRING');
});

it('returns typed defaults for missing values and preserves present nulls', function (): void {
    $names = ['ACCESS_DEFAULT', 'ACCESS_PRESENT_NULL'];
    $this->forgetEnvironment(...$names);
    $_ENV['ACCESS_PRESENT_NULL'] = null;

    expect(Env::bool('ACCESS_DEFAULT', true))->toBeTrue()
        ->and(Env::int('ACCESS_DEFAULT', 12))->toBe(12)
        ->and(Env::float('ACCESS_DEFAULT', 1.25))->toBe(1.25)
        ->and(Env::string('ACCESS_DEFAULT', 'fallback'))->toBe('fallback')
        ->and(Env::bool('ACCESS_PRESENT_NULL', true))->toBeNull()
        ->and(Env::int('ACCESS_PRESENT_NULL', 12))->toBeNull()
        ->and(Env::float('ACCESS_PRESENT_NULL', 1.25))->toBeNull()
        ->and(Env::string('ACCESS_PRESENT_NULL', 'fallback'))->toBeNull();
});

it('does not expose invalid values in conversion exception messages', function (): void {
    $this->forgetEnvironment('ACCESS_SECRET');
    $_ENV['ACCESS_SECRET'] = 'TOP_SECRET_ACCESS_VALUE';

    try {
        Env::bool('ACCESS_SECRET');
        self::fail('Expected an invalid environment value exception.');
    } catch (InvalidEnvironmentValue $exception) {
        expect($exception->getMessage())->toContain('ACCESS_SECRET')
            ->and($exception->getMessage())->toContain('boolean')
            ->and($exception->getMessage())->not->toContain('TOP_SECRET_ACCESS_VALUE');
    }
});
