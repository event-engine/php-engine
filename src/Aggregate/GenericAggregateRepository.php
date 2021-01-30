<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
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
     * @var string
     */
    private $multiStoreMode;

    /**
     * @var Flavour
     */
    private $flavour;

    public function __construct(
        Flavour $flavour,
        EventStore $eventStore,
        string $streamName,
        DocumentStore $documentStore = null,
        string $aggregateCollection = null,
        string $multiStoreMode = null
    ) {
        $this->flavour = $flavour;
        $this->eventStore = $eventStore;
        $this->documentStore = $documentStore;
        $this->streamName = $streamName;
        $this->aggregateCollection = $aggregateCollection;
        $this->multiStoreMode = $multiStoreMode ?: MultiModelStore::STORAGE_MODE_EVENTS_AND_STATE;
    }

    /**
     * @param FlavouredAggregateRoot $aggregateRoot
     * @return GenericEvent[]
     * @throws \Throwable
     */
    public function saveAggregateRoot(FlavouredAggregateRoot $aggregateRoot): array
    {
        $domainEvents = $aggregateRoot->popRecordedEvents();

        if(count($domainEvents) === 0) {
            return $domainEvents;
        }

        if($this->eventStore instanceof MultiModelStore && $this->aggregateCollection) {
            $this->eventStore->connection()->beginTransaction();

            try {
                if($this->multiStoreMode !== MultiModelStore::STORAGE_MODE_STATE) {
                    $this->eventStore->appendTo($this->streamName, ...$domainEvents);
                }

                if($this->multiStoreMode !== MultiModelStore::STORAGE_MODE_EVENTS) {

                    $aggregateState = $aggregateRoot->currentState();

                    if (is_object($aggregateState) && $aggregateState instanceof DeletableState && $aggregateState->deleted()) {
                        $this->eventStore->deleteDoc(
                            $this->aggregateCollection,
                            (string) $aggregateRoot->aggregateId()
                        );
                    } else {
                        $currentState = $aggregateRoot->currentState();
                        $doc = [
                            'state' => $this->flavour->convertAggregateStateToArray($aggregateRoot->aggregateType(), $currentState),
                            'version' => $aggregateRoot->version()
                        ];

                        if($this->flavour->canProvideAggregateMetadata($aggregateRoot->aggregateType())) {
                            $doc['metadata'] = $this->flavour->provideAggregateMetadata($aggregateRoot->aggregateType(), $aggregateRoot->version(), $currentState);
                        }

                        $this->eventStore->upsertDoc(
                            $this->aggregateCollection,
                            $aggregateRoot->aggregateId(),
                            $doc
                        );
                    }

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
    public function getAggregateRoot(
        string $aggregateType,
        string $aggregateId,
        array $eventApplyMap,
        int $expectedVersion = null
    ): ?FlavouredAggregateRoot {
        $documentStore = $this->getDocumentStore();

        if (
            $this->aggregateCollection
            && $documentStore
            && $this->flavour->canBuildAggregateState($aggregateType)
            && $this->multiStoreMode !== MultiModelStore::STORAGE_MODE_EVENTS
        ) {
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

                if($expectedVersion && $expectedVersion > $aggregate->version() && $this->multiStoreMode !== MultiModelStore::STORAGE_MODE_STATE) {
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

    /**
     * Returns null if no stream events can be found for aggregate root otherwise the reconstituted aggregate root
     *
     * @param string $aggregateType
     * @param string $aggregateId
     * @param array $eventApplyMap
     * @param int|null $maxVersion
     * @return null|FlavouredAggregateRoot
     */
    public function getAggregateRootUntil(
        string $aggregateType,
        string $aggregateId,
        array $eventApplyMap,
        int $maxVersion = null
    ): ?FlavouredAggregateRoot {
        $streamEvents = $this->eventStore->loadAggregateEvents($this->streamName, $aggregateType, $aggregateId, 1, $maxVersion);

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
