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

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\EventStore\EventStore;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Persistence\DeletableState;
use EventEngine\Persistence\MultiModelStore;
use EventEngine\Runtime\Flavour;

final class GenericAggregateRepository
{
    /**
     * @var EventStore
     */
    private $eventStore;

    /**
     * @var DocumentStore|null
     */
    private $documentStore;

    /**
     * @var string
     */
    private $streamName;

    /**
     * @var string|null
     */
    private $aggregateCollection;

    /**
     * @var Flavour
     */
    private $flavour;

    public function __construct(
        Flavour $flavour,
        EventStore $eventStore,
        string $streamName,
        DocumentStore $documentStore = null,
        string $aggregateCollection = null
    ) {
        $this->flavour = $flavour;
        $this->eventStore = $eventStore;
        $this->documentStore = $documentStore;
        $this->streamName = $streamName;
        $this->aggregateCollection = $aggregateCollection;
    }

    /**
     * @param FlavouredAggregateRoot $aggregateRoot
     * @return GenericEvent[]
     * @throws \Throwable
     */
    public function saveAggregateRoot(FlavouredAggregateRoot $aggregateRoot): array
    {
        $domainEvents = $aggregateRoot->popRecordedEvents();

        if($this->eventStore instanceof MultiModelStore) {
            $this->eventStore->connection()->beginTransaction();

            try {
                $this->eventStore->appendTo($this->streamName, ...$domainEvents);

                $aggregateState = $aggregateRoot->currentState();

                if (is_object($aggregateState) && $aggregateState instanceof DeletableState && $aggregateState->deleted()) {
                    $this->eventStore->deleteDoc(
                        $this->aggregateCollection,
                        (string) $aggregateRoot->aggregateId()
                    );
                } else {
                    $this->eventStore->upsertDoc(
                        $this->aggregateCollection,
                        $aggregateRoot->aggregateId(),
                        [
                            'state' => $this->flavour->convertAggregateStateToArray($aggregateRoot->aggregateType(), $aggregateRoot->currentState()),
                            'version' => $aggregateRoot->version()
                        ]
                    );
                }

                $this->eventStore->connection()->commit();
            } catch (\Throwable $error) {
                $this->eventStore->connection()->rollBack();
                throw $error;
            }
        } else {
            $this->eventStore->appendTo($this->streamName, ...$domainEvents);
        }

        return $domainEvents;
    }

    /**
     * Returns null if no stream events can be found for aggregate root otherwise the reconstituted aggregate root
     *
     * @param string $aggregateType
     * @param string $aggregateId
     * @param array $eventApplyMap
     * @param int|null $expectedVersion
     * @return null|FlavouredAggregateRoot
     */
    public function getAggregateRoot(string $aggregateType, string $aggregateId, array $eventApplyMap, int $expectedVersion = null)
    {
        if(
            $this->flavour->canBuildAggregateState($aggregateType)
            && $this->aggregateCollection
            && $documentStore = $this->getDocumentStore()) {

            $aggregateStateDoc = $documentStore->getDoc($this->aggregateCollection, $aggregateId);

            if($aggregateStateDoc) {
                $aggregateState = $this->flavour->buildAggregateState($aggregateType, $aggregateStateDoc['state'], $aggregateStateDoc['version']);

                $aggregate = FlavouredAggregateRoot::reconstituteFromAggregateState(
                    $aggregateId,
                    $aggregateType,
                    $eventApplyMap,
                    $this->flavour,
                    (int)$aggregateStateDoc['version'],
                    $aggregateState
                );

                if($expectedVersion && $expectedVersion > $aggregate->version()) {
                    $newerEvents = $this->eventStore->loadAggregateEvents(
                        $this->streamName,
                        $aggregateType,
                        $aggregateId,
                        $aggregate->version() + 1
                    );

                    $aggregate->replay($newerEvents);
                }

                return $aggregate;
            }
        }


        $streamEvents = $this->eventStore->loadAggregateEvents($this->streamName, $aggregateType, $aggregateId);

        if (! $streamEvents->valid()) {
            return null;
        }

        return FlavouredAggregateRoot::reconstituteFromHistory(
            $aggregateId,
            $aggregateType,
            $eventApplyMap,
            $this->flavour,
            $streamEvents
        );
    }

    private function getDocumentStore(): ?DocumentStore
    {
        if($this->eventStore instanceof MultiModelStore) {
            return $this->eventStore;
        }

        return $this->documentStore;
    }
}
