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

use EventEngine\Aggregate\MetadataProvider;
use EventEngine\Exception\RuntimeException;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageBag;
use EventEngine\Messaging\MessageFactory;
use EventEngine\Messaging\MessageFactoryAware;
use EventEngine\Runtime\Oop\AggregateAndEventBag;
use EventEngine\Runtime\Oop\Port;
use EventEngine\Util\MapIterator;

/**
 * Class OopFlavour
 *
 * Event Sourcing can be implemented using either a functional programming approach (pure aggregate functions + immutable data types)
 * or an object-oriented approach with stateful aggregates. The latter is supported by the OopFlavour.
 *
 * Aggregates manage their state internally. Event Engine takes over the rest like history replays and event persistence.
 * You can focus on the business logic with a 100% decoupled domain model.
 *
 * Decoupling is achieved by implementing the Oop\Port tailored to your domain model.
 *
 * The OopFlavour uses a FunctionalFlavour internally. This is because the OopFlavour also requires type-safe messages.
 *
 *
 * @package EventEngine\Runtime
 */
final class OopFlavour implements Flavour, MessageFactoryAware
{
    /**
     * @var Port
     */
    private $port;

    /**
     * @var FunctionalFlavour
     */
    private $functionalFlavour;

    public function __construct(Port $port, FunctionalFlavour $flavour)
    {
        $this->port = $port;
        $this->functionalFlavour = $flavour;
    }

    /**
     * {@inheritdoc}
     */
    public function callAggregateFactory(string $aggregateType, callable $aggregateFunction, Message $command, $context = null): \Generator
    {
        if (! $command instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        $aggregate = $this->port->callAggregateFactory($aggregateType, $aggregateFunction, $command->get(MessageBag::MESSAGE), $context);

        $events = $this->port->popRecordedEvents($aggregate);

        $isFirstEvent = true;

        yield from new MapIterator(new \ArrayIterator($events), function ($event) use ($command, $aggregate, $aggregateType, &$isFirstEvent) {
            if (null === $event) {
                return null;
            }

            $decoratedEvent = $this->functionalFlavour->decorateEvent($event);

            if($isFirstEvent) {
                $decoratedEvent = $decoratedEvent->withMessage(new AggregateAndEventBag($aggregate, $event));
                $isFirstEvent = false;
            }

            return $decoratedEvent->withAddedMetadata(GenericEvent::META_CAUSATION_ID, $command->uuid()->toString())
                ->withAddedMetadata(GenericEvent::META_CAUSATION_NAME, $command->messageName());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function callSubsequentAggregateFunction(string $aggregateType, callable $aggregateFunction, $aggregateState, Message $command, $context = null): \Generator
    {
        if (! $command instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        $this->port->callAggregateWithCommand($aggregateState, $command->get(MessageBag::MESSAGE), $context);

        $events = $this->port->popRecordedEvents($aggregateState);

        yield from new MapIterator(new \ArrayIterator($events), function ($event) use ($command) {
            if (null === $event) {
                return null;
            }

            return $this->functionalFlavour->decorateEvent($event)
                ->withAddedMetadata(GenericEvent::META_CAUSATION_ID, $command->uuid()->toString())
                ->withAddedMetadata(GenericEvent::META_CAUSATION_NAME, $command->messageName());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function callApplyFirstEvent(callable $applyFunction, Message $event)
    {
        if (! $event instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        $aggregateAndEventBag = $event->get(MessageBag::MESSAGE);

        if (! $aggregateAndEventBag instanceof AggregateAndEventBag) {
            throw new RuntimeException('MessageBag passed to ' . __METHOD__ . ' should contain a ' . AggregateAndEventBag::class . ' message.');
        }

        $aggregate = $aggregateAndEventBag->aggregate();
        $event = $aggregateAndEventBag->event();

        $this->port->applyEvent($aggregate, $event);

        return $aggregate;
    }

    public function callApplySubsequentEvent(callable $applyFunction, $aggregateState, Message $event)
    {
        if (! $event instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        $this->port->applyEvent($aggregateState, $event->get(MessageBag::MESSAGE));

        return $aggregateState;
    }

    /**
     * {@inheritdoc}
     */
    public function callCommandPreProcessor($preProcessor, Message $command)
    {
        return $this->functionalFlavour->callCommandPreProcessor($preProcessor, $command);
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, Message $command): string
    {
        return $this->functionalFlavour->getAggregateIdFromCommand($aggregateIdPayloadKey, $command);
    }

    /**
     * {@inheritdoc}
     */
    public function callContextProvider($contextProvider, Message $command)
    {
        return $this->functionalFlavour->callContextProvider($contextProvider, $command);
    }

    /**
     * {@inheritdoc}
     */
    public function prepareNetworkTransmission(Message $message): Message
    {
        if ($message instanceof MessageBag) {
            $innerEvent = $message->getOrDefault(MessageBag::MESSAGE, new \stdClass());

            if ($innerEvent instanceof AggregateAndEventBag) {
                $message = $message->withMessage($innerEvent->event());
            }
        }

        return $this->functionalFlavour->prepareNetworkTransmission($message);
    }

    /**
     * {@inheritdoc}
     */
    public function convertMessageReceivedFromNetwork(Message $message, $aggregateEvent = false): Message
    {
        $customMessageInBag = $this->functionalFlavour->convertMessageReceivedFromNetwork($message);

        if ($aggregateEvent && $message->messageType() === Message::TYPE_EVENT) {
            $aggregateType = $message->metadata()[GenericEvent::META_AGGREGATE_TYPE] ?? null;
            $aggregateVersion = $message->metadata()[GenericEvent::META_AGGREGATE_VERSION] ?? 0;

            if($aggregateVersion !== 1) {
                return $customMessageInBag;
            }

            if (null === $aggregateType) {
                throw new RuntimeException('Event passed to ' . __METHOD__ . ' should have a metadata key: ' . GenericEvent::META_AGGREGATE_TYPE);
            }

            if (! $customMessageInBag instanceof MessageBag) {
                throw new RuntimeException('FunctionalFlavour is expected to return a ' . MessageBag::class);
            }

            $aggregate = $this->port->reconstituteAggregate((string) $aggregateType, [$customMessageInBag->get(MessageBag::MESSAGE)]);

            $customMessageInBag = $customMessageInBag->withMessage(new AggregateAndEventBag($aggregate, $customMessageInBag->get(MessageBag::MESSAGE)));
        }

        return $customMessageInBag;
    }

    /**
     * {@inheritdoc}
     */
    public function callProjector($projector, string $projectionVersion, string $projectionName, Message $event): void
    {
        $this->functionalFlavour->callProjector($projector, $projectionVersion, $projectionName, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function convertAggregateStateToArray(string $aggregateType, $aggregateState): array
    {
        return $this->port->serializeAggregate($aggregateState);
    }

    public function canProvideAggregateMetadata(string $aggregateType): bool
    {
        return $this->port instanceof MetadataProvider;
    }

    public function provideAggregateMetadata(string $aggregateType, int $version, $aggregateState): array
    {
        if($this->port instanceof MetadataProvider) {
            return $this->port->provideAggregateMetadata($aggregateType, $version, $aggregateState);
        }

        return [];
    }

    public function canBuildAggregateState(string $aggregateType): bool
    {
        return true;
    }

    public function buildAggregateState(string $aggregateType, array $state, int $version)
    {
        return $this->port->reconstituteAggregateFromStateArray($aggregateType, $state, $version);
    }

    public function setMessageFactory(MessageFactory $messageFactory): void
    {
        $this->functionalFlavour->setMessageFactory($messageFactory);
    }

    public function callEventListener(callable $listener, Message $event)
    {
        return $this->functionalFlavour->callEventListener($listener, $event);
    }

    public function callQueryResolver($resolver, Message $query)
    {
        return $this->functionalFlavour->callQueryResolver($resolver, $query);
    }
}
