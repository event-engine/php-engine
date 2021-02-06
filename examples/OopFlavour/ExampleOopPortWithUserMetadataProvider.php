<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\OopFlavour;

use EventEngine\Aggregate\MetadataProvider;
use EventEngine\Exception\InvalidArgumentException;
use EventEngine\Runtime\Oop\Port;
use EventEngine\Util\VariableType;
use EventEngineExample\FunctionalFlavour\Command\ChangeEmail;
use EventEngineExample\FunctionalFlavour\Command\ChangeUsername;
use EventEngineExample\OopFlavour\Aggregate\User;

final class ExampleOopPortWithUserMetadataProvider implements Port, MetadataProvider
{
    /**
     * {@inheritdoc}
     */
    public function callAggregateFactory(string $aggregateType, callable $aggregateFactory, $customCommand, ...$contextServices)
    {
        return $aggregateFactory($customCommand, ...$contextServices);
    }

    /**
     * {@inheritdoc}
     */
    public function callAggregateWithCommand($aggregate, $customCommand, ...$contextServices): void
    {
        switch (\get_class($customCommand)) {
            case ChangeUsername::class:
                /** @var User $aggregate */
                $aggregate->changeName($customCommand);
                break;
            case ChangeEmail::class:
                /** @var User $aggregate */
                $aggregate->changeEmail($customCommand);
                break;
            default:
                throw new InvalidArgumentException('Unknown command: ' . VariableType::determine($customCommand));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function popRecordedEvents($aggregate): array
    {
        //Duck typing, do not do this in production but rather use your own interfaces
        return $aggregate->popRecordedEvents();
    }

    /**
     * {@inheritdoc}
     */
    public function applyEvent($aggregate, $customEvent): void
    {
        //Duck typing, do not do this in production but rather use your own interfaces
        $aggregate->apply($customEvent);
    }

    /**
     * {@inheritdoc}
     */
    public function serializeAggregate($aggregate): array
    {
        //Duck typing, do not do this in production but rather use your own interfaces
        return $aggregate->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function reconstituteAggregate(string $aggregateType, iterable $events)
    {
        switch ($aggregateType) {
            case User::TYPE:
                return User::reconstituteFromHistory($events);
                break;
            default:
                throw new InvalidArgumentException("Unknown aggregate type $aggregateType");
        }
    }

    /**
     * @param string $aggregateType
     * @param array $state
     * @param int $version
     * @return mixed Aggregate instance
     */
    public function reconstituteAggregateFromStateArray(string $aggregateType, array $state, int $version)
    {
        switch ($aggregateType) {
            case User::TYPE:
                return User::reconstituteFromState($state);
                break;
            default:
                throw new InvalidArgumentException("Unknown aggregate type $aggregateType");
        }
    }

    public function provideAggregateMetadata(string $aggregateType, int $version, $aggregateState): array
    {
        return ['version' => $version];
    }
}
