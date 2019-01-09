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
use EventEngine\EventStore\Stream\Name;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Process\Pid;
use EventEngine\Process\ProcessType;
use EventEngine\Prooph\V7\EventStore\Exception\NoTransactionalStore;
use EventEngine\Util\MapIterator;
use Prooph\Common\Messaging\DomainMessage;
use Prooph\EventStore\EventStore as ProophV7EventStore;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\Metadata\Operator;
use Prooph\EventStore\Stream;
use Prooph\EventStore\StreamName;
use Prooph\EventStore\TransactionalEventStore;

final class ProophEventStore implements EventStore
{
    /**
     * @var ProophV7EventStore|TransactionalEventStore
     */
    private $pes;
    private $manageTransaction;

    public function __construct(ProophV7EventStore $pes, $manageTransaction = false)
    {
        $this->pes = $pes;

        if($manageTransaction && !$pes instanceof TransactionalEventStore) {
            throw NoTransactionalStore::withProophEventStore($pes);
        }

        $this->manageTransaction = $manageTransaction;
    }

    /**
     * @param Name $streamName
     * @throws \Exception
     */
    public function createStream(Name $streamName): void
    {
        if($this->manageTransaction) {
            $this->pes->transactional(function (ProophV7EventStore $eventStore) use ($streamName) {
                $eventStore->create(new Stream(new StreamName($streamName->toString()), new \ArrayIterator([])));
            });
            return;
        }

        $this->pes->create(new Stream(new StreamName($streamName->toString()), new \ArrayIterator([])));
    }

    /**
     * @param Name $streamName
     * @throws \Exception
     */
    public function deleteStream(Name $streamName): void
    {
        if($this->manageTransaction) {
            $this->pes->transactional(function (ProophV7EventStore $eventStore) use ($streamName) {
                $eventStore->delete(new StreamName($streamName->toString()));
            });
            return;
        }

        $this->pes->delete(new StreamName($streamName->toString()));
    }

    /**
     * @param Name $streamName
     * @param GenericEvent ...$events
     * @throws \Exception
     */
    public function appendTo(Name $streamName, GenericEvent ...$events): void
    {
        if($this->manageTransaction) {
            $this->pes->transactional(function (ProophV7EventStore $eventStore) use ($streamName, &$events) {
                $eventStore->appendTo(new StreamName($streamName->toString()), new MapIterator(new \ArrayIterator($events), function (GenericEvent $event): DomainMessage {
                    return GenericProophEvent::fromArray($event->toArray());
                }));
            });
            return;
        }

        $this->pes->appendTo(new StreamName($streamName->toString()), new MapIterator(new \ArrayIterator($events), function (GenericEvent $event): DomainMessage {
            return GenericProophEvent::fromArray($event->toArray());
        }));
    }

    /**
     * @param Name $streamName
     * @param ProcessType $processType
     * @param Pid $processId
     * @param int $minVersion
     * @return \Iterator GenericEvent[]
     */
    public function loadProcessEvents(Name $streamName, ProcessType $processType, Pid $processId, int $minVersion = 1): \Iterator
    {
        $matcher = new MetadataMatcher();

        $matcher->withMetadataMatch(GenericEvent::META_PROCESS_TYPE, Operator::EQUALS(), $processType->toString())
            ->withMetadataMatch(GenericEvent::META_PROCESS_ID, Operator::EQUALS(), $processId->toString());

        if($minVersion > 1) {
            $matcher->withMetadataMatch(GenericEvent::META_PROCESS_VERSION, Operator::GREATER_THAN_EQUALS(), $minVersion);
        }

        return $this->prepareEventMapping(
            $this->pes->load(
                new StreamName($streamName->toString()),
                1,
                null,
                $matcher
            )
        );
    }

    /**
     * @param Name $streamName
     * @param string $correlationId
     * @return \Iterator GenericEvent[]
     */
    public function loadEventsByCorrelationId(Name $streamName, string $correlationId): \Iterator
    {
        $matcher = new MetadataMatcher();

        $matcher->withMetadataMatch(GenericEvent::META_CORRELATION_ID, Operator::EQUALS(), $correlationId);

        return $this->prepareEventMapping(
            $this->pes->load(
                new StreamName($streamName->toString()),
                1,
                null,
                $matcher
            )
        );
    }

    /**
     * @param Name $streamName
     * @param string $causationId
     * @return \Iterator GenericEvent[]
     */
    public function loadEventsByCausationId(Name $streamName, string $causationId): \Iterator
    {
        $matcher = new MetadataMatcher();

        $matcher->withMetadataMatch(GenericEvent::META_CAUSATION_ID, Operator::EQUALS(), $causationId);

        return $this->prepareEventMapping(
            $this->pes->load(
                new StreamName($streamName->toString()),
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
