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

use EventEngine\Messaging\Exception\RuntimeException;
use EventEngine\Schema\PayloadSchema;
use EventEngine\Schema\Schema;
use EventEngine\Schema\TypeSchema;
use EventEngine\Schema\TypeSchemaMap;
use Ramsey\Uuid\UuidInterface;

interface Message
{
    public const TYPE_COMMAND = 'command';
    public const TYPE_EVENT = 'event';
    public const TYPE_QUERY = 'query';

    /**
     * Get $key from message payload
     *
     * @param string $key
     * @throws RuntimeException if key does not exist in payload
     * @return mixed
     */
    public function get(string $key);

    /**
     * Get $key from message payload or default in case key does not exist
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getOrDefault(string $key, $default);

    /**
     * Get $key from message metadata
     *
     * @param string $key
     * @throws RuntimeException if key does not exist in metadata
     * @return mixed
     */
    public function getMeta(string $key);

    /**
     * Get $key from message metadata or default in case key does not exist
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getMetaOrDefault(string $key, $default);

    public function withPayload(array $payload, Schema $assertion, PayloadSchema $payloadSchema, TypeSchemaMap $typeSchemaMap): self;

    /**
     * Should be one of Message::TYPE_COMMAND, Message::TYPE_EVENT or Message::TYPE_QUERY
     */
    public function messageType(): string;

    public function messageName(): string;

    public function uuid(): UuidInterface;

    public function createdAt(): \DateTimeImmutable;

    public function payload(): array;

    public function metadata(): array;

    public function withMetadata(array $metadata): Message;

    /**
     * Returns new instance of message with $key => $value added to metadata
     *
     * Given value must have a scalar or array type.
     */
    public function withAddedMetadata(string $key, $value): Message;
}
