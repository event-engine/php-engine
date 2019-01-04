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

use EventEngine\Aggregate\ContextProvider;
use EventEngine\Aggregate\Exception\AggregateNotFound;
use EventEngine\Aggregate\FlavouredAggregateRoot;
use EventEngine\Aggregate\GenericAggregateRepository;
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
    private $aggregateType;

    /**
     * @var string
     */
    private $aggregateIdentifier;

    /**
     * @var bool
     */
    private $createAggregate;

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
    private $aggregateFunction;

    /**
     * @var string|null
     */
    private $aggregateCollection;

    /**
     * @var Flavour
     */
    private $flavour;

    /**
     * @var DocumentStore|null
     */
    private $documentStore;

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
        array &$aggregateDescriptions,
        Flavour $flavour,
        EventStore $eventStore,
        LogEngine $logEngine,
        DocumentStore $documentStore = null,
        ContextProvider $contextProvider = null
    ): self {
        $aggregateDesc = $aggregateDescriptions[$processorDesc['aggregateType'] ?? ''] ?? [];

        if (! isset($aggregateDesc['eventApplyMap'])) {
            throw new RuntimeException('Missing eventApplyMap for aggregate type: ' . $processorDesc['aggregateType'] ?? '');
        }

        if (! \array_key_exists('commandName', $processorDesc)) {
            throw new InvalidArgumentException('Missing key commandName in commandProcessorDescription');
        }

        if (! \array_key_exists('createAggregate', $processorDesc)) {
            throw new InvalidArgumentException('Missing key createAggregate in commandProcessorDescription');
        }

        if (! \array_key_exists('aggregateType', $processorDesc)) {
            throw new InvalidArgumentException('Missing key aggregateType in commandProcessorDescription');
        }

        if (! \array_key_exists('aggregateIdentifier', $processorDesc)) {
            throw new InvalidArgumentException('Missing key aggregateIdentifier in commandProcessorDescription');
        }

        if (! \array_key_exists('aggregateFunction', $processorDesc)) {
            throw new InvalidArgumentException('Missing key aggregateFunction in commandProcessorDescription');
        }

        if (! \array_key_exists('streamName', $processorDesc)) {
            throw new InvalidArgumentException('Missing key streamName in commandProcessorDescription');
        }

        return new self(
            $processorDesc['commandName'],
            $processorDesc['aggregateType'],
            $processorDesc['createAggregate'],
            $processorDesc['aggregateIdentifier'],
            $processorDesc['aggregateFunction'],
            $aggregateDesc['eventApplyMap'],
            $processorDesc['streamName'],
            $flavour,
            $eventStore,
            $logEngine,
            $contextProvider,
            $documentStore,
            $aggregateDesc['aggregateCollection'] ?? null
        );
    }

    private function __construct(
        string $commandName,
        string $aggregateType,
        bool $createAggregate,
        string $aggregateIdentifier,
        callable $aggregateFunction,
        array $eventApplyMap,
        string $streamName,
        Flavour $flavour,
        EventStore $eventStore,
        LogEngine $log,
        ContextProvider $contextProvider = null,
        DocumentStore $documentStore = null,
        string $aggregateCollection = null
    ) {
        $this->commandName = $commandName;
        $this->aggregateType = $aggregateType;
        $this->aggregateIdentifier = $aggregateIdentifier;
        $this->createAggregate = $createAggregate;
        $this->aggregateFunction = $aggregateFunction;
        $this->eventApplyMap = $eventApplyMap;
        $this->streamName = $streamName;
        $this->flavour = $flavour;
        $this->eventStore = $eventStore;
        $this->log = $log;
        $this->documentStore = $documentStore;
        $this->contextProvider = $contextProvider;
        $this->aggregateCollection = $aggregateCollection;
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

        $arId = $this->flavour->getAggregateIdFromCommand($this->aggregateIdentifier, $command);
        $arRepository = $this->getAggregateRepository();

        $aggregate = null;
        $aggregateState = null;
        $context = null;
        $expectedVersion = $command->metadata()[GenericCommand::META_EXPECTED_AGGREGATE_VERSION] ?? null;

        if ($this->createAggregate) {
            $aggregate = new FlavouredAggregateRoot($arId, $this->aggregateType, $this->eventApplyMap, $this->flavour);
        } else {
            /** @var FlavouredAggregateRoot $aggregate */
            $aggregate = $arRepository->getAggregateRoot($this->aggregateType, $arId, $this->eventApplyMap, $expectedVersion);

            if (! $aggregate) {
                throw AggregateNotFound::with($this->aggregateType, $arId);
            }

            $aggregateState = $aggregate->currentState();
        }

        if ($this->contextProvider) {
            $context = $this->flavour->callContextProvider($this->contextProvider, $command);
            $this->log->contextProviderCalled($this->contextProvider, $command, $context);
        }

        $arFunc = $this->aggregateFunction;

        if ($this->createAggregate) {
            $events = $this->flavour->callAggregateFactory($this->aggregateType, $arFunc, $command, $context);
        } else {
            $events = $this->flavour->callSubsequentAggregateFunction($this->aggregateType, $arFunc, $aggregateState, $command, $context);
        }

        foreach ($events as $event) {
            if (! $event) {
                continue;
            }
            $aggregate->recordThat($event);
        }

        $events = $arRepository->saveAggregateRoot($aggregate);

        if($this->createAggregate) {
            $this->log->newAggregateCreated($this->aggregateType, $arId, ...$events);
        } else {
            $this->log->existingAggregateChanged($this->aggregateType, $arId, $aggregateState, ...$events);
        }

        return $events;
    }

    private function getAggregateRepository(): GenericAggregateRepository
    {
        return new GenericAggregateRepository(
            $this->flavour,
            $this->eventStore,
            $this->streamName,
            $this->documentStore,
            $this->aggregateCollection
        );
    }
}
