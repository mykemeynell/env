<?php

declare(strict_types=1);

namespace mykemeynell\Env\Source;

use Dotenv\Exception\InvalidFileException;
use Dotenv\Loader\Loader;
use Dotenv\Parser\Entry;
use Dotenv\Parser\Parser;
use mykemeynell\Env\Exception\InvalidEnvironmentFile;
use mykemeynell\Env\Source\Concerns\ReadsEnvironmentFiles;
use mykemeynell\Env\Support\EnvironmentRepository;

final readonly class DotenvSource implements EnvironmentSource
{
    use ReadsEnvironmentFiles;

    /**
     * Retain parsed dotenv entries so factory creation remains side-effect free.
     *
     * @param  list<Entry>  $entries
     */
    private function __construct(
        private array $entries,
    ) {}

    /**
     * Read and validate dotenv syntax without publishing any environment values.
     *
     * @throws InvalidEnvironmentFile When the file is unreadable or contains invalid dotenv syntax.
     */
    public static function fromFile(string $filename): self
    {
        $contents = self::readEnvironmentFile($filename);

        try {
            return new self(array_values((new Parser)->parse($contents)));
        } catch (InvalidFileException $exception) {
            throw InvalidEnvironmentFile::because(
                $filename,
                $exception->getMessage(),
                $exception,
            );
        }
    }

    /**
     * Resolve standard dotenv expressions and write accepted values to the repository.
     *
     * @return array<string, string|null>
     */
    #[\Override]
    public function load(EnvironmentRepository $repository): array
    {
        return (new Loader)->load($repository, $this->entries);
    }
}
