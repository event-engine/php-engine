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
use EventEngine\Util\UtcDateTime;
use EventEngine\Util\VariableType;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

abstract class GenericSchemaMessage implements Message
{
    /**
     * @var string
     */
    protected $messageName;

    /**
     * @var UuidInterface
     */
    protected $uuid;

    /**
     * @var \DateTimeImmutable
     */
    protected $createdAt;

    /**
     * @var array
     */
    protected $metadata = [];

    /**
     * @var array
     */
    private $payload;

    public function __construct(
        string $messageName,
        array $payload,
        Schema $schemaAssertion,
        PayloadSchema $payloadSchema,
        TypeSchema ...$typeSchemas
    ) {
        self::assertMessageName($messageName);
        $this->messageName = $messageName;
        $schemaAssertion->assertPayload($messageName, $payload, $payloadSchema, ...$typeSchemas);
        $this->init();
        $this->setPayload($payload);
    }

    protected function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function get(string $key)
    {
        if (! \array_key_exists($key, $this->payload)) {
            throw new RuntimeException("Message payload of {$this->messageName()} does not contain a key $key.");
        }

        return $this->payload[$key];
    }

    public function getOrDefault(string $key, $default)
    {
        if (! \array_key_exists($key, $this->payload)) {
            return $default;
        }

        return $this->payload[$key];
    }

    public function payload(): array
    {
        return $this->payload;
    }

    public function withPayload(array $payload, Schema $assertion, PayloadSchema $payloadSchema, TypeSchemaMap $typeSchemaMap): Message
    {
        $assertion->assertPayload($this->messageName, $payload, $payloadSchema, $typeSchemaMap);
        $copy = clone $this;
        $copy->payload = $payload;

        return $copy;
    }

    public static function fromArray(array $messageData): Message
    {
        self::assert($messageData);

        $messageRef = new \ReflectionClass(\get_called_class());

        /** @var $message GenericSchemaMessage */
        $message = $messageRef->newInstanceWithoutConstructor();

        $message->uuid = Uuid::fromString($messageData['uuid']);
        $message->messageName = $messageData['message_name'];
        $message->metadata = $messageData['metadata'];
        $message->createdAt = $messageData['created_at'];
        $message->setPayload($messageData['payload']);

        return $message;
    }

    protected function init(): void
    {
        if ($this->uuid === null) {
            $this->uuid = Uuid::uuid4();
        }

        if ($this->messageName === null) {
            $this->messageName = \get_class($this);
        }

        if ($this->createdAt === null) {
            $this->createdAt = UtcDateTime::now();
        }
    }

    public function uuid(): UuidInterface
    {
        return $this->uuid;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    public function toArray(): array
    {
        return [
            'message_name' => $this->messageName,
            'uuid' => $this->uuid->toString(),
            'payload' => $this->payload(),
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt(),
        ];
    }

    public function messageName(): string
    {
        return $this->messageName;
    }

    public function withMetadata(array $metadata): Message
    {
        $message = clone $this;

        $message->metadata = $metadata;

        return $message;
    }

    /**
     * Returns new instance of message with $key => $value added to metadata
     *
     * Given value must have a scalar type.
     */
    public function withAddedMetadata(string $key, $value): Message
    {
        self::assertMetadata([$key => $value]);

        $message = clone $this;

        $message->metadata[$key] = $value;

        return $message;
    }

    /**
     * @param mixed $messageData
     *
     * @return void
     */
    public static function assert($messageData): void
    {
        if(!is_array($messageData)) {
            throw new RuntimeException('MessageData must be an array');
        }

        if(!array_key_exists('message_name', $messageData)) {
            throw new RuntimeException('MessageData must contain a key message_name');
        }

        if(!array_key_exists('uuid', $messageData)) {
            throw new RuntimeException('MessageData must contain a key uuid');
        }

        if(!array_key_exists('payload', $messageData)) {
            throw new RuntimeException('MessageData must contain a key payload');
        }

        if(!array_key_exists('metadata', $messageData)) {
            throw new RuntimeException('MessageData must contain a key metadata');
        }

        if(!array_key_exists('created_at', $messageData)) {
            throw new RuntimeException('MessageData must contain a key created_at');
        }

        self::assertMessageName($messageData['message_name']);
        self::assertUuid($messageData['uuid']);
        self::assertPayload($messageData['payload']);
        self::assertMetadata($messageData['metadata']);
        self::assertCreatedAt($messageData['created_at']);
    }

    public static function assertUuid(string $uuid): void
    {
        if(!Uuid::isValid($uuid)) {
            throw new RuntimeException('uuid must be a valid UUID string');
        }
    }

    public static function assertMessageName($messageName): void
    {
        if (! \preg_match('/^[A-Za-z0-9_.-\/]+$/', $messageName)) {
            throw new RuntimeException('message_name is invalid');
        }
    }

    public static function assertPayload($payload): void
    {
        if(!is_array($payload)) {
            throw new RuntimeException('payload must be an array');
        }
        self::assertSubLevel($payload, 'Payload');
    }

    /**
     * @param mixed $payload
     */
    private static function assertSubLevel($payload, string $messagePart): void
    {
        if (\is_array($payload)) {
            foreach ($payload as $subPayload) {
                self::assertSubLevel($subPayload, $messagePart);
            }

            return;
        }

        if(null === $payload || is_scalar($payload)) {
            return;
        }

        throw new RuntimeException("$messagePart must only contain arrays and scalar values");
    }

    public static function assertMetadata($metadata): void
    {
        if(!is_array($metadata)) {
            throw new RuntimeException('metadata must be an array');
        }

        foreach ($metadata as $key => $value) {
            self::assertSubLevel($value, 'Metadata');
        }
    }

    public static function assertCreatedAt($createdAt): void
    {
        if(!$createdAt instanceof \DateTimeImmutable) {
            throw new RuntimeException(\sprintf(
                'created_at must be of type %s. Got %s',
                \DateTimeImmutable::class,
                VariableType::determine($createdAt)
            ));
        }
    }
}
