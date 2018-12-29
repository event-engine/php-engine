<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Prooph\V7\EventStore;

use EventEngine\EventStore\EventStore;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Util\MapIterator;
use Prooph\Common\Messaging\DomainMessage;
use Prooph\EventStore\EventStore as ProophV7EventStore;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\Metadata\Operator;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;

final class ProophEventStore implements EventStore
{
    /**
     * @var ProophV7EventStore
     */
    private $pes;

    public function __construct(ProophV7EventStore $pes)
    {
        $this->pes = $pes;
    }

    public function createStream(string $streamName): void
    {
        $this->pes->create(new Stream(new StreamName($streamName), new \ArrayIterator([])));
    }

    public function deleteStream(string $streamName): void
    {
        $this->pes->delete(new StreamName($streamName));
    }

    public function appendTo(string $streamName, GenericEvent ...$events): void
    {
        $this->pes->appendTo(new StreamName($streamName), new MapIterator(new \ArrayIterator($events), function (GenericEvent $event): DomainMessage {
            return GenericProophEvent::fromArray($event->toArray());
        }));
    }

    /**
     * @param string $streamName
     * @param string $aggregateType
     * @param string $aggregateId
     * @param int $minVersion
     * @return \Iterator GenericEvent[]
     */
    public function loadAggregateEvents(string $streamName, string $aggregateType, string $aggregateId, int $minVersion = 1): \Iterator
    {
        $matcher = new MetadataMatcher();

        $matcher->withMetadataMatch(GenericEvent::META_AGGREGATE_TYPE, Operator::EQUALS(), $aggregateType)
            ->withMetadataMatch(GenericEvent::META_AGGREGATE_ID, Operator::EQUALS(), $aggregateId);

        if($minVersion > 1) {
            $matcher->withMetadataMatch(GenericEvent::META_AGGREGATE_VERSION, Operator::GREATER_THAN_EQUALS(), $minVersion);
        }

        return $this->prepareEventMapping(
            $this->pes->load(
                new StreamName($streamName),
                1,
                null,
                $matcher
            )
        );
    }

    /**
     * @param string $streamName
     * @param string $correlationId
     * @return \Iterator GenericEvent[]
     */
    public function loadEventsByCorrelationId(string $streamName, string $correlationId): \Iterator
    {
        $matcher = new MetadataMatcher();

        $matcher->withMetadataMatch(GenericEvent::META_CORRELATION_ID, Operator::EQUALS(), $correlationId);

        return $this->prepareEventMapping(
            $this->pes->load(
                new StreamName($streamName),
                1,
                null,
                $matcher
            )
        );
    }

    /**
     * @param string $streamName
     * @param string $causationId
     * @return \Iterator GenericEvent[]
     */
    public function loadEventsByCausationId(string $streamName, string $causationId): \Iterator
    {
        $matcher = new MetadataMatcher();

        $matcher->withMetadataMatch(GenericEvent::META_CAUSATION_ID, Operator::EQUALS(), $causationId);

        return $this->prepareEventMapping(
            $this->pes->load(
                new StreamName($streamName),
                1,
                null,
                $matcher
            )
        );
    }
    
    private function prepareEventMapping(\Iterator $events): \Iterator
    {
        return new MapIterator($events, function (GenericProophEvent $proophEvent): GenericEvent {
            return GenericEvent::fromArray($proophEvent->toArray());
        });
    }
}
