<?php
/**
 * This file is part of even-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Messaging;

interface MessageFactory
{
    /**
     * Message data MUST contain at least a "payload" key
     * but may also contain "uuid", "message_name", "metadata", and "created_at".
     *
     * If one of the optional keys is not part of the message data the factory should use a default instead:
     * For example:
     * uuid = Uuid::uuid4()
     * message_name = $messageName //First parameter passed to the method
     * metadata = []
     * created_at = new \DateTimeImmutable('now', new \DateTimeZone('UTC')) //We treat all dates as UTC
     */
    public function createMessageFromArray(string $messageName, array $messageData): Message;

    public function setPayloadFor(Message $message, array $payload): Message;
}
