<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Commanding;

use EventEngine\Aggregate\ContextProvider;
use EventEngine\DocumentStore\DocumentStore;
use EventEngine\EventEngine;
use EventEngine\EventStore\EventStore;
use EventEngine\Exception\RuntimeException;
use EventEngine\Logger\LogEngine;
use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageProducer;
use EventEngine\Runtime\Flavour;

final class CommandDispatch
{
    /**
     * @param Message $command
     * @param Flavour $flavour
     * @param EventStore $eventStore
     * @param LogEngine $log
     * @param array $processorDescription
     * @param array $aggregateDescriptions
     * @param bool $autoPublish
     * @param bool $autoProject
     * @param MessageProducer $eventQueue
     * @param EventEngine $eventEngine
     * @param DocumentStore|null $documentStore
     * @param ContextProvider[]|mixed[] $contextProviders
     * @param array $services
     * @param bool $forwardMetadata
     * @return CommandDispatchResult
     * @throws \Throwable
     */
    public static function exec(
        Message $command,
        Flavour $flavour,
        EventStore $eventStore,
        LogEngine $log,
        array &$processorDescription,
        array &$aggregateDescriptions,
        bool $autoPublish,
        bool $autoProject,
        MessageProducer $eventQueue,
        EventEngine $eventEngine,
        DocumentStore $documentStore = null,
        array $contextProviders = [],
        array $services = [],
        bool $forwardMetadata = false
    ): CommandDispatchResult
    {
        if(empty($processorDescription)) {
            throw new RuntimeException("No routing information found for command {$command->messageName()}");
        }

        $commandProcessor = CommandProcessor::fromDescriptionArraysAndDependencies(
            $processorDescription,
            $aggregateDescriptions,
            $flavour,
            $eventStore,
            $log,
            $eventEngine,
            $documentStore,
            $contextProviders,
            $services,
            $forwardMetadata
        );

        $recordedEvents = $commandProcessor($command);

        if($autoProject) {
            $eventEngine->runAllProjections($eventEngine->writeModelStreamName(), ...$recordedEvents);
        }

        if($autoPublish) {
            foreach ($recordedEvents as $event) {
                $eventQueue->produce($event);
                $log->eventPublished($event);
            }
        }

        $arId = $flavour->getAggregateIdFromCommand($processorDescription['aggregateIdentifier'], $command);

        return CommandDispatchResult::forCommandHandledByAggregate(
            $command,
            $arId,
            ...$recordedEvents
        );
    }
}
