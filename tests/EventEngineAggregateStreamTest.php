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

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\DocumentStore\InMemoryDocumentStore;
use EventEngine\EventEngine;
use EventEngine\EventStore\EventStore;
use EventEngine\JsonSchema\JsonSchema;
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

abstract class EventEngineAggregateStreamTest extends BasicTestCase
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

    /**
     * @var DocumentStore
     */
    private $documentStore;

    abstract protected function getSchemaInstance(): Schema;

    protected function setUp()
    {
        parent::setUp();

        $this->eventEngine = new EventEngine($this->getSchemaInstance());

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

        $this->documentStore = new InMemoryDocumentStore($this->inMemoryConnection);
        $this->eventStore = new ProophEventStore(new InMemoryEventStore($this->inMemoryConnection), true);

        $this->eventStore->createStream(self::AGGREGATE_STREAM);

        $this->eventEngine->initialize(
            new PrototypingFlavour(),
            $this->eventStore,
            new SimpleMessageEngine(new DevNull()),
            $this->prophesize(ContainerInterface::class)->reveal(),
            $this->documentStore
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

    /**
     * @test
     */
    public function it_can_rebuild_aggregate_state(): void
    {
        $userId = Uuid::uuid4()->toString();
        $name = 'John';

        $collectionName = 'User_Projection_0_1_0';

        $this->documentStore->addCollection($collectionName);

        $this->eventEngine->dispatch(self::CMD_REGISTER_USER, [
            'id' => $userId,
            'name' => $name,
        ]);

        $userState = $this->eventEngine->loadAggregateState(self::AR_USER, $userId);
        $this->eventEngine->clearAggregateCache();

        // ensure empty document store collection
        $this->documentStore->deleteDoc($collectionName, $userId);

        $this->eventEngine->rebuildAggregateState(self::AR_USER, $userId);
        $userStateRebuild = $this->eventEngine->loadAggregateState(self::AR_USER, $userId);

        $this->assertSame($userState, $userStateRebuild);
    }
}
