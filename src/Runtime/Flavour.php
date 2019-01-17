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
     * Invoked by Event Machine after CommandPreProcessor to load process in case it should exist
     *
     * @param string $pidKey
     * @param Message $command
     * @return string
     */
    public function getPidFromCommand(string $pidKey, Message $command): string;

    /**
     * @param Message $command
     * @param mixed $contextProvider A callable or object pulled from app container
     * @return mixed Context that gets passed as argument to corresponding process function
     */
    public function callContextProvider($contextProvider, Message $command);

    /**
     * A process factory usually starts the lifecycle of a process by producing the first event(s).
     *
     * @param string $processType
     * @param callable $processFunction
     * @param Message $command
     * @param null|mixed $context
     * @return \Generator Message[] yield events
     */
    public function callProcessFactory(string $processType, callable $processFunction, Message $command, $context = null): \Generator;

    /**
     * Subsequent process functions receive current state of the process as an argument.
     *
     * In case of the OopFlavour $processState is the process instance itself. Check implementation of the OopFlavour for details.
     *
     * @param string $processType
     * @param callable $processFunction
     * @param mixed $processState
     * @param Message $command
     * @param null|mixed $context
     * @return \Generator Message[] yield events
     */
    public function callProcessFunction(string $processType, callable $processFunction, $processState, Message $command, $context = null): \Generator;

    /**
     * First event apply function does not receive process state as an argument but should return the first version
     * of process state derived from the first recorded event.
     *
     * @param callable $applyFunction
     * @param Message $event
     * @return mixed New process state
     */
    public function callApplyFirstEvent(callable $applyFunction, Message $event);

    /**
     * All subsequent apply functions receive process state as an argument and should return a modified version of it.
     *
     * @param callable $applyFunction
     * @param mixed $processState
     * @param Message $event
     * @return mixed Modified aggregae state
     */
    public function callApplySubsequentEvent(callable $applyFunction, $processState, Message $event);

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
     * that it is the first event of a process history.
     * In this case the flag $firstProcessEvent is TRUE.
     *
     * @param Message $message
     * @param bool $processEvent
     * @return Message
     */
    public function convertMessageReceivedFromNetwork(Message $message, $processEvent = false): Message;

    /**
     * @param Projector|CustomEventProjector $projector The projector instance
     * @param string $projectionVersion
     * @param string $projectionName Used to register projection in Event Machine
     * @param Message $event
     */
    public function callProjector($projector, string $projectionVersion, string $projectionName, Message $event): void;

    /**
     * @param string $processType
     * @param mixed $processState
     * @return array
     */
    public function convertProcessStateToArray(string $processType, $processState): array;

    public function canBuildProcessState(string $processType): bool;

    /**
     * @param string $processType
     * @param array $state
     * @return mixed process state
     */
    public function buildProcessState(string $processType, array $state);

    public function callEventListener(callable $listener, Message $event): void;

    public function callQueryResolver($resolver, Message $query);
}
