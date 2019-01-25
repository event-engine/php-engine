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
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;

class EventEngineAggregateStreamTest extends BasicTestCase
{
    private const AR_USER = 'User';
    private const CMD_REGISTER_USER = 'RegisterUser';
    private const EVT_USER_REGISTERED = 'UserRegistered';

    private const AGGREGATE_STREAM = 'custom_stream';

    /**
     * @var EventEngine
     */
    private $eventEngine;

    /**
     * @var InMemoryConnection
     */
    private $inMemoryConnection;

    /**
     * @var EventStore
     */
    private $eventStore;

    protected function setUp()
    {
        parent::setUp();

        $this->eventEngine = new EventEngine(new JustinRainbowJsonSchema());

        $this->eventEngine->registerCommand(self::CMD_REGISTER_USER, JsonSchema::object([
            'id' => JsonSchema::uuid(),
            'name' => JsonSchema::string()->withMinLength(1)
        ]));

        $this->eventEngine->registerEvent(self::EVT_USER_REGISTERED, JsonSchema::object([
            'id' => JsonSchema::uuid(),
            'name' => JsonSchema::string()->withMinLength(1)
        ]));

        $this->eventEngine->process(self::CMD_REGISTER_USER)
            ->withNew(self::AR_USER)
            ->handle(function (Message $registerUser) {
                yield [self::EVT_USER_REGISTERED, $registerUser->payload()];
            })
            ->recordThat(self::EVT_USER_REGISTERED)
            ->apply(function (Message $userRegistered) {
                return $userRegistered->payload();
            })
        ->storeEventsIn(self::AGGREGATE_STREAM);

        $this->inMemoryConnection = new InMemoryConnection();

        $this->eventStore = new ProophEventStore(new InMemoryEventStore($this->inMemoryConnection), true);

        $this->eventStore->createStream(self::AGGREGATE_STREAM);

        $this->eventEngine->initialize(
            new PrototypingFlavour(),
            $this->eventStore,
            new SimpleMessageEngine(new DevNull()),
            $this->prophesize(ContainerInterface::class)->reveal()
        );

        $this->eventEngine->bootstrap(EventEngine::ENV_TEST, true);
    }


    /**
     * @test
     */
    public function it_uses_custom_aggregate_stream_if_defined()
    {
        $userId = Uuid::uuid4()->toString();
        $name = 'John';

        $this->eventEngine->dispatch(self::CMD_REGISTER_USER, [
            'id' => $userId,
            'name' => $name,
        ]);

        $this->eventEngine->clearAggregateCache();

        $user = $this->eventEngine->loadAggregateState(self::AR_USER, $userId);

        $this->assertEquals($name, $user['name']);
    }
}
