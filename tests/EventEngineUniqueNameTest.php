<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineTest;

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\DocumentStore\InMemoryDocumentStore;
use EventEngine\EventEngine;
use EventEngine\EventStore\EventStore;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\JustinRainbowJsonSchema;
use EventEngine\Logger\DevNull;
use EventEngine\Logger\SimpleMessageEngine;
use EventEngine\Messaging\Message;
use EventEngine\Persistence\InMemoryConnection;
use EventEngine\Prooph\V7\EventStore\InMemoryEventStore;
use EventEngine\Prooph\V7\EventStore\ProophEventStore;
use EventEngine\Runtime\PrototypingFlavour;
use EventEngine\Schema\Schema;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;

class EventEngineUniqueNameTest extends BasicTestCase
{
    const CMD_REGISTER_USER = 'RegisterUser';
    const EV_USER_REGISTERED = 'UserHasRegistered';
    const QY_LIST_USERS = 'ListUsers';
    /**
     * @var EventEngine
     */
    private $eventEngine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventEngine = new EventEngine(new JustinRainbowJsonSchema());

        $this->eventEngine->registerCommand(self::CMD_REGISTER_USER, JsonSchema::object([]));
        $this->eventEngine->registerEvent(self::EV_USER_REGISTERED, JsonSchema::object([]));
        $this->eventEngine->registerQuery(self::QY_LIST_USERS, JsonSchema::object([]));
    }
    /**
     * @test
     */
    public function it_does_not_allow_to_register_command_as_event()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Command with name/');

        $this->eventEngine->registerEvent(self::CMD_REGISTER_USER, JsonSchema::object([]));
    }

    /**
     * @test
     */
    public function it_does_not_allow_to_register_command_as_query()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Command with name/');

        $this->eventEngine->registerQuery(self::CMD_REGISTER_USER, JsonSchema::object([]));
    }

    /**
     * @test
     */
    public function it_does_not_allow_to_register_event_as_command()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Event with name/');

        $this->eventEngine->registerCommand(self::EV_USER_REGISTERED, JsonSchema::object([]));
    }

    /**
     * @test
     */
    public function it_does_not_allow_to_register_event_as_query()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Event with name/');

        $this->eventEngine->registerQuery(self::EV_USER_REGISTERED, JsonSchema::object([]));
    }

    /**
     * @test
     */
    public function it_does_not_allow_to_register_query_as_command()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Query with name/');

        $this->eventEngine->registerCommand(self::QY_LIST_USERS, JsonSchema::object([]));
    }

    /**
     * @test
     */
    public function it_does_not_allow_to_register_query_as_event()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Query with name/');

        $this->eventEngine->registerEvent(self::QY_LIST_USERS, JsonSchema::object([]));
    }
}
