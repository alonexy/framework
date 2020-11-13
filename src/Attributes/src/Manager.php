<?php

/**
 * This file is part of Spiral Framework package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spiral\Attributes;

use Spiral\Attributes\Exception\InitializationException;
use Spiral\Attributes\Exception\NotFoundException;
use Spiral\Attributes\Reader\Composite;
use Spiral\Attributes\Reader\DoctrineReader;
use Spiral\Attributes\Reader\MergeReader;
use Spiral\Attributes\Reader\NativeReader;
use Spiral\Attributes\Reader\SelectiveReader;

/**
 * @psalm-type ReadersList = iterable<string|mixed, ReaderInterface>
 */
class Manager implements ManagerInterface
{
    /**
     * @var string
     */
    private const ERROR_DRIVER_NOT_FOUND = 'Reader "%s" was not registered';

    /**
     * @var string
     */
    private const ERROR_DRIVER_NOT_AVAILABLE = 'There are no metadata readers available';

    /**
     * @psalm-var array<positive-int, class-string<ReaderInterface>>
     *
     * @var string[]
     */
    private const DEFAULT_READERS = [
        NativeReader::class,
        DoctrineReader::class,
    ];

    /**
     * @psalm-var array<positive-int, class-string<Composite>>
     *
     * @var string[]
     */
    private const DEFAULT_COMPOSITE_READERS = [
        SelectiveReader::class,
        MergeReader::class,
    ];

    /**
     * @var string
     */
    private const DEFAULT_READER_ALIAS = SelectiveReader::class;

    /**
     * @psalm-var array<positive-int, ReaderInterface>
     * @var ReaderInterface[]
     */
    private $readers;

    /**
     * @var string
     */
    private $default;

    /**
     * @param ReaderInterface[] $readers
     * @param string $default
     */
    public function __construct(iterable $readers = [], string $default = self::DEFAULT_READER_ALIAS)
    {
        $this->default = $default;

        $this->registerCustomReaders($readers);

        $errors = $this->registerReaders($this->getDefaultReaders());
        $this->registerCompositeReaders($this->getDefaultCompositeReaders(), $this->readers);

        if (\count($this->readers) === 0) {
            throw $this->bootError($errors);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function get(string $name = self::DEFAULT_READER): ReaderInterface
    {
        if ($name === self::DEFAULT_READER) {
            $name = $this->default;
        }

        $reader = $this->readers[$name] ?? null;

        if ($reader === null) {
            throw new NotFoundException(\sprintf(self::ERROR_DRIVER_NOT_FOUND, $name));
        }

        return $reader;
    }

    /**
     * @psalm-return array<class-string<ReaderInterface>>
     * @return string[]
     */
    protected function getDefaultReaders(): array
    {
        return self::DEFAULT_READERS;
    }

    /**
     * @psalm-return array<class-string<ReaderInterface>>
     * @return string[]
     */
    protected function getDefaultCompositeReaders(): array
    {
        return self::DEFAULT_COMPOSITE_READERS;
    }

    /**
     * @param iterable $errors
     * @return InitializationException
     */
    private function bootError(iterable $errors): InitializationException
    {
        $messages = [];

        foreach ($errors as $reader => $message) {
            $messages[] = \sprintf(' - %s: %s', $reader, $message);
        }

        $message = self::ERROR_DRIVER_NOT_AVAILABLE . ":\n" . \implode($messages);
        return new InitializationException($message);
    }

    /**
     * @param ReaderInterface[] $readers
     * @return void
     */
    private function registerCustomReaders(iterable $readers): void
    {
        foreach ($readers as $name => $reader) {
            $name = \is_string($name) ? $name : \get_class($reader);

            $this->readers[$name] = $reader;
        }
    }

    /**
     * @psalm-return iterable<string, string>
     *
     * @param string[] $readers
     * @param ReaderInterface[] $registered
     * @return void
     */
    private function registerCompositeReaders(iterable $readers, array $registered): void
    {
        if (\count($registered) !== 0) {
            $this->registerReaders($readers, $registered);
        }
    }

    /**
     * @psalm-return iterable<string, string>
     *
     * @param string[] $readers
     * @param array $arguments
     * @return string[]
     */
    private function registerReaders(iterable $readers, array $arguments = []): iterable
    {
        foreach ($readers as $reader) {
            try {
                if (isset($this->readers[$reader])) {
                    continue;
                }

                $this->readers[$reader] = new $reader(...\array_values($arguments));
            } catch (InitializationException $e) {
                yield $reader => $e->getMessage();
            }
        }
    }
}