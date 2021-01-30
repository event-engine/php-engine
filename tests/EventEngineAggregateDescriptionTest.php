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

use EventEngine\Commanding\CommandPreProcessor;
use EventEngine\DocumentStore\DocumentStore;
use EventEngine\DocumentStore\InMemoryDocumentStore;
use EventEngine\EventEngine;
use EventEngine\EventStore\EventStore;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\OpisJsonSchema;
use EventEngine\Logger\DevNull;
use EventEngine\Logger\SimpleMessageEngine;
use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\Message;
use EventEngine\Persistence\InMemoryConnection;
use EventEngine\Prooph\V7\EventStore\InMemoryEventStore;
use EventEngine\Prooph\V7\EventStore\ProophEventStore;
use EventEngine\Runtime\PrototypingFlavour;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;

class EventEngineAggregateDescriptionTest extends BasicTestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventEngine = new EventEngine(new OpisJsonSchema());

        $this->eventEngine->registerCommand(self::CMD_REGISTER_USER, JsonSchema::object([
            'id' => JsonSchema::uuid(),
            'name' => JsonSchema::string()->withMinLength(1)
        ]));

        $this->eventEngine->registerEvent(self::EVT_USER_REGISTERED, JsonSchema::object([
            'id' => JsonSchema::uuid(),
            'name' => JsonSchema::string()->withMinLength(1)
        ]));

        $this->inMemoryConnection = new InMemoryConnection();

        $this->documentStore = new InMemoryDocumentStore($this->inMemoryConnection);
        $this->eventStore = new ProophEventStore(new InMemoryEventStore($this->inMemoryConnection), true);

        $this->eventStore->createStream(self::AGGREGATE_STREAM);
    }

    /**
     * @test
     */
    public function it_calls_registered_pre_processor(): void
    {
        $passThruPreProcessor = new class() implements CommandPreProcessor {

            /**
             * @var Message
             */
            public $newCommand;

            public function preProcess(Message $command)
            {
                $this->newCommand = $command;
                return $command;
            }

            public function __invoke($command)
            {
                return $this->preProcess($command);
            }
        };

        $this->eventEngine->process(self::CMD_REGISTER_USER)
            ->withNew(self::AR_USER)
            ->preProcess($passThruPreProcessor)
            ->handle(static function (Message $registerUser) {
                yield [self::EVT_USER_REGISTERED, $registerUser->payload()];
            })
            ->recordThat(self::EVT_USER_REGISTERED)
            ->apply(static function (Message $userRegistered) {
                return $userRegistered->payload();
            })
            ->storeEventsIn(self::AGGREGATE_STREAM);

        $this->eventEngine->initialize(
            new PrototypingFlavour(),
            $this->eventStore,
            new SimpleMessageEngine(new DevNull()),
            $this->prophesize(ContainerInterface::class)->reveal(),
            $this->documentStore
        );

        $this->eventEngine->bootstrap(EventEngine::ENV_TEST, true);

        $userId = Uuid::uuid4()->toString();
        $name = 'John';

        $this->eventEngine->dispatch(self::CMD_REGISTER_USER, [
            'id' => $userId,
            'name' => $name,
        ]);

        $this->assertSame(self::CMD_REGISTER_USER, $passThruPreProcessor->newCommand->messageName());

        $this->eventEngine->clearAggregateCache();

        $user = $this->eventEngine->loadAggregateState(self::AR_USER, $userId);

        $this->assertEquals($name, $user['name']);
    }
}
