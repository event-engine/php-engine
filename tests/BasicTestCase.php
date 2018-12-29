<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineTest;

use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\JustinRainbowJsonSchema;
use EventEngine\Messaging\GenericCommand;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\MessageFactory;
use EventEngine\Schema\Schema;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;

class BasicTestCase extends TestCase
{
    /**
     * @var Schema
     */
    private $schema;

    /**
     * @var MessageFactory
     */
    private $commandMessageFactory;

    /**
     * @var MessageFactory
     */
    private $eventMessageFactory;

    protected function getSchema(): Schema
    {
        if (null === $this->schema) {
            $this->schema = new JustinRainbowJsonSchema();
        }

        return $this->schema;
    }

    protected function getMockedCommandMessageFactory(): MessageFactory
    {
        if (null === $this->commandMessageFactory) {
            $messageFactory = $this->prophesize(MessageFactory::class);

            $schemaAssertion = $this->prophesize(Schema::class);

            $schemaAssertion->assertPayload(Argument::any(), Argument::any(), Argument::any())->will(function () {
            });

            $messageFactory->createMessageFromArray(Argument::any(), Argument::any())->will(function ($args) use ($schemaAssertion) {
                list($commandName, $commandData) = $args;
                if (! isset($commandData['payload'])) {
                    $commandData = [
                        'payload' => $commandData,
                    ];
                }
                $command = new GenericCommand($commandName, $commandData['payload'], $schemaAssertion->reveal(), JsonSchema::object([]));

                return $command->withMetadata($commandData['metadata'] ?? []);
            });

            $this->commandMessageFactory = $messageFactory->reveal();
        }

        return $this->commandMessageFactory;
    }

    protected function getMockedEventMessageFactory(): MessageFactory
    {
        if (null === $this->eventMessageFactory) {
            $messageFactory = $this->prophesize(MessageFactory::class);

            $schemaAssertion = $this->prophesize(Schema::class);

            $schemaAssertion->assertPayload(Argument::any(), Argument::any(), Argument::any())->will(function () {
            });

            $messageFactory->createMessageFromArray(Argument::any(), Argument::any())->will(function ($args) use ($schemaAssertion) {
                list($eventName, $eventData) = $args;
                $event = new GenericEvent($eventName, $eventData['payload'], $schemaAssertion->reveal(), JsonSchema::object([]));

                return $event->withMetadata($eventData['metadata'] ?? []);
            });

            $this->eventMessageFactory = $messageFactory->reveal();
        }

        return $this->eventMessageFactory;
    }
}
