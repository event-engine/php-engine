<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Projecting;

use EventEngine\Process\Exception\ProcessNotFound;
use EventEngine\DocumentStore\DocumentStore;
use EventEngine\DocumentStore\Index;
use EventEngine\Exception\RuntimeException;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\Message;
use EventEngine\Persistence\ProcessStateStore;
use EventEngine\Persistence\DeletableState;
use EventEngine\Runtime\Flavour;
use EventEngine\Runtime\PrototypingFlavour;

final class ProcessStateProjector implements Projector, FlavourAware, DocumentStoreIndexAware
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @var ProcessStateStore
     */
    private $stateStore;

    /**
     * @var Index[]
     */
    private $indices = [];

    /**
     * Set to true, if document should only be the process state.
     *
     * By default document has the structure ['state' => $processState, 'version' => $processVersion]
     * which can be used as a snapshot. However, it adds an extra level of structure that makes it slightly harder to query.
     *
     * @var bool
     */
    private $storeStateOnly;

    /**
     * @var Flavour
     */
    private $flavour;

    public static function processStateCollectionName(string $projectionVersion, string $processType): string
    {
        return self::generateCollectionName($projectionVersion, self::generateProjectionName($processType));
    }

    public static function generateProjectionName(string $processType): string
    {
        return $processType . '.Projection';
    }

    public static function generateCollectionName(string $projectionVersion, string $projectionName): string
    {
        return \str_replace('.', '_', $projectionName.'_'.$projectionVersion);
    }

    public function __construct(DocumentStore $documentStore, ProcessStateStore $stateStore, $storeStateOnly = false)
    {
        $this->documentStore = $documentStore;
        $this->stateStore = $stateStore;
        $this->storeStateOnly = $storeStateOnly;
    }

    public function setFlavour(Flavour $flavour): void
    {
        $this->flavour = $flavour;
    }

    public function setDocumentStoreIndices(Index ...$indices): void
    {
        $this->indices = $indices;
    }

    private function flavour(): Flavour
    {
        if (null === $this->flavour) {
            $this->flavour = new PrototypingFlavour();
        }

        return $this->flavour;
    }

    /**
     * @param string $projectionVersion
     * @param string $projectionName
     * @param Message $event
     * @throws \Throwable
     */
    public function handle(string $projectionVersion, string $projectionName, Message $event): void
    {
        if (! $event instanceof Message) {
            throw new RuntimeException(__METHOD__ . ' can only handle events of type: ' . Message::class);
        }

        $pid = $event->metadata()[GenericEvent::META_PROCESS_ID] ?? null;

        if (! $pid) {
            return;
        }

        $processType = $event->metadata()[GenericEvent::META_PROCESS_TYPE] ?? null;

        if (! $processType) {
            return;
        }

        $processVersion = $event->metadata()[GenericEvent::META_PROCESS_VERSION] ?? 0;

        $this->assertProjectionNameMatchesWithProcessType($projectionName, (string) $processType);

        try {
            $processState = $this->stateStore->loadProcessState((string) $processType, (string) $pid);
        } catch (ProcessNotFound $e) {
            return;
        }

        if (is_object($processState) && $processState instanceof DeletableState && $processState->deleted()) {
            $this->documentStore->deleteDoc(
                $this->generateCollectionName($projectionVersion, $projectionName),
                (string) $pid
            );

            return;
        }

        $document = $this->flavour()->convertProcessStateToArray((string)$processType, $processState);

        if(!$this->storeStateOnly) {
            $document = [
                'state' => $document,
                'version' => $processVersion
            ];
        }

        $this->documentStore->upsertDoc(
            $this->generateCollectionName($projectionVersion, $projectionName),
            (string) $pid,
            $document
        );
    }

    public function prepareForRun(string $projectionVersion, string $projectionName): void
    {
        if (! $this->documentStore->hasCollection($this->generateCollectionName($projectionVersion, $projectionName))) {
            $this->documentStore->addCollection($this->generateCollectionName($projectionVersion, $projectionName), ...$this->indices);
        }
    }

    public function deleteReadModel(string $projectionVersion, string $projectionName): void
    {
        if ($this->documentStore->hasCollection(self::generateCollectionName($projectionVersion, $projectionName))) {
            $this->documentStore->dropCollection(self::generateCollectionName($projectionVersion, $projectionName));
        }
    }

    private function assertProjectionNameMatchesWithProcessType(string $projectionName, string $processType): void
    {
        if ($projectionName !== self::generateProjectionName($processType)) {
            throw new \RuntimeException(\sprintf(
                'Wrong projection name configured for %s. Should be %s but got %s',
                __CLASS__,
                self::generateProjectionName($processType),
                $projectionName
            ));
        }
    }
}
