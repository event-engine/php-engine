<?php
declare(strict_types=1);

namespace EventEngine\Aggregate;

use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\MessageBag;
use EventEngine\Runtime\Flavour;

final class AggregateEventEnvelope
{
    /**
     * @var string
     */
    private $eventName;

    /**
     * @var mixed
     */
    private $event;

    /**
     * @var string
     */
    private $aggregateId;

    /**
     * @var string
     */
    private $aggregateType;

    /**
     * @var int
     */
    private $aggregateVersion;

    /**
     * @var array
     */
    private $rawPayload;

    /**
     * @var array
     */
    private $metadata;

    public static function fromGenericEvent(GenericEvent $event, Flavour $flavour): self
    {
        $aggregateType = $event->getMeta(GenericEvent::META_AGGREGATE_TYPE);
        $aggregateId = $event->getMeta(GenericEvent::META_AGGREGATE_ID);
        $aggregateVersion = $event->getMeta(GenericEvent::META_AGGREGATE_VERSION);
        $metadata = $event->metadata();
        $rawPayload = $event->payload();
        $eventName = $event->messageName();

        $event = $flavour->convertMessageReceivedFromNetwork($event, true);

        if($event instanceof MessageBag) {
            $event = $event->get(MessageBag::MESSAGE);
        }

        return new self(
            $eventName,
            $aggregateType,
            $aggregateId,
            $aggregateVersion,
            $event,
            $rawPayload,
            $metadata
        );
    }

    private function __construct(
        string $eventName,
        string $aggregateType,
        string $aggregateId,
        int $aggregateVersion,
        object $event,
        array $rawPayload,
        array $metadata
    ) {
        $this->eventName = $eventName;
        $this->aggregateType = $aggregateType;
        $this->aggregateId = $aggregateId;
        $this->aggregateVersion = $aggregateVersion;
        $this->event = $event;
        $this->rawPayload = $rawPayload;
        $this->metadata = $metadata;
    }

    /**
     * @return string
     */
    public function eventName(): string
    {
        return $this->eventName;
    }

    /**
     * @return mixed
     */
    public function event()
    {
        return $this->event;
    }

    /**
     * @return string
     */
    public function aggregateId(): string
    {
        return $this->aggregateId;
    }

    /**
     * @return string
     */
    public function aggregateType(): string
    {
        return $this->aggregateType;
    }

    /**
     * @return int
     */
    public function aggregateVersion(): int
    {
        return $this->aggregateVersion;
    }

    /**
     * @return array
     */
    public function rawPayload(): array
    {
        return $this->rawPayload;
    }

    /**
     * @return array
     */
    public function metadata(): array
    {
        return $this->metadata;
    }
}
