<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Aggregate;

use EventEngine\Exception\RuntimeException;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\Message;
use EventEngine\Runtime\Flavour;

final class FlavouredAggregateRoot
{
    /**
     * @var string
     */
    private $aggregateId;

    /**
     * @var string
     */
    private $aggregateType;

    /**
     * Map with event name being the key and callable apply method for that event being the value
     *
     * @var callable[]
     */
    private $eventApplyMap;

    /**
     * @var mixed
     */
    private $aggregateState;

    /**
     * Current version
     *
     * @var int
     */
    private $version = 0;

    /**
     * List of events that are not committed to the EventStore
     *
     * @var Message[]
     */
    private $recordedEvents = [];

    /**
     * @var Flavour
     */
    private $flavour;

    /**
     * @throws RuntimeException
     */
    public static function reconstituteFromHistory(
        string $aggregateId,
        string $aggregateType,
        array $eventApplyMap,
        Flavour $flavour,
        \Iterator $historyEvents
    ): self {
        $instance = new self($aggregateId, $aggregateType, $eventApplyMap, $flavour);
        $instance->replay($historyEvents);

        return $instance;
    }

    public static function reconstituteFromAggregateState(
        string $aggregateId,
        string $aggregateType,
        array $eventApplyMap,
        Flavour $flavour,
        int $version,
        $state
    ): self {
        $self = new self($aggregateId, $aggregateType, $eventApplyMap, $flavour);
        $self->aggregateState = $state;
        $self->version = $version;
        return $self;
    }

    public function __construct(string  $aggregateId, string $aggregateType, array $eventApplyMap, Flavour $flavour)
    {
        $this->aggregateId = $aggregateId;
        $this->aggregateType = $aggregateType;
        $this->eventApplyMap = $eventApplyMap;
        $this->flavour = $flavour;
    }

    /**
     * Record an aggregate changed event
     */
    public function recordThat(Message $event): void
    {
        if (! \array_key_exists($event->messageName(), $this->eventApplyMap)) {
            throw new RuntimeException('Wrong event recording detected. Unknown event passed to GenericAggregateRoot: ' . $event->messageName());
        }

        $this->version += 1;

        $event = $event->withMetadata(\array_merge(
            $event->metadata(),
            [
                GenericEvent::META_AGGREGATE_ID => $this->aggregateId(),
                GenericEvent::META_AGGREGATE_TYPE => $this->aggregateType(),
                GenericEvent::META_AGGREGATE_VERSION => $this->version()
            ]
        ));

        $this->recordedEvents[] = $event;

        $this->apply($event);
    }

    public function currentState()
    {
        return $this->aggregateState;
    }

    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function aggregateType(): string
    {
        return $this->aggregateType;
    }

    /**
     * Get pending events and reset stack
     *
     * @return GenericEvent[]
     */
    public function popRecordedEvents(): array
    {
        $pendingEvents = $this->recordedEvents;

        $this->recordedEvents = [];

        $mapMessage = function (Message $event): GenericEvent {
            if(! $event instanceof GenericEvent) {
                return GenericEvent::fromMessage($event);
            }

            return $event;
        };

        foreach ($pendingEvents as $i => $event) {
            $pendingEvents[$i] = $mapMessage($this->flavour->prepareNetworkTransmission($event));
        }

        return $pendingEvents;
    }

    /**
     * Replay past events
     *
     * @throws RuntimeException
     */
    public function replay(\Iterator $historyEvents): void
    {
        foreach ($historyEvents as $pastEvent) {
            /** @var GenericEvent $pastEvent */
            $this->version = $pastEvent->version();

            $pastEvent = $this->flavour->convertMessageReceivedFromNetwork($pastEvent, true);
            $this->apply($pastEvent);
        }
    }

    public function apply(Message $event): void
    {
        $apply = $this->eventApplyMap[$event->messageName()];

        if ($this->aggregateState === null) {
            $newArState = $this->flavour->callApplyFirstEvent($apply, $event);
        } else {
            $newArState = $this->flavour->callApplySubsequentEvent($apply, $this->aggregateState, $event);
        }

        if (null === $newArState) {
            throw new \RuntimeException('Apply function for ' . $event->messageName() . ' did not return a new aggregate state.');
        }

        $this->aggregateState = $newArState;
    }
}
