<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Runtime;

use EventEngine\Exception\RuntimeException;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageBag;
use EventEngine\Messaging\MessageFactory;
use EventEngine\Messaging\MessageFactoryAware;
use EventEngine\Process\Pid;
use EventEngine\Process\ProcessType;
use EventEngine\Runtime\Oop\ProcessAndEventBag;
use EventEngine\Runtime\Oop\Port;
use EventEngine\Util\MapIterator;

/**
 * Class OopFlavour
 *
 * Event Sourcing can be implemented using either a functional programming approach (pure process functions + immutable data types)
 * or an object-oriented approach with stateful processes. The latter is supported by the OopFlavour.
 *
 * Processes manage their state internally. Event Engine takes over the rest like history replays and event persistence.
 * You can focus on the business logic with a 100% decoupled domain model.
 *
 * Decoupling is achieved by implementing the Oop\Port tailored to your domain model.
 *
 * The OopFlavour uses a FunctionalFlavour internally. This is because the OopFlavour also requires type-safe messages.
 *
 *
 * @package EventEngine\Runtime
 */
final class OopFlavour implements Flavour, MessageFactoryAware
{
    /**
     * @var Port
     */
    private $port;

    /**
     * @var FunctionalFlavour
     */
    private $functionalFlavour;

    public function __construct(Port $port, FunctionalFlavour $flavour)
    {
        $this->port = $port;
        $this->functionalFlavour = $flavour;
    }

    /**
     * {@inheritdoc}
     */
    public function callProcessFactory(ProcessType $processType, callable $processFunction, Message $command, $context = null): \Generator
    {
        if (! $command instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        $process = $this->port->callProcessFactory($processType, $processFunction, $command->get(MessageBag::MESSAGE), $context);

        $events = $this->port->popRecordedEvents($process);

        yield from new MapIterator(new \ArrayIterator($events), function ($event) use ($command, $process, $processType) {
            if (null === $event) {
                return null;
            }

            return $this->functionalFlavour->decorateEvent($event)
                ->withMessage(new ProcessAndEventBag($process, $event))
                ->withAddedMetadata(GenericEvent::META_CAUSATION_ID, $command->uuid()->toString())
                ->withAddedMetadata(GenericEvent::META_CAUSATION_NAME, $command->messageName());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function callProcessFunction(ProcessType $processType, callable $processFunction, $processState, Message $command, $context = null): \Generator
    {
        if (! $command instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        $this->port->callProcessWithCommand($processState, $command->get(MessageBag::MESSAGE), $context);

        $events = $this->port->popRecordedEvents($processState);

        yield from new MapIterator(new \ArrayIterator($events), function ($event) use ($command) {
            if (null === $event) {
                return null;
            }

            return $this->functionalFlavour->decorateEvent($event)
                ->withAddedMetadata(GenericEvent::META_CAUSATION_ID, $command->uuid()->toString())
                ->withAddedMetadata(GenericEvent::META_CAUSATION_NAME, $command->messageName());
        });
    }

    /**
     * {@inheritdoc}
     */
    public function callApplyFirstEvent(callable $applyFunction, Message $event)
    {
        if (! $event instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        $processAndEventBag = $event->get(MessageBag::MESSAGE);

        if (! $processAndEventBag instanceof ProcessAndEventBag) {
            throw new RuntimeException('MessageBag passed to ' . __METHOD__ . ' should contain a ' . ProcessAndEventBag::class . ' message.');
        }

        $process = $processAndEventBag->process();
        $event = $processAndEventBag->event();

        $this->port->applyEvent($process, $event);

        return $process;
    }

    public function callApplySubsequentEvent(callable $applyFunction, $processState, Message $event)
    {
        if (! $event instanceof MessageBag) {
            throw new RuntimeException('Message passed to ' . __METHOD__ . ' should be of type ' . MessageBag::class);
        }

        $this->port->applyEvent($processState, $event->get(MessageBag::MESSAGE));

        return $processState;
    }

    /**
     * {@inheritdoc}
     */
    public function callCommandPreProcessor($preProcessor, Message $command)
    {
        return $this->functionalFlavour->callCommandPreProcessor($preProcessor, $command);
    }

    /**
     * {@inheritdoc}
     */
    public function getPidFromCommand(string $pidKey, Message $command): Pid
    {
        return $this->functionalFlavour->getPidFromCommand($pidKey, $command);
    }

    /**
     * {@inheritdoc}
     */
    public function callContextProvider($contextProvider, Message $command)
    {
        return $this->functionalFlavour->callContextProvider($contextProvider, $command);
    }

    /**
     * {@inheritdoc}
     */
    public function prepareNetworkTransmission(Message $message): Message
    {
        if ($message instanceof MessageBag) {
            $innerEvent = $message->getOrDefault(MessageBag::MESSAGE, new \stdClass());

            if ($innerEvent instanceof ProcessAndEventBag) {
                $message = $message->withMessage($innerEvent->event());
            }
        }

        return $this->functionalFlavour->prepareNetworkTransmission($message);
    }

    /**
     * {@inheritdoc}
     */
    public function convertMessageReceivedFromNetwork(Message $message, $processEvent = false): Message
    {
        $customMessageInBag = $this->functionalFlavour->convertMessageReceivedFromNetwork($message);

        if ($processEvent && $message->messageType() === Message::TYPE_EVENT) {
            $processType = $message->metadata()[GenericEvent::META_PROCESS_TYPE] ?? null;
            $processVersion = $message->metadata()[GenericEvent::META_PROCESS_VERSION] ?? 0;

            if($processVersion !== 1) {
                return $customMessageInBag;
            }

            if (null === $processType) {
                throw new RuntimeException('Event passed to ' . __METHOD__ . ' should have a metadata key: ' . GenericEvent::META_PROCESS_TYPE);
            }

            if (! $customMessageInBag instanceof MessageBag) {
                throw new RuntimeException('FunctionalFlavour is expected to return a ' . MessageBag::class);
            }

            $process = $this->port->reconstituteProcess(ProcessType::fromString((string)$processType), [$customMessageInBag->get(MessageBag::MESSAGE)]);

            $customMessageInBag = $customMessageInBag->withMessage(new ProcessAndEventBag($process, $customMessageInBag->get(MessageBag::MESSAGE)));
        }

        return $customMessageInBag;
    }

    /**
     * {@inheritdoc}
     */
    public function callProjector($projector, string $projectionVersion, string $projectionName, Message $event): void
    {
        $this->functionalFlavour->callProjector($projector, $projectionVersion, $projectionName, $event);
    }

    /**
     * {@inheritdoc}
     */
    public function convertProcessStateToArray(ProcessType $processType, $processState): array
    {
        return $this->port->serializeProcess($processState);
    }

    public function canBuildProcessState(ProcessType $processType): bool
    {
        return true;
    }

    public function buildProcessState(ProcessType $processType, array $state)
    {
        return $this->port->reconstituteProcessFromStateArray($processType, $state);
    }

    public function setMessageFactory(MessageFactory $messageFactory): void
    {
        $this->functionalFlavour->setMessageFactory($messageFactory);
    }

    public function callEventListener(callable $listener, Message $event): void
    {
        $this->functionalFlavour->callEventListener($listener, $event);
    }

    public function callQueryResolver($resolver, Message $query)
    {
        return $this->functionalFlavour->callQueryResolver($resolver, $query);
    }
}
