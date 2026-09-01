<?php

declare(strict_types=1);

namespace Tests\Concerns;

trait ManagesEnvironment
{
    /** @var array<string, array{envExists: bool, env: mixed, serverExists: bool, server: mixed, process: string|false}> */
    private array $environmentSnapshots = [];

    /** @var list<string> */
    private array $temporaryEnvironmentFiles = [];

    /**
     * Remove variables from every environment location after remembering their original state.
     */
    protected function forgetEnvironment(string ...$names): void
    {
        foreach ($names as $name) {
            $this->rememberEnvironment($name);

            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        }
    }

    /**
     * Remember a variable so that the test can safely change any environment location.
     */
    protected function rememberEnvironment(string $name): void
    {
        if (array_key_exists($name, $this->environmentSnapshots)) {
            return;
        }

        $this->environmentSnapshots[$name] = [
            'envExists' => array_key_exists($name, $_ENV),
            'env' => $_ENV[$name] ?? null,
            'serverExists' => array_key_exists($name, $_SERVER),
            'server' => $_SERVER[$name] ?? null,
            'process' => getenv($name),
        ];
    }

    /**
     * Create a disposable configuration file with the requested filename suffix.
     */
    protected function temporaryEnvironmentFile(string $contents, string $suffix): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'mykemeynell-env-');

        if ($temporary === false) {
            self::fail('Unable to create a temporary environment file.');
        }

        $filename = str_starts_with($suffix, '.env.')
            ? dirname($temporary).'/'.$suffix.'-'.basename($temporary)
            : $temporary.$suffix;
        rename($temporary, $filename);
        file_put_contents($filename, $contents);

        $this->temporaryEnvironmentFiles[] = $filename;

        return $filename;
    }

    /**
     * Restore environment state and remove temporary files after each test.
     */
    protected function restoreEnvironment(): void
    {
        foreach ($this->environmentSnapshots as $name => $snapshot) {
            if ($snapshot['envExists']) {
                $_ENV[$name] = $snapshot['env'];
            } else {
                unset($_ENV[$name]);
            }

            if ($snapshot['serverExists']) {
                $_SERVER[$name] = $snapshot['server'];
            } else {
                unset($_SERVER[$name]);
            }

            if ($snapshot['process'] === false) {
                putenv($name);
            } else {
                putenv($name.'='.$snapshot['process']);
            }
        }

        foreach ($this->temporaryEnvironmentFiles as $filename) {
            if (is_file($filename)) {
                unlink($filename);
            }
        }
    }
}
