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

use EventEngine\Messaging\Exception\MessageNotFound;
use EventEngine\Runtime\Flavour;
use EventEngine\Schema\PayloadSchema;
use EventEngine\Schema\Schema;
use EventEngine\Schema\TypeSchema;
use EventEngine\Schema\TypeSchemaMap;
use EventEngine\Util\UtcDateTime;
use Ramsey\Uuid\Uuid;

final class GenericSchemaMessageFactory implements MessageFactory
{
    /**
     * @var Schema
     */
    private $schema;

    /**
     * Map of command message schemas indexed by message name
     *
     * @var PayloadSchema[]
     */
    private $commandMap = [];

    /**
     * Map of event message schemas indexed by message name
     *
     * @var PayloadSchema[]
     */
    private $eventMap = [];

    /**
     * Map of query message schemas indexed by message name
     *
     * @var PayloadSchema[]
     */
    private $queryMap = [];

    /**
     * Map of type definitions used within other schemas indexed by type name
     *
     * @var TypeSchemaMap
     */
    private $typeSchemaMap;

    /**
     * @var Flavour
     */
    private $flavour;

    public function __construct(Schema $schema, array $commandMap, array $eventMap, array $queryMap, TypeSchemaMap $typeSchemaMap, Flavour $flavour)
    {
        $this->schema = $schema;
        $this->commandMap = $commandMap;
        $this->eventMap = $eventMap;
        $this->queryMap = $queryMap;
        $this->typeSchemaMap = $typeSchemaMap;
        $this->flavour = $flavour;
    }

    /**
     * {@inheritdoc}
     */
    public function createMessageFromArray(string $messageName, array $messageData): Message
    {
        GenericSchemaMessage::assertMessageName($messageName);

        [$messageType, $payloadSchema] = $this->getPayloadSchemaAndMessageType($messageName);

        if (! isset($messageData['payload'])) {
            $messageData['payload'] = [];
        }

        if(is_array($payloadSchema)) {
            throw new \RuntimeException("array payload schema");
        }

        $this->schema->assertPayload($messageName, $messageData['payload'], $payloadSchema, $this->typeSchemaMap);

        $messageData['message_name'] = $messageName;

        if (! isset($messageData['uuid'])) {
            $messageData['uuid'] = Uuid::uuid4()->toString();
        }

        if (! isset($messageData['created_at'])) {
            $messageData['created_at'] = UtcDateTime::now();
        }

        if (! isset($messageData['metadata'])) {
            $messageData['metadata'] = [];
        }

        switch ($messageType) {
            case Message::TYPE_COMMAND:
                $message = GenericCommand::fromArray($messageData);
                break;
            case Message::TYPE_EVENT:
                $message = GenericEvent::fromArray($messageData);
                break;
            case Message::TYPE_QUERY:
                $message = GenericQuery::fromArray($messageData);
                break;
        }

        return $this->flavour->convertMessageReceivedFromNetwork($message);
    }

    public function setFlavour(Flavour $flavour): void
    {
        $this->flavour = $flavour;
    }

    public function setPayloadFor(Message $message, array $payload): Message
    {
        [, $payloadSchema] = $this->getPayloadSchemaAndMessageType($message->messageName());

        return $message->withPayload($payload, $this->schema, $payloadSchema, $this->typeSchemaMap);
    }

    private function getPayloadSchemaAndMessageType(string $messageName): array
    {
        $payloadSchema = null;
        $messageType = null;

        if (\array_key_exists($messageName, $this->commandMap)) {
            $messageType = Message::TYPE_COMMAND;
            $payloadSchema = $this->commandMap[$messageName];
        }

        if ($messageType === null && \array_key_exists($messageName, $this->eventMap)) {
            $messageType = Message::TYPE_EVENT;
            $payloadSchema = $this->eventMap[$messageName];
        }

        if ($messageType === null && \array_key_exists($messageName, $this->queryMap)) {
            $messageType = Message::TYPE_QUERY;
            $payloadSchema = $this->queryMap[$messageName];
        }

        if (null === $messageType) {
            throw new MessageNotFound(
                "Unknown message received. Got message with name: $messageName"
            );
        }

        if (null === $payloadSchema && $messageType === Message::TYPE_QUERY) {
            $payloadSchema = $this->schema->emptyPayloadSchema();
        }

        return [$messageType, $payloadSchema];
    }
}
