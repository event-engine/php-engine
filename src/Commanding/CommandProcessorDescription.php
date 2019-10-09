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
use EventEngine\Eventing\EventRecorderDescription;
use EventEngine\Exception\BadMethodCallException;
use EventEngine\Persistence\MultiModelStore;

final class CommandProcessorDescription
{
    /**
     * @var EventEngine
     */
    private $eventEngine;

    /**
     * @var string
     */
    private $commandName;

    /**
     * @var bool
     */
    private $createAggregate;

    /**
     * @var string
     */
    private $aggregateType;

    /**
     * @var string|null
     */
    private $aggregateIdentifier;

    /**
     * @var string|null
     */
    private $aggregateCollection;

    /**
     * @var string|null
     */
    private $aggregateStream;

    /**
     * @var string|null
     */
    private $multiStoreMode;

    /**
     * @var callable
     */
    private $aggregateFunction;

    private $eventRecorderMap = [];

    /**
     * @var string[]|null
     */
    private $contextProviders;

    /**
     * @var array
     */
    private $services = [];

    public function __construct(string $commandName, EventEngine $eventEngine)
    {
        $this->commandName = $commandName;
        $this->eventEngine = $eventEngine;
    }

    public function withNew(string $aggregateType): self
    {
        $this->assertWithAggregateWasNotCalled();

        $this->createAggregate = true;
        $this->aggregateType = $aggregateType;

        return $this;
    }

    public function withExisting(string  $aggregateType): self
    {
        $this->assertWithAggregateWasNotCalled();

        $this->createAggregate = false;
        $this->aggregateType = $aggregateType;

        return $this;
    }

    public function identifiedBy(string $aggregateIdentifier): self
    {
        if (null === $this->aggregateType) {
            throw new BadMethodCallException('You should not call identifiedBy before calling one of the with* Aggregate methods.');
        }

        $this->aggregateIdentifier = $aggregateIdentifier;

        return $this;
    }

    public function storeEventsIn(string $streamName): self
    {
        if (null === $this->aggregateType) {
            throw new BadMethodCallException('You should not call '.__METHOD__.' before calling the withNew Aggregate method.');
        }

        if(!$this->createAggregate) {
            throw new BadMethodCallException(__METHOD__ . ' should only be called when registering a new aggregate: withNew()');
        }

        $this->aggregateStream = $streamName;

        return $this;
    }

    public function disableEventStorage(): self
    {
        if (null === $this->aggregateType) {
            throw new BadMethodCallException('You should not call '.__METHOD__.' before calling the withNew Aggregate method.');
        }

        if(!$this->createAggregate) {
            throw new BadMethodCallException(__METHOD__ . ' should only be called when registering a new aggregate: withNew()');
        }

        if(null !== $this->multiStoreMode) {
            throw new BadMethodCallException('You can not set multi store mode twice. Either '.__METHOD__.' was already called for the aggregate description or state storage is disabled.');
        }

        $this->multiStoreMode = MultiModelStore::STORAGE_MODE_STATE;

        return $this;
    }

    public function storeStateIn(string $aggregateCollection): self
    {
        if (null === $this->aggregateType) {
            throw new BadMethodCallException('You should not call '.__METHOD__.' before calling the withNew Aggregate method.');
        }

        if(!$this->createAggregate) {
            throw new BadMethodCallException(__METHOD__ . ' should only be called when registering a new aggregate: withNew()');
        }

        $this->aggregateCollection = $aggregateCollection;

        return $this;
    }

    public function disableStateStorage(): self
    {
        if (null === $this->aggregateType) {
            throw new BadMethodCallException('You should not call '.__METHOD__.' before calling the withNew Aggregate method.');
        }

        if(!$this->createAggregate) {
            throw new BadMethodCallException(__METHOD__ . ' should only be called when registering a new aggregate: withNew()');
        }

        if(null !== $this->multiStoreMode) {
            throw new BadMethodCallException('You can not set multi store mode twice. Either '.__METHOD__.' was already called for the aggregate description or event storage is disabled.');
        }

        $this->multiStoreMode = MultiModelStore::STORAGE_MODE_EVENTS;

        return $this;
    }

    public function provideContext(string $contextProvider): self
    {
        if(null === $this->contextProviders) {
            $this->contextProviders = [];
        }

        $this->contextProviders[] = $contextProvider;

        return $this;
    }

    public function provideService(string $serviceId): self
    {
        $this->services[] = $serviceId;

        return $this;
    }

    /**
     * Registers given pre processor for this command.
     *
     * @see \EventEngine\EventEngine::preProcess
     * @param $preProcessor
     * @return $this
     */
    public function preProcess($preProcessor): self
    {
        $this->eventEngine->preProcess($this->commandName, $preProcessor);

        return $this;
    }

    public function handle(callable $aggregateFunction): self
    {
        $this->assertWithAggregateWasCalled(__METHOD__);

        $this->aggregateFunction = $aggregateFunction;

        return $this;
    }

    public function recordThat(string $eventName): EventRecorderDescription
    {
        if (\array_key_exists($eventName, $this->eventRecorderMap)) {
            throw new BadMethodCallException('Method recordThat was already called for event: ' . $eventName);
        }

        if (! $this->eventEngine->isKnownEvent($eventName)) {
            throw new BadMethodCallException("Event $eventName is unknown. You should register it first.");
        }

        $this->assertWithAggregateWasCalled(__METHOD__);
        $this->assertHandleWasCalled(__METHOD__);

        $this->eventRecorderMap[$eventName] = new EventRecorderDescription($eventName, $this);

        return $this->eventRecorderMap[$eventName];
    }

    public function orRecordThat(string $eventName): EventRecorderDescription
    {
        return $this->recordThat($eventName);
    }

    public function andRecordThat(string $eventName): EventRecorderDescription
    {
        return $this->recordThat($eventName);
    }

    public function __invoke(): array
    {
        $this->assertWithAggregateWasCalled('EventMachine::bootstrap');
        $this->assertHandleWasCalled('EventMachine::bootstrap');

        $eventRecorderMap = [];

        foreach ($this->eventRecorderMap as $eventName => $desc) {
            $eventRecorderMap[$eventName] = $desc()['apply'];
        }

        return [
            'commandName' => $this->commandName,
            'createAggregate' => $this->createAggregate,
            'aggregateType' => $this->aggregateType,
            'aggregateIdentifier' => $this->aggregateIdentifier,
            'aggregateFunction' => $this->aggregateFunction,
            'aggregateCollection' => $this->aggregateCollection,
            'eventRecorderMap' => $eventRecorderMap,
            'streamName' => $this->aggregateStream,
            'multiStoreMode' => $this->multiStoreMode,
            'contextProviders' => $this->contextProviders,
            'services' => $this->services,
        ];
    }

    private function assertWithAggregateWasCalled(string $method): void
    {
        if (null === $this->createAggregate) {
            throw new BadMethodCallException("Method with(New|Existing) Aggregate was not called. You need to call it before calling $method");
        }
    }

    private function assertHandleWasCalled(string $method): void
    {
        if (null === $this->aggregateFunction) {
            throw new BadMethodCallException("Method handle was not called. You need to call it before calling $method");
        }
    }

    private function assertWithAggregateWasNotCalled(): void
    {
        if (null !== $this->createAggregate) {
            throw new BadMethodCallException('Method with(New|Existing) Aggregate was called twice for the same command.');
        }
    }
}
