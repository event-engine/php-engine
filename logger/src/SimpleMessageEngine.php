<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Logger;

use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\Message;
use EventEngine\Util\VariableType;
use Psr\Log\LoggerInterface;

class SimpleMessageEngine implements LogEngine
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    public function initializedFromCachedConfig(&$config): void
    {
        $this->logger->info("Initialized from cached config");
    }

    public function initializedAfterLoadingDescriptions(array &$commandMap, array &$eventMap, array &$queryMap): void
    {
        $this->logger->info("Initialized after loading descriptions");
    }

    public function bootstrapped(string $env, bool $debugMode): void
    {
        $this->logger->info("Bootstrapped environment {$env} in " . (($debugMode)? 'debug' : 'normal') . " mode");
    }

    public function dispatchStarted(Message $message): void
    {
        $this->logger->info("Dispatching of {$message->messageType()} {$message->messageName()} started");
    }

    public function eventListenerCalled($listener, Message $event): void
    {
        $this->logger->info("Called event listener" . VariableType::determine($listener) . " with event {$event->messageName()}");
    }

    public function queryResolverCalled($resolver, Message $query): void
    {
        $this->logger->info("Called query resolver" . VariableType::determine($resolver) . " with query {$query->messageName()}");
    }

    public function preProcessorCalled($preProcessor, Message $orgCommand, Message $returnedCommand): void
    {
        $this->logger->info("Called command preprocessor" . VariableType::determine($preProcessor) . " with command {$orgCommand->messageName()}");
    }

    public function preProcessorReturnedDispatchResult($preProcessor, Message $command, CommandDispatchResult $result)
    {
        $this->logger->info(sprintf(
            "Command pre processor %s returned CommandDispatchResult. Stopping dispatch using the result.",
            VariableType::determine($preProcessor)
        ));
    }

    public function contextProviderCalled($contextProvider, Message $command, $returnedContext)
    {
        $this->logger->info("Called context provider " . VariableType::determine($contextProvider) . " with command {$command->messageName()}");
    }

    public function eventPublished(Message $event): void
    {
        $this->logger->info(" Event {$event->messageName()} published on event queue");
    }

    public function newProcessCreated(string $processType, string $processId, GenericEvent ...$events): void
    {
        $this->logger->info("New aggregate of type $processType with id $processId created");
    }


    public function existingProcessChanged(string $processType, string $processId, $processState, GenericEvent ...$events)
    {
        $this->logger->info("Changed existing aggregate of type $processType with id $processId");
    }

    public function processStateLoaded(string $processType, string $processId, int $processVersion)
    {
        $this->logger->info("Loaded aggregate state of type $processType with id $processId and version $processVersion");
    }


    public function projectionHandledEvent(string $projectionName, GenericEvent $event)
    {
        $this->logger->info("Projection $projectionName handled event: {$event->messageName()} ({$event->uuid()->toString()})");
    }

    public function projectionSetUp(string $projectionName)
    {
        $this->logger->info("Projection $projectionName set up");
    }

    public function projectionDeleted(string $projectionName)
    {
        $this->logger->info("Projection $projectionName deleted");
    }
}
