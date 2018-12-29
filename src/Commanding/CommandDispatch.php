<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Commanding;

use EventEngine\Aggregate\ContextProvider;
use EventEngine\DocumentStore\DocumentStore;
use EventEngine\EventStore\EventStore;
use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageProducer;
use EventEngine\Runtime\Flavour;
use EventEngine\Util\Await;

final class CommandDispatch
{
    /**
     * @param Message $command
     * @param Flavour $flavour
     * @param EventStore $eventStore
     * @param array $preProcessors
     * @param array $processorDescription
     * @param array $aggregateDescriptions
     * @param MessageProducer $eventQueue
     * @param DocumentStore|null $documentStore
     * @param ContextProvider|null $contextProvider
     * @return CommandDispatchResult
     * @throws \Throwable
     */
    public static function exec(
        Message $command,
        Flavour $flavour,
        EventStore $eventStore,
        array &$preProcessors,
        array &$processorDescription,
        array &$aggregateDescriptions,
        MessageProducer $eventQueue,
        DocumentStore $documentStore = null,
        ContextProvider $contextProvider = null): CommandDispatchResult
    {
        foreach ($preProcessors as $preProcessor) {
            $command = $flavour->callCommandPreProcessor($preProcessor, $command);

            if($command instanceof CommandDispatchResult) {
                return $command;
            }
        }

        $commandProcessor = CommandProcessor::fromDescriptionArraysAndDependencies(
            $processorDescription,
            $aggregateDescriptions,
            $flavour,
            $eventStore,
            $documentStore,
            $contextProvider
        );

        $recordedEvents = $commandProcessor($command);

        foreach ($recordedEvents as $event) {
            Await::lastResult($eventQueue->produce($event));
        }

        $arId = $flavour->getAggregateIdFromCommand($processorDescription['aggregateIdentifier'], $command);

        return CommandDispatchResult::forCommandHandledByAggregate(
            $command,
            $arId,
            ...$recordedEvents
        );
    }
}
