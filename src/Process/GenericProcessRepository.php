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

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\EventStore\EventStore;
use EventEngine\EventStore\Stream\Name;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Persistence\DeletableState;
use EventEngine\Persistence\MultiModelStore;
use EventEngine\Runtime\Flavour;

final class GenericProcessRepository
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
     * @var Name
     */
    private $streamName;

    /**
     * @var string|null
     */
    private $processStateCollection;

    /**
     * @var Flavour
     */
    private $flavour;

    public function __construct(
        Flavour $flavour,
        EventStore $eventStore,
        Name $streamName,
        DocumentStore $documentStore = null,
        string $processStateCollection = null
    ) {
        $this->flavour = $flavour;
        $this->eventStore = $eventStore;
        $this->documentStore = $documentStore;
        $this->streamName = $streamName;
        $this->processStateCollection = $processStateCollection;
    }

    /**
     * @param FlavouredProcess $process
     * @return GenericEvent[]
     * @throws \Throwable
     */
    public function saveProcess(FlavouredProcess $process): array
    {
        $domainEvents = $process->popRecordedEvents();

        if($this->eventStore instanceof MultiModelStore) {
            $this->eventStore->connection()->beginTransaction();

            try {
                $this->eventStore->appendTo($this->streamName, ...$domainEvents);

                $processState = $process->currentState();

                if (is_object($processState) && $processState instanceof DeletableState && $processState->deleted()) {
                    $this->eventStore->deleteDoc(
                        $this->processStateCollection,
                        (string) $process->pid()
                    );
                } else {
                    $this->eventStore->upsertDoc(
                        $this->processStateCollection,
                        $process->pid()->toString(),
                        [
                            'state' => $this->flavour->convertProcessStateToArray($process->processType(), $process->currentState()),
                            'version' => $process->version()
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
     * Returns null if no stream events can be found for process otherwise the reconstituted process
     *
     * @param ProcessType $processType
     * @param Pid $pid
     * @param array $eventApplyMap
     * @param int|null $expectedVersion
     * @return null|FlavouredProcess
     */
    public function getProcess(ProcessType $processType, Pid $pid, array $eventApplyMap, int $expectedVersion = null)
    {
        if(
            $this->flavour->canBuildProcessState($processType)
            && $this->processStateCollection
            && $documentStore = $this->getDocumentStore()) {

            $processStateDoc = $documentStore->getDoc($this->processStateCollection, $pid);

            if($processStateDoc) {
                $processState = $this->flavour->buildProcessState($processType, $processStateDoc);

                $process = FlavouredProcess::reconstituteFromProcessState(
                    $pid,
                    $processType,
                    $eventApplyMap,
                    $this->flavour,
                    (int)$processStateDoc['version'],
                    $processState
                );

                if($expectedVersion && $expectedVersion > $process->version()) {
                    $newerEvents = $this->eventStore->loadProcessEvents(
                        $this->streamName,
                        $processType,
                        $pid,
                        $process->version() + 1
                    );

                    $process->replay($newerEvents);
                }

                return $process;
            }
        }


        $streamEvents = $this->eventStore->loadProcessEvents($this->streamName, $processType, $pid);

        if (! $streamEvents->valid()) {
            return null;
        }

        return FlavouredProcess::reconstituteFromHistory(
            $pid,
            $processType,
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
