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
use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\Message;
use EventEngine\Projecting\CustomEventProjector;
use EventEngine\Projecting\Projector;
use EventEngine\Querying\Resolver;

/**
 * Create your own Flavour by implementing the Flavour interface.
 *
 * With a Flavour you can tell Event Machine how it should communicate with your domain model.
 * Check the three available Flavours shipped with Event Machine. If they don't meet your personal
 * Flavour, mix and match them or create your very own Flavour.
 *
 * Interface Flavour
 * @package EventEngine\Runtime
 */
interface Flavour
{
    /**
     * @param Message $command
     * @param mixed $preProcessor A callable or object pulled from app container
     * @return Message|CommandDispatchResult
     */
    public function callCommandPreProcessor($preProcessor, Message $command);

    /**
     * Invoked by Event Machine after CommandPreProcessor to load aggregate in case it should exist
     *
     * @param string $aggregateIdPayloadKey
     * @param Message $command
     * @return string
     */
    public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, Message $command): string;

    /**
     * @param Message $command
     * @param mixed $contextProvider A callable or object pulled from app container
     * @return mixed Context that gets passed as argument to corresponding aggregate function
     */
    public function callContextProvider($contextProvider, Message $command);

    /**
     * An aggregate factory usually starts the lifecycle of an aggregate by producing the first event(s).
     *
     * @param string $aggregateType
     * @param callable $aggregateFunction
     * @param Message $command
     * @param null|mixed $context
     * @return \Generator Message[] yield events
     */
    public function callAggregateFactory(string $aggregateType, callable $aggregateFunction, Message $command, $context = null): \Generator;

    /**
     * Subsequent aggregate functions receive current state of the aggregate as an argument.
     *
     * In case of the OopFlavour $aggregateState is the aggregate instance itself. Check implementation of the OopFlavour for details.
     *
     * @param string $aggregateType
     * @param callable $aggregateFunction
     * @param mixed $aggregateState
     * @param Message $command
     * @param null|mixed $context
     * @return \Generator Message[] yield events
     */
    public function callSubsequentAggregateFunction(string $aggregateType, callable $aggregateFunction, $aggregateState, Message $command, $context = null): \Generator;

    /**
     * First event apply function does not receive aggregate state as an argument but should return the first version
     * of aggregate state derived from the first recorded event.
     *
     * @param callable $applyFunction
     * @param Message $event
     * @return mixed New aggregate state
     */
    public function callApplyFirstEvent(callable $applyFunction, Message $event);

    /**
     * All subsequent apply functions receive aggregate state as an argument and should return a modified version of it.
     *
     * @param callable $applyFunction
     * @param mixed $aggregateState
     * @param Message $event
     * @return mixed Modified aggregae state
     */
    public function callApplySubsequentEvent(callable $applyFunction, $aggregateState, Message $event);

    /**
     * Use this hook to convert a custom message decorated by a MessageBag into an Event Machine message (serialize payload)
     *
     * @param Message $message
     * @return Message
     */
    public function prepareNetworkTransmission(Message $message): Message;

    /**
     * Use this hook to convert an Event Machine message into a custom message and decorate it with a MessageBag
     *
     * Always invoked after raw message data is deserialized into Event Machine Message:
     *
     * - EventMachine::dispatch() is called
     * - EventMachine::messageFactory()->createMessageFromArray() is called
     *
     * Create a type safe message from given Event Machine message and put it into a Prooph\EventMachine\Messaging\MessageBag
     * to pass it through the Event Machine layer.
     *
     * Use MessageBag::get(MessageBag::MESSAGE) in call-interceptions to access your type safe message.
     *
     * It might be important for a Flavour implementation to know that an event is loaded from event store and
     * that it is the first event of an aggregate history.
     * In this case the flag $firstAggregateEvent is TRUE.
     *
     * @param Message $message
     * @param bool $aggregateEvent
     * @return Message
     */
    public function convertMessageReceivedFromNetwork(Message $message, $aggregateEvent = false): Message;

    /**
     * @param Projector|CustomEventProjector $projector The projector instance
     * @param string $projectionVersion
     * @param string $projectionName Used to register projection in Event Machine
     * @param Message $event
     */
    public function callProjector($projector, string $projectionVersion, string $projectionName, Message $event): void;

    /**
     * @param string $aggregateType
     * @param mixed $aggregateState
     * @return array
     */
    public function convertAggregateStateToArray(string $aggregateType, $aggregateState): array;

    public function canProvideAggregateMetadata(string $aggregateType): bool;

    public function provideAggregateMetadata(string $aggregateType, int $version, $aggregateState): array;

    public function canBuildAggregateState(string $aggregateType): bool;

    /**
     * @param string $aggregateType
     * @param array $state
     * @return mixed aggregate state
     */
    public function buildAggregateState(string $aggregateType, array $state, int $version);

    /**
     * A listener can return a command that should be dispatched next
     *
     * The command can be of type Message or the tuple shortcut: [command_name, payload, optional_metadata]
     *
     * @param callable $listener
     * @param Message $event
     * @return null|array|Message
     */
    public function callEventListener(callable $listener, Message $event);

    public function callQueryResolver($resolver, Message $query);
}
