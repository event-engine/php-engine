<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Commanding;

use EventEngine\EventEngine;
use EventEngine\Process\ContextProvider;
use EventEngine\Process\Exception\ProcessNotFound;
use EventEngine\Process\FlavouredProcess;
use EventEngine\Process\GenericProcessRepository;
use EventEngine\DocumentStore\DocumentStore;
use EventEngine\EventStore\EventStore;
use EventEngine\Exception\InvalidArgumentException;
use EventEngine\Exception\RuntimeException;
use EventEngine\Logger\LogEngine;
use EventEngine\Messaging\GenericCommand;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\Message;
use EventEngine\Runtime\Flavour;
use Psr\Log\LoggerInterface;

final class CommandProcessor
{
    /**
     * @var string
     */
    private $commandName;

    /**
     * @var string
     */
    private $processType;

    /**
     * @var string
     */
    private $pidKey;

    /**
     * @var bool
     */
    private $createProcess;

    /**
     * @var EventStore
     */
    private $eventStore;

    /**
     * @var array
     */
    private $eventApplyMap;

    /**
     * @var string
     */
    private $streamName;

    /**
     * @var callable
     */
    private $processFunction;

    /**
     * @var string|null
     */
    private $processStateCollection;

    /**
     * @var Flavour
     */
    private $flavour;

    /**
     * @var DocumentStore|null
     */
    private $documentStore;

    /**
     * @var EventEngine
     */
    private $eventEngine;

    /**
     * @var ContextProvider|null
     */
    private $contextProvider;

    /**
     * @var LoggerInterface
     */
    private $log;

    public static function fromDescriptionArraysAndDependencies(
        array &$processorDesc,
        array &$processDescriptions,
        Flavour $flavour,
        EventStore $eventStore,
        LogEngine $logEngine,
        EventEngine $eventEngine,
        DocumentStore $documentStore = null,
        ContextProvider $contextProvider = null
    ): self {
        $processDesc = $processDescriptions[$processorDesc['processType'] ?? ''] ?? [];

        if (! isset($processDesc['eventApplyMap'])) {
            throw new RuntimeException('Missing eventApplyMap for process type: ' . $processorDesc['processType'] ?? '');
        }

        if (! \array_key_exists('commandName', $processorDesc)) {
            throw new InvalidArgumentException('Missing key commandName in commandProcessorDescription');
        }

        if (! \array_key_exists('createProcess', $processorDesc)) {
            throw new InvalidArgumentException('Missing key createProcess in commandProcessorDescription');
        }

        if (! \array_key_exists('processType', $processorDesc)) {
            throw new InvalidArgumentException('Missing key processType in commandProcessorDescription');
        }

        if (! \array_key_exists('pidKey', $processorDesc)) {
            throw new InvalidArgumentException('Missing key pidKey in commandProcessorDescription');
        }

        if (! \array_key_exists('processFunction', $processorDesc)) {
            throw new InvalidArgumentException('Missing key processFunction in commandProcessorDescription');
        }

        if (! \array_key_exists('streamName', $processorDesc)) {
            throw new InvalidArgumentException('Missing key streamName in commandProcessorDescription');
        }

        return new self(
            $processorDesc['commandName'],
            $processorDesc['processType'],
            $processorDesc['createProcess'],
            $processorDesc['pidKey'],
            $processorDesc['processFunction'],
            $processDesc['eventApplyMap'],
            $processorDesc['streamName'],
            $flavour,
            $eventStore,
            $logEngine,
            $eventEngine,
            $contextProvider,
            $documentStore,
            $processDesc['processStateCollection'] ?? null
        );
    }

    private function __construct(
        string $commandName,
        string $processType,
        bool $createProcess,
        string $pidKey,
        callable $processFunction,
        array $eventApplyMap,
        string $streamName,
        Flavour $flavour,
        EventStore $eventStore,
        LogEngine $log,
        EventEngine $eventEngine,
        ContextProvider $contextProvider = null,
        DocumentStore $documentStore = null,
        string $processStateCollection = null
    ) {
        $this->commandName = $commandName;
        $this->processType = $processType;
        $this->pidKey = $pidKey;
        $this->createProcess = $createProcess;
        $this->processFunction = $processFunction;
        $this->eventApplyMap = $eventApplyMap;
        $this->streamName = $streamName;
        $this->flavour = $flavour;
        $this->eventStore = $eventStore;
        $this->log = $log;
        $this->eventEngine = $eventEngine;
        $this->documentStore = $documentStore;
        $this->contextProvider = $contextProvider;
        $this->processStateCollection = $processStateCollection;
    }

    /**
     * @param Message $command
     * @return GenericEvent[]
     * @throws \Throwable
     */
    public function __invoke(Message $command): array
    {
        if ($command->messageName() !== $this->commandName) {
            throw  new RuntimeException('Wrong routing detected. Command processor is responsible for '
                . $this->commandName . ' but command '
                . $command->messageName() . ' received.');
        }

        $pid = $this->flavour->getPidFromCommand($this->pidKey, $command);
        $procRepository = $this->getProcessRepository();

        $process = null;
        $processState = null;
        $context = null;
        $expectedVersion = $command->metadata()[GenericCommand::META_EXPECTED_PROCESS_VERSION] ?? null;

        if ($this->createProcess) {
            $process = new FlavouredProcess($pid, $this->processType, $this->eventApplyMap, $this->flavour);
        } else {
            /** @var FlavouredProcess $process */
            $process = $procRepository->getProcess($this->processType, $pid, $this->eventApplyMap, $expectedVersion);

            if (! $process) {
                throw ProcessNotFound::with($this->processType, $pid);
            }

            $processState = $process->currentState();
        }

        if ($this->contextProvider) {
            $context = $this->flavour->callContextProvider($this->contextProvider, $command);
            $this->log->contextProviderCalled($this->contextProvider, $command, $context);
        }

        $arFunc = $this->processFunction;

        if ($this->createProcess) {
            $events = $this->flavour->callProcessFactory($this->processType, $arFunc, $command, $context);
        } else {
            $events = $this->flavour->callProcessFunction($this->processType, $arFunc, $processState, $command, $context);
        }

        foreach ($events as $event) {
            if (! $event) {
                continue;
            }
            $process->recordThat($event);
        }

        $events = $procRepository->saveProcess($process);

        if($this->createProcess) {
            $this->log->newProcessCreated($this->processType, $pid, ...$events);
        } else {
            $this->log->existingProcessChanged($this->processType, $pid, $processState, ...$events);
        }

        $this->eventEngine->cacheProcessState($this->processType, $pid, $process->version(), $process->currentState());

        return $events;
    }

    private function getProcessRepository(): GenericProcessRepository
    {
        return new GenericProcessRepository(
            $this->flavour,
            $this->eventStore,
            $this->streamName,
            $this->documentStore,
            $this->processStateCollection
        );
    }
}
