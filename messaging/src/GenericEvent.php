<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Messaging;

final class GenericEvent extends GenericSchemaMessage
{
    public const META_PROCESS_ID = '_process_id';
    public const META_PROCESS_TYPE = '_process_type';
    public const META_PROCESS_VERSION = '_process_version';
    public const META_CAUSATION_ID = '_causation_id';
    public const META_CAUSATION_NAME = '_causation_name';
    public const META_CORRELATION_ID = '_correlation_id';

    public static function fromMessage(Message $message): self
    {
        return self::fromArray([
            'uuid' => $message->uuid()->toString(),
            'message_name' => $message->messageName(),
            'payload' => $message->payload(),
            'metadata' => $message->metadata(),
            'created_at' => $message->createdAt()
        ]);
    }

    /**
     * Should be one of Message::TYPE_COMMAND, Message::TYPE_EVENT or Message::TYPE_QUERY
     */
    public function messageType(): string
    {
        return self::TYPE_EVENT;
    }

    public function version(): int
    {
        return $this->metadata[self::META_PROCESS_VERSION] ?? 0;
    }

    public function processId(): string
    {
        return $this->metadata[self::META_PROCESS_ID] ?? '';
    }

    public function processType(): string
    {
        return $this->metadata[self::META_PROCESS_TYPE] ?? '';
    }

    public function causationId(): string
    {
        return $this->metadata[self::META_CAUSATION_ID] ?? '';
    }

    public function causationName(): string
    {
        return $this->metadata[self::META_CAUSATION_NAME] ?? '';
    }
}
