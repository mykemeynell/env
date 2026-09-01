# Changelog

## [2.0.0](https://github.com/mykemeynell/dotenv/compare/1.0.2...v2.0.0) (2026-09-01)


### ⚠ BREAKING CHANGES

* global `load_env`, `env`, and `value` helpers have been removed.
* namespace moved to `mykemeynell/Env`
* minimum PHP 8.3

### Features

* `Env::get()`, `Env::string()`, `Env::bool()`, `Env::int()`, `Env::float()` methods added for environment reading. ([91d1b19](https://github.com/mykemeynell/dotenv/commit/91d1b19357d6770a46b785a67152289dda79d65b))
* enhance PHPStan integration with isolated tooling and updated analyze scripts ([4dfaa13](https://github.com/mykemeynell/dotenv/commit/4dfaa1391a83e47bb53487f5645869db4e555920))
* global `load_env`, `env`, and `value` helpers have been removed. ([91d1b19](https://github.com/mykemeynell/dotenv/commit/91d1b19357d6770a46b785a67152289dda79d65b))
* minimum PHP 8.3 ([91d1b19](https://github.com/mykemeynell/dotenv/commit/91d1b19357d6770a46b785a67152289dda79d65b))
* missing or invalid env files throw exception instead of being silently ignored. ([91d1b19](https://github.com/mykemeynell/dotenv/commit/91d1b19357d6770a46b785a67152289dda79d65b))
* namespace moved to `mykemeynell/Env` ([91d1b19](https://github.com/mykemeynell/dotenv/commit/91d1b19357d6770a46b785a67152289dda79d65b))
