<?php
/**
 * This file is part of the event-engine/php-engine.
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
    private $createProcess;

    /**
     * @var string
     */
    private $processType;

    /**
     * @var string
     */
    private $pidKey = 'pid';

    /**
     * @var string|null
     */
    private $processStateCollection;

    /**
     * @var callable
     */
    private $processFunction;

    private $eventRecorderMap = [];

    /**
     * @var string|null
     */
    private $contextProvider;

    public function __construct(string $commandName, EventEngine $eventEngine)
    {
        $this->commandName = $commandName;
        $this->eventEngine = $eventEngine;
    }

    public function withNew(string $processType): self
    {
        $this->assertWithProcessWasNotCalled();

        $this->createProcess = true;
        $this->processType = $processType;

        return $this;
    }

    public function withExisting(string  $processType): self
    {
        $this->assertWithProcessWasNotCalled();

        $this->createProcess = false;
        $this->processType = $processType;

        return $this;
    }

    public function identifiedBy(string $pidKey): self
    {
        if (null === $this->processType) {
            throw new BadMethodCallException('You should not call identifiedBy before calling one of the with* Process methods.');
        }

        $this->pidKey = $pidKey;

        return $this;
    }

    public function storeStateIn(string $processStateCollection): self
    {
        if (null === $this->processType) {
            throw new BadMethodCallException('You should not call storeStateIn before calling one of the with* Process methods.');
        }

        $this->processStateCollection = $processStateCollection;

        return $this;
    }

    public function provideContext(string $contextProvider): self
    {
        $this->contextProvider = $contextProvider;

        return $this;
    }

    public function handle(callable $processFunction): self
    {
        $this->assertWithProcessWasCalled(__METHOD__);

        $this->processFunction = $processFunction;

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

        $this->assertWithProcessWasCalled(__METHOD__);
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
        $this->assertWithProcessWasCalled('EventMachine::bootstrap');
        $this->assertHandleWasCalled('EventMachine::bootstrap');

        $eventRecorderMap = [];

        foreach ($this->eventRecorderMap as $eventName => $desc) {
            $eventRecorderMap[$eventName] = $desc()['apply'];
        }

        return [
            'commandName' => $this->commandName,
            'createProcess' => $this->createProcess,
            'processType' => $this->processType,
            'pidKey' => $this->pidKey,
            'processFunction' => $this->processFunction,
            'processStateCollection' => $this->processStateCollection,
            'eventRecorderMap' => $eventRecorderMap,
            'streamName' => $this->eventEngine->writeModelStreamName()->toString(),
            'contextProvider' => $this->contextProvider,
        ];
    }

    private function assertWithProcessWasCalled(string $method): void
    {
        if (null === $this->createProcess) {
            throw new BadMethodCallException("Method with(New|Existing) Process was not called. You need to call it before calling $method");
        }
    }

    private function assertHandleWasCalled(string $method): void
    {
        if (null === $this->processFunction) {
            throw new BadMethodCallException("Method handle was not called. You need to call it before calling $method");
        }
    }

    private function assertWithProcessWasNotCalled(): void
    {
        if (null !== $this->createProcess) {
            throw new BadMethodCallException('Method with(New|Existing) Process was called twice for the same command.');
        }
    }
}
