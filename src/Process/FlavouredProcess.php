<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Process;

use EventEngine\Exception\RuntimeException;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\Message;
use EventEngine\Runtime\Flavour;

final class FlavouredProcess
{
    /**
     * @var Pid
     */
    private $pid;

    /**
     * @var ProcessType
     */
    private $processType;

    /**
     * Map with event name being the key and callable apply method for that event being the value
     *
     * @var callable[]
     */
    private $eventApplyMap;

    /**
     * @var mixed
     */
    private $processState;

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
        Pid $pid,
        ProcessType $processType,
        array $eventApplyMap,
        Flavour $flavour,
        \Iterator $historyEvents
    ): self {
        $instance = new self($pid, $processType, $eventApplyMap, $flavour);
        $instance->replay($historyEvents);

        return $instance;
    }

    public static function reconstituteFromProcessState(
        Pid $pid,
        ProcessType $processType,
        array $eventApplyMap,
        Flavour $flavour,
        int $version,
        $state
    ): self {
        $self = new self($pid, $processType, $eventApplyMap, $flavour);
        $self->processState = $state;
        $self->version = $version;
        return $self;
    }

    public function __construct(Pid  $processId, ProcessType $processType, array $eventApplyMap, Flavour $flavour)
    {
        $this->pid = $processId;
        $this->processType = $processType;
        $this->eventApplyMap = $eventApplyMap;
        $this->flavour = $flavour;
    }

    /**
     * Record a process changed event
     */
    public function recordThat(Message $event): void
    {
        if (! \array_key_exists($event->messageName(), $this->eventApplyMap)) {
            throw new RuntimeException('Wrong event recording detected. Unknown event passed to GenericProcess: ' . $event->messageName());
        }

        $this->version += 1;

        $event = $event->withMetadata(\array_merge(
            $event->metadata(),
            [
                GenericEvent::META_PROCESS_ID => $this->pid()->toString(),
                GenericEvent::META_PROCESS_TYPE => $this->processType()->toString(),
                GenericEvent::META_PROCESS_VERSION => $this->version()
            ]
        ));

        $this->recordedEvents[] = $event;

        $this->apply($event);
    }

    public function currentState()
    {
        return $this->processState;
    }

    public function pid(): Pid
    {
        return $this->pid;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function processType(): ProcessType
    {
        return $this->processType;
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

        if ($this->processState === null) {
            $newArState = $this->flavour->callApplyFirstEvent($apply, $event);
        } else {
            $newArState = $this->flavour->callApplySubsequentEvent($apply, $this->processState, $event);
        }

        if (null === $newArState) {
            throw new \RuntimeException('Apply function for ' . $event->messageName() . ' did not return a new process state.');
        }

        $this->processState = $newArState;
    }
}
