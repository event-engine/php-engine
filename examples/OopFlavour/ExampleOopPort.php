<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\OopFlavour;

use EventEngine\Exception\InvalidArgumentException;
use EventEngine\Runtime\Oop\Port;
use EventEngine\Util\VariableType;
use EventEngineExample\FunctionalFlavour\Command\ChangeUsername;
use EventEngineExample\OopFlavour\Process\User;

final class ExampleOopPort implements Port
{
    /**
     * {@inheritdoc}
     */
    public function callProcessFactory(string $processType, callable $processFactory, $customCommand, $context = null)
    {
        return $processFactory($customCommand, $context);
    }

    /**
     * {@inheritdoc}
     */
    public function callProcessWithCommand($process, $customCommand, $context = null): void
    {
        switch (\get_class($customCommand)) {
            case ChangeUsername::class:
                /** @var User $process */
                $process->changeName($customCommand);
                break;
            default:
                throw new InvalidArgumentException('Unknown command: ' . VariableType::determine($customCommand));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function popRecordedEvents($process): array
    {
        //Duck typing, do not do this in production but rather use your own interfaces
        return $process->popRecordedEvents();
    }

    /**
     * {@inheritdoc}
     */
    public function applyEvent($process, $customEvent): void
    {
        //Duck typing, do not do this in production but rather use your own interfaces
        $process->apply($customEvent);
    }

    /**
     * {@inheritdoc}
     */
    public function serializeProcess($process): array
    {
        //Duck typing, do not do this in production but rather use your own interfaces
        return $process->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function reconstituteProcess(string $processType, iterable $events)
    {
        switch ($processType) {
            case User::TYPE:
                return User::reconstituteFromHistory($events);
                break;
            default:
                throw new InvalidArgumentException("Unknown aggregate type $processType");
        }
    }

    /**
     * @param string $processType
     * @param array $state
     * @return mixed Process instance
     */
    public function reconstituteProcessFromStateArray(string $processType, array $state)
    {
        switch ($processType) {
            case User::TYPE:
                return User::reconstituteFromState($state);
                break;
            default:
                throw new InvalidArgumentException("Unknown aggregate type $processType");
        }
    }
}
