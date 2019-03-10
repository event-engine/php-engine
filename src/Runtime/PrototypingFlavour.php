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

use EventEngine\Aggregate\ContextProvider;
use EventEngine\Aggregate\MetadataProvider;
use EventEngine\Commanding\CommandController;
use EventEngine\Commanding\CommandPreProcessor;
use EventEngine\Data\DataConverter;
use EventEngine\Data\ImmutableRecordDataConverter;
use EventEngine\Exception\InvalidEventFormat;
use EventEngine\Exception\MissingAggregateIdentifier;
use EventEngine\Exception\NoGenerator;
use EventEngine\Exception\RuntimeException;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageFactory;
use EventEngine\Messaging\MessageFactoryAware;
use EventEngine\Projecting\AggregateProjector;
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
 * generic messages directly into pure aggregate functions, command preprocessors, context providers and so on.
 *
 * Aggregate functions can use a short array syntax to describe events that should be recorded by Event Engine.
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

    /**
     * @var MetadataProvider
     */
    private $aggregateMetadataProvider;

    public function __construct(DataConverter $dataConverter = null, MetadataProvider $metadataProvider = null)
    {
        if (null === $dataConverter) {
            $dataConverter = new ImmutableRecordDataConverter();
        }

        $this->stateConverter = $dataConverter;
        $this->aggregateMetadataProvider = $metadataProvider;
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

    /**
     * @inheritdoc
     */
    public function callCommandController($controller, Message $command)
    {
        if (!is_callable($controller) && ! $controller instanceof CommandController) {
            throw new RuntimeException(
                'By default a CommandController should be a callable or implement the interface: '
                . CommandController::class . '. Got ' . VariableType::determine($controller)
            );
        }

        return is_callable($controller) ? $controller($command) : $controller->process($command);
    }

    public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, Message $command): string
    {
        $payload = $command->payload();

        if (! \array_key_exists($aggregateIdPayloadKey, $payload)) {
            throw MissingAggregateIdentifier::inCommand($command, $aggregateIdPayloadKey);
        }

        return (string) $payload[$aggregateIdPayloadKey];
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
    public function callAggregateFactory(string $aggregateType, callable $aggregateFunction, Message $command, $context = null): \Generator
    {
        $events = $aggregateFunction($command, $context);

        if (! $events instanceof \Generator) {
            throw NoGenerator::forAggregateTypeAndCommand($aggregateType, $command);
        }

        yield from new MapIterator($events, function ($event) use ($aggregateType, $command) {
            if (null === $event) {
                return null;
            }

            return $this->mapToMessage($event, $aggregateType, $command);
        });
    }

    /**
     * {@inheritdoc}
     */
    public function callSubsequentAggregateFunction(string $aggregateType, callable $aggregateFunction, $aggregateState, Message $command, $context = null): \Generator
    {
        $events = $aggregateFunction($aggregateState, $command, $context);

        if (! $events instanceof \Generator) {
            throw NoGenerator::forAggregateTypeAndCommand($aggregateType, $command);
        }

        yield from new MapIterator($events, function ($event) use ($aggregateType, $command) {
            if (null === $event) {
                return null;
            }

            return $this->mapToMessage($event, $aggregateType, $command);
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
    public function callApplySubsequentEvent(callable $applyFunction, $aggregateState, Message $event)
    {
        return $applyFunction($aggregateState, $event);
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
    public function convertMessageReceivedFromNetwork(Message $message, $aggregateEvent = false): Message
    {
        return $message;
    }

    /**
     * {@inheritdoc}
     */
    public function callProjector($projector, string $projectionVersion, string $projectionName, Message $event): void
    {
        if (! $projector instanceof Projector && ! $projector instanceof AggregateProjector) {
            throw new RuntimeException(__METHOD__ . ' can only call instances of ' . Projector::class);
        }

        $projector->handle($projectionVersion, $projectionName, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function convertAggregateStateToArray(string $aggregateType, $aggregateState): array
    {
        return $this->stateConverter->convertDataToArray($aggregateType, $aggregateState);
    }

    public function canProvideAggregateMetadata(string $aggregateType): bool
    {
        return $this->aggregateMetadataProvider !== null;
    }

    public function provideAggregateMetadata(string $aggregateType, int $version, $aggregateState): array
    {
        if($this->aggregateMetadataProvider) {
            return $this->aggregateMetadataProvider->provideAggregateMetadata($aggregateType, $version, $aggregateState);
        }

        return [];
    }

    public function canBuildAggregateState(string $aggregateType): bool
    {
        return $this->stateConverter->canConvertTypeToData($aggregateType);
    }

    public function buildAggregateState(string $aggregateType, array $state, int $version)
    {
        return $this->stateConverter->convertArrayToData($aggregateType, $state);
    }

    public function callEventListener(callable $listener, Message $event)
    {
        return $listener($event);
    }

    public function callQueryResolver($resolver, Message $query)
    {
        if(! $resolver instanceof Resolver) {
            throw new RuntimeException(__METHOD__ . ' only works with instances of ' . Resolver::class);
        }

        return $resolver->resolve($query);
    }

    private function mapToMessage($event, string $aggregateType, Message $command): Message
    {
        if (! \is_array($event) || ! \array_key_exists(0, $event) || ! \array_key_exists(1, $event)
            || ! \is_string($event[0]) || ! \is_array($event[1])) {
            throw InvalidEventFormat::invalidEvent($aggregateType, $command);
        }
        [$eventName, $payload] = $event;

        $metadata = [];

        if (\array_key_exists(2, $event)) {
            $metadata = $event[2];
            if (! \is_array($metadata)) {
                throw InvalidEventFormat::invalidMetadata($metadata, $aggregateType, $command);
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

