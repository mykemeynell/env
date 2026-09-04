# Changelog

## [2.0.2](https://github.com/mykemeynell/env/compare/v2.0.1...v2.0.2) (2026-09-04)


### Miscellaneous Chores

* add CODEOWNERS file ([ac8434b](https://github.com/mykemeynell/env/commit/ac8434b8adeecd70a0ecbc26f83c11cdee39e4e2))
* promote dev to main ([4029d7e](https://github.com/mykemeynell/env/commit/4029d7e589be94a04b02055de8bac8004135a1ec))
* update badges and release links in README following repository rename ([a9ed472](https://github.com/mykemeynell/env/commit/a9ed4728dab58312113ae421b1d292100be85d3d))
* update badges and release links in README following repository… ([896b5c5](https://github.com/mykemeynell/env/commit/896b5c5302c9179777aa23de4f42ee7a0a6fcce0))

## [2.0.1](https://github.com/mykemeynell/env/compare/v2.0.0...v2.0.1) (2026-09-03)


### Miscellaneous Chores

* housekeeping ([aedabf0](https://github.com/mykemeynell/env/commit/aedabf0be8b5d80a5b8a17fb7a262e691dac0b87))
* trigger workflow ([15f1196](https://github.com/mykemeynell/env/commit/15f1196d8c31ba33151286f44bb173a3ea82a5d1))
* update homepage URL in composer.json ([b1fd13b](https://github.com/mykemeynell/env/commit/b1fd13b3cf0ea7e47143c33483e5b9676649137d))

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
