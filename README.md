# Env

![GitHub Release](https://img.shields.io/github/v/release/mykemeynell/dotenv)
[![CI/CD (main)](https://img.shields.io/github/actions/workflow/status/mykemeynell/dotenv/ci-cd.yml?label=stable%20(main)&branch=main)](https://github.com/mykemeynell/dotenv/actions/workflows/ci-cd.yml)
[![CI/CD (dev)](https://img.shields.io/github/actions/workflow/status/mykemeynell/dotenv/ci-cd.yml?label=dev&branch=dev)](https://github.com/mykemeynell/dotenv/actions/workflows/ci-cd.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue.svg)](https://www.php.net)
[![License](https://img.shields.io/badge/License-MIT-lightgrey.svg)](LICENSE)

Load and retrieve dotenv and YAML environment configuration through one typed API.

The package validates every source before changing the environment, protects values supplied by the host, and never calls `putenv()` while loading configuration.

## Requirements

- PHP 8.3 or newer
- Composer

## Installation

```shell
composer require mykemeynell/env
```

## Loading dotenv files

```php
use mykemeynell\Env\Env;

$loaded = Env::fromDotenv(__DIR__.'/.env')->load();

$applicationName = Env::string('APP_NAME');
```

Dotenv files support the syntax provided by `vlucas/phpdotenv`, including comments, quoted values, empty values, and `${VARIABLE}` interpolation.

Creating the loader reads and validates the file but does not change the environment. Values are published only when `load()` is called.

## Loading YAML files

YAML may use flat environment names, nested mappings, or both:

```yaml
app-name: Example application

database:
  read-host: database.internal
  port: 5432

feature:
  enabled: true

api-url: ${APP_NAME}/api
nullable-value: null
```

```php
use mykemeynell\Env\Env;

$loaded = Env::fromYaml(__DIR__.'/environment.yaml')->load();
```

Nested key segments are converted to uppercase snake case. For example, `database.read-host` becomes `DATABASE_READ_HOST`.

YAML scalar types are preserved. Strings remain strings, booleans remain booleans, integers and floats remain numeric, and `null` remains null in the returned array, `$_ENV`, and `$_SERVER`. YAML sequences are rejected because environment values must be scalar. Invalid names and keys that normalise to the same environment name are also rejected.

Every YAML string supports `${VARIABLE}` interpolation, regardless of its YAML quoting style. References resolve against host variables and values that appear earlier in the same file or in an earlier source. Native values are stringified only for interpolation: booleans use lowercase `true` or `false`, numbers use their decimal representation, and null uses an empty string. Forward or unavailable references remain unchanged.

## Composing files

Use `fromFiles()` to layer dotenv and YAML files in argument order:

```php
use mykemeynell\Env\Env;

$loaded = Env::fromFiles(
    __DIR__.'/.env',
    __DIR__.'/environment.yaml',
    __DIR__.'/.env.local',
)->load();
```

Later files override values loaded from earlier files. Values that existed in `$_ENV`, `$_SERVER`, or the process environment before `load()` always take precedence and are omitted from the returned array.

Automatic detection recognises `.env`, `.env.*`, `*.env`, `.yaml`, and `.yml` filenames. Use `fromDotenv()` or `fromYaml()` when a configuration file uses a non-standard filename.

## Reading values

Read configuration through `Env` after loading it:

```php
use mykemeynell\Env\Env;

Env::fromFiles(
    __DIR__.'/.env',
    __DIR__.'/environment.yaml',
)->load();

$applicationName = Env::string('APP_NAME');
$databaseHost = Env::string('DATABASE_READ_HOST', 'localhost');
$featureEnabled = Env::bool('FEATURE_ENABLED', false);
$maximumRetries = Env::int('MAX_RETRIES', 3);
$backoffRatio = Env::float('BACKOFF_RATIO', 1.0);
```

`Env::get()` returns native YAML scalars unchanged. It also follows Laravel's explicit string conventions: `true`, `false`, `empty`, `null`, and their parenthesised forms are converted to their corresponding PHP values, and matching surrounding quotes are removed. Numeric strings remain strings.

Use the typed accessors when the expected type is known. They deliberately reject ambiguous conversions: for example, `Env::bool('FEATURE_ENABLED')` accepts `true`, `(true)`, `false`, and `(false)`, but rejects `1`, `0`, and `0.5`. An invalid present value throws `InvalidEnvironmentValue`; a missing value returns the supplied typed default or null.

```php
use mykemeynell\Env\Exception\InvalidEnvironmentValue;

try {
    $featureEnabled = Env::bool('FEATURE_ENABLED');
} catch (InvalidEnvironmentValue $exception) {
    // FEATURE_ENABLED exists but is not an explicit boolean.
}
```

Defaults apply only to missing names. A YAML value explicitly set to null remains null. If a stored string such as `true` must remain a string rather than use `get()` casting, retrieve it with `Env::string()`.

## Environment behaviour

Loaded values are published to both `$_ENV` and `$_SERVER`, but those storage details do not need to leak into application code. `Env::get()` and the typed accessors read `$_SERVER` first, then `$_ENV`, and finally the process environment.

The package consults `getenv()` to protect and read process values but does not call `putenv()`. Consequently, newly loaded values are available through `Env`, `$_ENV`, and `$_SERVER`, but are not exposed through `getenv()`. This avoids the thread-safety concerns associated with process-environment mutation.

Calling `load()` again creates a new protected loading operation. Values written by the earlier call are then treated as existing environment values and are not overwritten.

## Errors

Missing, unreadable, malformed, or semantically invalid configuration throws `mykemeynell\Env\Exception\InvalidEnvironmentFile`. 

Automatic detection failures throw `mykemeynell\Env\Exception\UnsupportedEnvironmentFormat`. 

Typed accessor conversion failures throw `mykemeynell\Env\Exception\InvalidEnvironmentValue` without including the potentially sensitive value in the exception message.

Exception messages include the filename and, for YAML value errors, the relevant configuration path.

## Development

```shell
composer test
composer test:coverage
composer lint:check
composer analyze
composer validate --strict
```

The test suite uses Pest and covers dotenv parsing, native YAML scalars, interpolation, source composition, typed access, strict conversion failures, precedence, validation failures, and safe environment publication.
