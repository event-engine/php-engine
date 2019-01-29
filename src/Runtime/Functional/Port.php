<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Runtime\Functional;

use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageBag;

interface Port
{
    /**
     * @param Message $message
     * @return mixed The custom message
     */
    public function deserialize(Message $message);

    /**
     * @param mixed $customMessage
     * @return array
     */
    public function serializePayload($customMessage): array;

    /**
     * @param mixed $customCommand
     * @return MessageBag
     */
    public function decorateCommand($customCommand): MessageBag;

    /**
     * @param mixed $customEvent
     * @return MessageBag
     */
    public function decorateEvent($customEvent): MessageBag;

    /**
     * @param string $aggregateIdPayloadKey
     * @param mixed $command
     * @return string
     */
    public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, $command): string;

    /**
     * @param mixed $customCommand
     * @param mixed $preProcessor Custom preprocessor
     * @return mixed|CommandDispatchResult Custom message or CommandDispatchResult
     */
    public function callCommandPreProcessor($customCommand, $preProcessor);

    /**
     * @param mixed $customCommand
     * @param mixed $contextProvider
     * @return mixed
     */
    public function callContextProvider($customCommand, $contextProvider);

    public function callResolver($customQuery, $resolver);
}
