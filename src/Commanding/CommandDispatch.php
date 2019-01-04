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
use EventEngine\Exception\RuntimeException;
use EventEngine\Logger\LogEngine;
use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageProducer;
use EventEngine\Runtime\Flavour;
use EventEngine\Util\Await;
use EventEngine\Util\VariableType;
use Psr\Log\LoggerInterface;

final class CommandDispatch
{
    /**
     * @param Message $command
     * @param Flavour $flavour
     * @param EventStore $eventStore
     * @param LoggerInterface $log
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
        LogEngine $log,
        array &$preProcessors,
        array &$processorDescription,
        array &$aggregateDescriptions,
        MessageProducer $eventQueue,
        DocumentStore $documentStore = null,
        ContextProvider $contextProvider = null): CommandDispatchResult
    {
        foreach ($preProcessors as $preProcessor) {
            $orgCommand = $command;
            $command = $flavour->callCommandPreProcessor($preProcessor, $command);

            if($command instanceof CommandDispatchResult) {
                $log->preProcessorReturnedDispatchResult($preProcessor, $orgCommand, $command);
                return $command;
            } else {
                $log->preProcessorCalled($preProcessor, $orgCommand, $command);
            }
        }

        if(empty($processorDescription)) {
            throw new RuntimeException("No routing information found for command {$command->messageName()}");
        }

        $commandProcessor = CommandProcessor::fromDescriptionArraysAndDependencies(
            $processorDescription,
            $aggregateDescriptions,
            $flavour,
            $eventStore,
            $log,
            $documentStore,
            $contextProvider
        );

        $recordedEvents = $commandProcessor($command);

        foreach ($recordedEvents as $event) {
            Await::lastResult($eventQueue->produce($event));
            $log->eventPublished($event);
        }

        $arId = $flavour->getAggregateIdFromCommand($processorDescription['aggregateIdentifier'], $command);

        return CommandDispatchResult::forCommandHandledByAggregate(
            $command,
            $arId,
            ...$recordedEvents
        );
    }
}
