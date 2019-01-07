<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Runtime;

use EventEngine\Process\ContextProvider;
use EventEngine\Commanding\CommandPreProcessor;
use EventEngine\Data\DataConverter;
use EventEngine\Data\ImmutableRecordDataConverter;
use EventEngine\Exception\InvalidEventFormat;
use EventEngine\Exception\MissingPid;
use EventEngine\Exception\NoGenerator;
use EventEngine\Exception\RuntimeException;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageFactory;
use EventEngine\Messaging\MessageFactoryAware;
use EventEngine\Projecting\ProcessStateProjector;
use EventEngine\Projecting\Projector;
use EventEngine\Querying\Resolver;
use EventEngine\Util\MapIterator;
use EventEngine\Util\VariableType;

/**
 * Class PrototypingFlavour
 *
 * Default Flavour used by Event Engine if no other Flavour is configured.
 *
 * This Flavour is tailored to rapid prototyping of event sourced domain models. Event Engine passes
 * generic messages directly into pure process functions, command preprocessors, context providers and so on.
 *
 * Process functions can use a short array syntax to describe events that should be recorded by Event Engine.
 * Check the tutorial at: https://event-engine.github.io/event-engine/php-tutorial/
 * It uses the PrototypingFlavour.
 *
 * @package EventEngine\Runtime
 */
final class PrototypingFlavour implements Flavour, MessageFactoryAware
{
    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var DataConverter
     */
    private $stateConverter;

    public function __construct(DataConverter $dataConverter = null)
    {
        if (null === $dataConverter) {
            $dataConverter = new ImmutableRecordDataConverter();
        }

        $this->stateConverter = $dataConverter;
    }

    public function setMessageFactory(MessageFactory $messageFactory): void
    {
        $this->messageFactory = $messageFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function callCommandPreProcessor($preProcessor, Message $command)
    {
        if (! $preProcessor instanceof CommandPreProcessor) {
            throw new RuntimeException(
                'By default a CommandPreProcessor should implement the interface: '
                . CommandPreProcessor::class . '. Got ' . VariableType::determine($preProcessor)
            );
        }

        return $preProcessor->preProcess($command);
    }

    public function getPidFromCommand(string $pidKey, Message $command): string
    {
        $payload = $command->payload();

        if (! \array_key_exists($pidKey, $payload)) {
            throw MissingPid::inCommand($command, $pidKey);
        }

        return (string) $payload[$pidKey];
    }

    /**
     * {@inheritdoc}
     */
    public function callContextProvider($contextProvider, Message $command)
    {
        if (! $contextProvider instanceof ContextProvider) {
            throw new RuntimeException(
                'By default a ContextProvider should implement the interface: '
                . ContextProvider::class . '. Got ' . VariableType::determine($contextProvider)
            );
        }

        return $contextProvider->provide($command);
    }

    /**
     * {@inheritdoc}
     */
    public function callProcessFactory(string $processType, callable $processFunction, Message $command, $context = null): \Generator
    {
        $events = $processFunction($command, $context);

        if (! $events instanceof \Generator) {
            throw NoGenerator::forProcessTypeAndCommand($processType, $command);
        }

        yield from new MapIterator($events, function ($event) use ($processType, $command) {
            if (null === $event) {
                return null;
            }

            return $this->mapToMessage($event, $processType, $command);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function callProcessFunction(string $processType, callable $processFunction, $processState, Message $command, $context = null): \Generator
    {
        $events = $processFunction($processState, $command, $context);

        if (! $events instanceof \Generator) {
            throw NoGenerator::forProcessTypeAndCommand($processType, $command);
        }

        yield from new MapIterator($events, function ($event) use ($processType, $command) {
            if (null === $event) {
                return null;
            }

            return $this->mapToMessage($event, $processType, $command);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function callApplyFirstEvent(callable $applyFunction, Message $event)
    {
        return $applyFunction($event);
    }

    /**
     * {@inheritdoc}
     */
    public function callApplySubsequentEvent(callable $applyFunction, $processState, Message $event)
    {
        return $applyFunction($processState, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function prepareNetworkTransmission(Message $message): Message
    {
        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function convertMessageReceivedFromNetwork(Message $message, $processEvent = false): Message
    {
        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function callProjector($projector, string $projectionVersion, string $projectionName, Message $event): void
    {
        if (! $projector instanceof Projector && ! $projector instanceof ProcessStateProjector) {
            throw new RuntimeException(__METHOD__ . ' can only call instances of ' . Projector::class);
        }

        $projector->handle($projectionVersion, $projectionName, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function convertProcessStateToArray(string $processType, $processState): array
    {
        return $this->stateConverter->convertDataToArray($processType, $processState);
    }

    public function canBuildProcessState(string $processType): bool
    {
        return $this->stateConverter->canConvertTypeToData($processType);
    }

    public function buildProcessState(string $processType, array $state)
    {
        return $this->stateConverter->convertArrayToData($processType, $state);
    }

    public function callEventListener(callable $listener, Message $event): void
    {
        $listener($event);
    }

    public function callQueryResolver($resolver, Message $query)
    {
        if(! $resolver instanceof Resolver) {
            throw new RuntimeException(__METHOD__ . ' only works with instances of ' . Resolver::class);
        }

        return $resolver->resolve($query);
    }

    private function mapToMessage($event, string $processType, Message $command): Message
    {
        if (! \is_array($event) || ! \array_key_exists(0, $event) || ! \array_key_exists(1, $event)
            || ! \is_string($event[0]) || ! \is_array($event[1])) {
            throw InvalidEventFormat::invalidEvent($processType, $command);
        }
        [$eventName, $payload] = $event;

        $metadata = [];

        if (\array_key_exists(2, $event)) {
            $metadata = $event[2];
            if (! \is_array($metadata)) {
                throw InvalidEventFormat::invalidMetadata($metadata, $processType, $command);
            }
        }

        /** @var GenericEvent $event */
        $event = $this->messageFactory->createMessageFromArray($eventName, [
            'payload' => $payload,
            'metadata' => \array_merge([
                '_causation_id' => $command->uuid()->toString(),
                '_causation_name' => $command->messageName(),
            ], $metadata),
        ]);

        return $event;
    }
}

