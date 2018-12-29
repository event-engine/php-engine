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
use EventEngine\EventEngine;
use EventEngine\EventStore\EventStore;
use EventEngine\JsonSchema\JustinRainbowJsonSchema;
use EventEngine\Logger\DevNull;
use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageBag;
use EventEngine\Messaging\MessageDispatcher;
use EventEngine\Messaging\MessageFactoryAware;
use EventEngine\Persistence\InMemoryConnection;
use EventEngine\Projecting\AggregateProjector;
use EventEngine\Prooph\V7\EventStore\InMemoryEventStore;
use EventEngine\Prooph\V7\EventStore\ProophEventStore;
use EventEngine\Querying\Resolver;
use EventEngine\Runtime\Flavour;
use EventEngine\Util\Await;
use EventEngineExample\FunctionalFlavour\Event\UsernameChanged;
use EventEngineExample\FunctionalFlavour\Event\UserRegistered;
use EventEngineExample\PrototypingFlavour\Aggregate\UserDescription;
use EventEngineExample\PrototypingFlavour\Messaging\Command;
use EventEngineExample\PrototypingFlavour\Messaging\Event;
use Prophecy\Argument;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;

abstract class EventEngineTestAbstract extends BasicTestCase
{
    abstract protected function loadEventMachineDescriptions(EventEngine $eventMachine);

    abstract protected function getFlavour(): Flavour;

    abstract protected function getRegisteredUsersProjector(DocumentStore $documentStore);

    abstract protected function getUserRegisteredListener(MessageDispatcher $messageDispatcher);

    abstract protected function getUserResolver(array $cachedUserState): Resolver;

    abstract protected function getUsersResolver(array $cachedUsers): Resolver;

    abstract protected function assertLoadedUserState($userState): void;

    /**
     * @var EventStore
     */
    private $eventStore;

    /**
     * @var ContainerInterface
     */
    private $appContainer;
    /**
     * @var EventEngine
     */
    private $eventEngine;

    /**
     * InMemoryConnection
     *
     * @var InMemoryConnection
     */
    private $inMemoryConnection;

    /**
     * @var Flavour
     */
    private $flavour;

    protected function setUp()
    {
        $this->eventEngine = new EventEngine(new JustinRainbowJsonSchema());

        $this->loadEventMachineDescriptions($this->eventEngine);

        $this->inMemoryConnection = new InMemoryConnection();

        $this->eventStore = new ProophEventStore(new InMemoryEventStore($this->inMemoryConnection));

        $this->eventStore->createStream($this->eventEngine->writeModelStreamName());

        $this->flavour = $this->getFlavour();

        $this->appContainer = $this->prophesize(ContainerInterface::class);
    }

    protected function tearDown()
    {
        $this->eventEngine = null;
        $this->eventStore = null;
        $this->appContainer = null;
        $this->inMemoryConnection = null;
        $this->flavour = null;
    }

    protected function setUpAggregateProjector(
        DocumentStore $documentStore,
        EventStore $eventStore,
        StreamName $streamName
    ): void {
        $aggregateProjector = new AggregateProjector($documentStore, $this->eventEngine);

        $eventStore->create(new \Prooph\EventStore\Stream($streamName, new \ArrayIterator([])));

        $this->appContainer->has(EventMachine::SERVICE_ID_PROJECTION_MANAGER)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_PROJECTION_MANAGER)->willReturn(new InMemoryProjectionManager(
            $eventStore,
            $this->inMemoryConnection
        ));
        $this->appContainer->get(EventMachine::SERVICE_ID_EVENT_STORE)->will(function ($args) use ($eventStore) {
            return $eventStore;
        });

        $this->appContainer->has(AggregateProjector::class)->willReturn(true);
        $this->appContainer->get(AggregateProjector::class)->will(function ($args) use ($aggregateProjector) {
            return $aggregateProjector;
        });

        $this->eventEngine->watch(Stream::ofWriteModel())
            ->with(AggregateProjector::generateProjectionName(Aggregate::USER), AggregateProjector::class)
            ->filterAggregateType(Aggregate::USER);
    }

    protected function setUpRegisteredUsersProjector(
        DocumentStore $documentStore,
        EventStore $eventStore,
        StreamName $streamName
    ): void {
        $projector = $this->getRegisteredUsersProjector($documentStore);

        $eventStore->create(new \Prooph\EventStore\Stream($streamName, new \ArrayIterator([])));

        $this->appContainer->has(EventMachine::SERVICE_ID_PROJECTION_MANAGER)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_PROJECTION_MANAGER)->willReturn(new InMemoryProjectionManager(
            $eventStore,
            $this->inMemoryConnection
        ));
        $this->appContainer->get(EventMachine::SERVICE_ID_EVENT_STORE)->will(function ($args) use ($eventStore) {
            return $eventStore;
        });

        $this->appContainer->has('Test.Projector.RegisteredUsers')->willReturn(true);
        $this->appContainer->get('Test.Projector.RegisteredUsers')->will(function ($args) use ($projector) {
            return $projector;
        });

        $this->eventEngine->watch(Stream::ofWriteModel())
            ->with('registered_users', 'Test.Projector.RegisteredUsers')
            ->filterEvents([
                \ProophExample\PrototypingFlavour\Messaging\Event::USER_WAS_REGISTERED,
            ]);
    }

    /**
     * @test
     */
    public function it_dispatches_a_known_command()
    {
        $publishedEvents = [];

        $this->eventEngine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $this->convertToEventMachineMessage($event);
        });

        $this->eventEngine->initialize(
            $this->flavour,
            $this->eventStore,
            new DevNull(),
            $this->appContainer->reveal()
        );

        $userId = Uuid::uuid4()->toString();

        $this->eventEngine->bootstrap();

        $registerUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        /** @var CommandDispatchResult $result */
        $result = Await::lastResult($this->eventEngine->dispatch($registerUser));

        $recordedEvents = $result->recordedEvents();

        self::assertCount(1, $recordedEvents);
        self::assertCount(1, $publishedEvents);
        /** @var GenericEvent $event */
        $event = $recordedEvents[0];

        $this->assertUserWasRegistered($event, $registerUser, $userId);
    }

    /**
     * @test
     */
    public function it_dispatches_a_known_query()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $userId = Uuid::uuid4()->toString();

        $getUserResolver = $this->getUserResolver([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
        ]);

        $this->appContainer->has(\get_class($getUserResolver))->willReturn(true);
        $this->appContainer->get(\get_class($getUserResolver))->will(function ($args) use ($getUserResolver) {
            return $getUserResolver;
        });

        $this->eventEngine->initialize($this->containerChain);

        $getUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Query::GET_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
            ]]
        );

        $promise = $this->eventEngine->bootstrap()->dispatch($getUser);

        $userData = null;

        $promise->done(function (array $data) use (&$userData) {
            $userData = $data;
        });

        self::assertEquals([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
        ], $userData);
    }

    /**
     * @test
     */
    public function it_allows_queries_without_payload()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $getUsersResolver = $this->getUsersResolver([
            [
                UserDescription::IDENTIFIER => '123',
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ],
        ]);

        $this->appContainer->has(\get_class($getUsersResolver))->willReturn(true);
        $this->appContainer->get(\get_class($getUsersResolver))->will(function ($args) use ($getUsersResolver) {
            return $getUsersResolver;
        });

        $this->eventEngine->initialize($this->containerChain);

        $getUsers = $this->eventEngine->messageFactory()->createMessageFromArray(
            Query::GET_USERS,
            ['payload' => []]
        );

        $promise = $this->eventEngine->bootstrap()->dispatch($getUsers);

        $userList = null;

        $promise->done(function (array $data) use (&$userList) {
            $userList = $data;
        });

        self::assertEquals([
            [
                UserDescription::IDENTIFIER => '123',
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ],
        ], $userList);
    }

    /**
     * @test
     */
    public function it_creates_message_on_dispatch_if_only_name_and_payload_is_given()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $recordedEvents = [];

        $this->eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = \iterator_to_array($args[1]);
        });

        $publishedEvents = [];

        $this->eventEngine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $this->convertToEventMachineMessage($event);
        });

        $this->eventEngine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $this->eventEngine->bootstrap()->dispatch(Command::REGISTER_USER, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
            UserDescription::EMAIL => 'contact@prooph.de',
        ]);

        self::assertCount(1, $recordedEvents);
        self::assertCount(1, $publishedEvents);
        /** @var GenericJsonSchemaEvent $event */
        $event = $recordedEvents[0];
        self::assertEquals(Event::USER_WAS_REGISTERED, $event->messageName());
    }

    /**
     * @test
     */
    public function it_can_handle_command_for_existing_aggregate()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $recordedEvents = [];

        $this->eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = \array_merge($recordedEvents, \iterator_to_array($args[1]));
        });

        $this->eventStore->load(new StreamName('event_stream'), 1, null, Argument::type(MetadataMatcher::class))->will(function ($args) use (&$recordedEvents) {
            return new \ArrayIterator([$recordedEvents[0]]);
        });

        $publishedEvents = [];

        $this->eventEngine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $this->convertToEventMachineMessage($event);
        });

        $this->eventEngine->on(Event::USERNAME_WAS_CHANGED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $this->convertToEventMachineMessage($event);
        });

        $this->eventEngine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $this->eventEngine->bootstrap()->dispatch(Command::REGISTER_USER, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
            UserDescription::EMAIL => 'contact@prooph.de',
        ]);

        $this->eventEngine->dispatch(Command::CHANGE_USERNAME, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'John',
        ]);

        self::assertCount(2, $recordedEvents);
        self::assertCount(2, $publishedEvents);
        /** @var GenericJsonSchemaEvent $event */
        $event = $recordedEvents[1];
        self::assertEquals(Event::USERNAME_WAS_CHANGED, $event->messageName());
    }

    /**
     * @test
     */
    public function it_enables_async_switch_message_router_if_container_has_a_producer()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $producedEvents = [];

        $eventMachine = $this->eventEngine;

        $messageProducer = $this->prophesize(MessageProducer::class);
        $messageProducer->__invoke(Argument::type(ProophMessage::class), Argument::exact(null))
            ->will(function ($args) use (&$producedEvents, $eventMachine) {
                $producedEvents[] = $args[0];
                $eventMachine->dispatch($args[0]);
            });

        $this->appContainer->has(EventMachine::SERVICE_ID_ASYNC_EVENT_PRODUCER)->willReturn(true);
        $this->appContainer->get(EventMachine::SERVICE_ID_ASYNC_EVENT_PRODUCER)->will(function ($args) use ($messageProducer) {
            return $messageProducer->reveal();
        });

        $recordedEvents = [];

        $this->eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = \iterator_to_array($args[1]);
        });

        $publishedEvents = [];

        $this->eventEngine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $this->convertToEventMachineMessage($event);
        });

        $this->eventEngine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        $this->eventEngine->bootstrap()->dispatch($registerUser);

        self::assertCount(1, $recordedEvents);
        self::assertCount(1, $publishedEvents);
        self::assertCount(1, $producedEvents);
        /** @var GenericJsonSchemaEvent $event */
        $event = $recordedEvents[0];

        $this->assertUserWasRegistered($event, $registerUser, $userId);

        self::assertEquals(Event::USER_WAS_REGISTERED, $producedEvents[0]->messageName());
        self::assertEquals(Event::USER_WAS_REGISTERED, $publishedEvents[0]->messageName());
    }

    /**
     * @test
     */
    public function it_can_load_aggregate_state()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $this->eventEngine->initialize($this->containerChain);
        $eventMachine = $this->eventEngine;
        $userId = Uuid::uuid4()->toString();

        $this->eventStore->load(new StreamName('event_stream'), 1, null, Argument::any())->will(function ($args) use ($userId, $eventMachine) {
            return new \ArrayIterator([
                $eventMachine->messageFactory()->createMessageFromArray(Event::USER_WAS_REGISTERED, [
                    'payload' => [
                        'userId' => $userId,
                        'username' => 'Tester',
                        'email' => 'tester@test.com',
                    ],
                    'metadata' => [
                        '_aggregate_id' => $userId,
                        '_aggregate_type' => Aggregate::USER,
                        '_aggregate_version' => 1,
                    ],
                ]),
            ]);
        });

        $userState = $eventMachine->bootstrap()->loadAggregateState(Aggregate::USER, $userId);

        $this->assertLoadedUserState($userState);
    }

    /**
     * @test
     */
    public function it_sets_up_transaction_manager_if_event_store_supports_transactions()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $this->eventStore = $this->prophesize(TransactionalEventStore::class);

        $this->actionEventEmitterEventStore = new TransactionalActionEventEmitterEventStore(
            $this->eventStore->reveal(),
            new ProophActionEventEmitter(TransactionalActionEventEmitterEventStore::ALL_EVENTS)
        );

        $recordedEvents = [];

        $this->eventStore->beginTransaction()->shouldBeCalled();

        $this->eventStore->inTransaction()->willReturn(true);

        $this->eventStore->appendTo(new StreamName('event_stream'), Argument::any())->will(function ($args) use (&$recordedEvents) {
            $recordedEvents = \iterator_to_array($args[1]);
        });

        $this->eventStore->commit()->shouldBeCalled();

        $publishedEvents = [];

        $this->eventEngine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $event;
        });

        $this->eventEngine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $this->eventEngine->bootstrap()->dispatch(Command::REGISTER_USER, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
            UserDescription::EMAIL => 'contact@prooph.de',
        ]);

        self::assertCount(1, $recordedEvents);
        self::assertCount(1, $publishedEvents);
        /** @var GenericJsonSchemaEvent $event */
        $event = $recordedEvents[0];
        self::assertEquals(Event::USER_WAS_REGISTERED, $event->messageName());
    }

    /**
     * @test
     */
    public function it_provides_message_schemas()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $this->eventEngine->initialize($this->containerChain);

        $userId = new UuidType();

        $username = (new StringType())->withMinLength(1);

        $userDataSchema = JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => $username,
            UserDescription::EMAIL => new EmailType(),
        ], [
            'shouldFail' => JsonSchema::boolean(),
        ]);

        $filterInput = JsonSchema::object([
            'username' => JsonSchema::nullOr(JsonSchema::string()),
            'email' => JsonSchema::nullOr(JsonSchema::email()),
        ]);

        self::assertEquals([
            'commands' => [
                Command::REGISTER_USER => $userDataSchema->toArray(),
                Command::CHANGE_USERNAME => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
                    UserDescription::USERNAME => $username,
                ])->toArray(),
                Command::DO_NOTHING => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
                ])->toArray(),
            ],
            'events' => [
                Event::USER_WAS_REGISTERED => $userDataSchema->toArray(),
                Event::USERNAME_WAS_CHANGED => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
                    'oldName' => $username,
                    'newName' => $username,
                ])->toArray(),
                Event::USER_REGISTRATION_FAILED => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
                ])->toArray(),
            ],
            'queries' => [
                Query::GET_USER => JsonSchema::object([
                    UserDescription::IDENTIFIER => $userId,
                ])->toArray(),
                Query::GET_USERS => JsonSchema::object([])->toArray(),
                Query::GET_FILTERED_USERS => JsonSchema::object([], [
                    'filter' => $filterInput,
                ])->toArray(),
            ],
        ],
            $this->eventEngine->messageSchemas()
        );
    }

    /**
     * @test
     */
    public function it_builds_a_message_box_schema(): void
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $this->eventEngine->initialize($this->containerChain);

        $this->eventEngine->bootstrap();

        $userId = new UuidType();

        $username = (new StringType())->withMinLength(1);

        $userDataSchema = JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => $username,
            UserDescription::EMAIL => new EmailType(),
        ], [
            'shouldFail' => JsonSchema::boolean(),
        ]);

        $filterInput = JsonSchema::object([
            'username' => JsonSchema::nullOr(JsonSchema::string()),
            'email' => JsonSchema::nullOr(JsonSchema::email()),
        ]);

        $queries = [
            Query::GET_USER => JsonSchema::object([
                UserDescription::IDENTIFIER => $userId,
            ])->toArray(),
            Query::GET_USERS => JsonSchema::object([])->toArray(),
            Query::GET_FILTERED_USERS => JsonSchema::object([], [
                'filter' => $filterInput,
            ])->toArray(),
        ];

        $queries[Query::GET_USER]['response'] = JsonSchema::typeRef('User')->toArray();
        $queries[Query::GET_USERS]['response'] = JsonSchema::array(JsonSchema::typeRef('User'))->toArray();
        $queries[Query::GET_FILTERED_USERS]['response'] = JsonSchema::array(JsonSchema::typeRef('User'))->toArray();

        $this->assertEquals([
            'title' => 'Event Machine MessageBox',
            'description' => 'A mechanism for handling prooph messages',
            '$schema' => 'http://json-schema.org/draft-06/schema#',
            'type' => 'object',
            'properties' => [
                'commands' => [
                    Command::REGISTER_USER => $userDataSchema->toArray(),
                    Command::CHANGE_USERNAME => JsonSchema::object([
                        UserDescription::IDENTIFIER => $userId,
                        UserDescription::USERNAME => $username,
                    ])->toArray(),
                    Command::DO_NOTHING => JsonSchema::object([
                        UserDescription::IDENTIFIER => $userId,
                    ])->toArray(),
                ],
                'events' => [
                    Event::USER_WAS_REGISTERED => $userDataSchema->toArray(),
                    Event::USERNAME_WAS_CHANGED => JsonSchema::object([
                        UserDescription::IDENTIFIER => $userId,
                        'oldName' => $username,
                        'newName' => $username,
                    ])->toArray(),
                    Event::USER_REGISTRATION_FAILED => JsonSchema::object([
                        UserDescription::IDENTIFIER => $userId,
                    ])->toArray(),
                ],
                'queries' => $queries,
            ],
            'definitions' => [
                'User' => $userDataSchema->toArray(),
            ],
        ], $this->eventEngine->messageBoxSchema());
    }

    /**
     * @test
     */
    public function it_watches_write_model_stream()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $documentStore = new DocumentStore\InMemoryDocumentStore(new InMemoryConnection());

        $eventStore = new ActionEventEmitterEventStore(
            new InMemoryEventStore($this->inMemoryConnection),
            new ProophActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS)
        );

        $this->setUpAggregateProjector($documentStore, $eventStore, new StreamName('event_stream'));

        $this->eventEngine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        $this->eventEngine->bootstrap()->dispatch($registerUser);

        $this->eventEngine->runProjections(false);

        $userState = $documentStore->getDoc(
            $this->getAggregateCollectionName(Aggregate::USER),
            $userId
        );

        $this->assertNotNull($userState);

        $this->assertEquals([
            'userId' => $userId,
            'username' => 'Alex',
            'email' => 'contact@prooph.de',
            'failed' => null,
        ], $userState);
    }

    /**
     * @test
     */
    public function it_forwards_projector_call_to_flavour()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $documentStore = new DocumentStore\InMemoryDocumentStore(new InMemoryConnection());

        $eventStore = new ActionEventEmitterEventStore(
            new InMemoryEventStore($this->inMemoryConnection),
            new ProophActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS)
        );

        $this->setUpRegisteredUsersProjector($documentStore, $eventStore, new StreamName('event_stream'));

        $this->eventEngine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        $this->eventEngine->bootstrap()->dispatch($registerUser);

        $this->eventEngine->runProjections(false);

        //We expect RegisteredUsersProjector to use collection naming convention: <projection_name>_<app_version>
        $userState = $documentStore->getDoc(
            'registered_users_0.1.0',
            $userId
        );

        $this->assertNotNull($userState);

        $this->assertEquals([
            'userId' => $userId,
            'username' => 'Alex',
            'email' => 'contact@prooph.de',
        ], $userState);
    }

    /**
     * @test
     */
    public function it_invokes_event_listener_using_flavour()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $messageDispatcher = $this->prophesize(MessageDispatcher::class);

        $newCmdName = null;
        $newCmdPayload = null;
        $messageDispatcher->dispatch(Argument::any(), Argument::any())->will(function ($args) use (&$newCmdName, &$newCmdPayload) {
            $newCmdName = $args[0] ?? null;
            $newCmdPayload = $args[1] ?? null;
        });

        $this->eventEngine->on(Event::USER_WAS_REGISTERED, 'Test.Listener.UserRegistered');

        $listener = $this->getUserRegisteredListener($messageDispatcher->reveal());

        $this->appContainer->has('Test.Listener.UserRegistered')->willReturn(true);
        $this->appContainer->get('Test.Listener.UserRegistered')->will(function ($args) use ($listener) {
            return $listener;
        });

        $this->eventEngine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        $this->eventEngine->bootstrap()->dispatch($registerUser);

        $this->assertNotNull($newCmdName);
        $this->assertNotNull($newCmdPayload);

        $this->assertEquals('SendWelcomeEmail', $newCmdName);
        $this->assertEquals(['email' => 'contact@prooph.de'], $newCmdPayload);
    }

    /**
     * @test
     */
    public function it_passes_registered_types_to_json_schema_assertion()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $this->eventEngine->registerType('UserState', JsonSchema::object([
            'id' => JsonSchema::string(['minLength' => 3]),
            'email' => JsonSchema::string(['format' => 'email']),
        ], [], true));

        $this->eventEngine->initialize($this->containerChain);

        $this->eventEngine->bootstrap();

        $visitorSchema = JsonSchema::object(['role' => JsonSchema::enum(['guest'])], [], true);

        $identifiedVisitorSchema = ['allOf' => [
            JsonSchema::typeRef('UserState')->toArray(),
            $visitorSchema,
        ]];

        $guest = ['id' => '123', 'role' => 'guest'];

        $this->eventEngine->jsonSchemaAssertion()->assert('Guest', $guest, $visitorSchema->toArray());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Validation of IdentifiedVisitor failed: \[email\] The property email is required/');

        $this->eventEngine->jsonSchemaAssertion()->assert('IdentifiedVisitor', $guest, $identifiedVisitorSchema);
    }

    /**
     * @test
     */
    public function it_registers_enum_type_as_type()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $colorSchema = new EnumType('red', 'blue', 'yellow');

        $this->eventEngine->registerEnumType('color', $colorSchema);

        $this->eventEngine->initialize($this->containerChain);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Validation of ball failed: \[color\] Does not have a value in the enumeration \["red","blue","yellow"\]/');

        $ballSchema = JsonSchema::object([
            'color' => JsonSchema::typeRef('color'),
        ])->toArray();

        $this->eventEngine->jsonSchemaAssertion()->assert('ball', ['color' => 'green'], $ballSchema);
    }

    /**
     * @test
     */
    public function it_uses_immutable_record_info_to_register_a_type()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $this->eventEngine->registerType(TestIdentityVO::class);

        $this->eventEngine->initialize($this->containerChain)->bootstrap(EventMachine::ENV_TEST, true);

        $userIdentityData = [
            'identity' => [
                'email' => 'test@test.local',
                'password' => 12345,
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Validation of UserIdentityData failed: \[identity.password\] Integer value found, but a string is required/');

        $this->eventEngine->jsonSchemaAssertion()->assert('UserIdentityData', $userIdentityData, JsonSchema::object([
            'identity' => JsonSchema::typeRef(TestIdentityVO::__type()),
        ])->toArray());
    }

    /**
     * @test
     */
    public function it_sets_app_version()
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $this->eventEngine->initialize($this->containerChain, '0.2.0');

        $this->eventEngine->bootstrap();

        $this->assertEquals('0.2.0', $this->eventEngine->appVersion());
    }

    /**
     * @test
     */
    public function it_dispatches_a_known_command_with_immediate_consistency(): void
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $documentStore = new DocumentStore\InMemoryDocumentStore($this->inMemoryConnection);

        $inMemoryEventStore = new InMemoryEventStore($this->inMemoryConnection);

        $eventStore = new ActionEventEmitterEventStore(
            $inMemoryEventStore,
            new ProophActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS)
        );

        $streamName = new StreamName('event_stream');

        $this->setUpAggregateProjector($documentStore, $eventStore, $streamName);

        $this->transactionManager = new TransactionManager($this->inMemoryConnection);
        $publishedEvents = [];

        $this->eventEngine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $event;
        });

        $this->eventEngine->setImmediateConsistency(true);
        $this->eventEngine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        $this->eventEngine->bootstrap()->dispatch($registerUser);

        $recordedEvents = $inMemoryEventStore->load($streamName);

        self::assertCount(1, $recordedEvents);
        self::assertCount(1, $publishedEvents);
        /** @var GenericJsonSchemaEvent $event */
        $event = $recordedEvents[0];
        $this->assertUserWasRegistered($event, $registerUser, $userId);

        $userState = $documentStore->getDoc(
            $this->getAggregateCollectionName(Aggregate::USER),
            $userId
        );

        $this->assertNotNull($userState);

        $this->assertEquals([
            'userId' => $userId,
            'username' => 'Alex',
            'email' => 'contact@prooph.de',
            'failed' => null,
        ], $userState);
    }

    /**
     * @test
     */
    public function it_rolls_back_events_and_projection_with_immediate_consistency(): void
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $documentStore = $this->prophesize(DocumentStore::class);
        $documentStore->hasCollection(Argument::type('string'))->willReturn(false);
        $documentStore->addCollection(Argument::type('string'))->shouldBeCalled();
        $documentStore
            ->upsertDoc(Argument::type('string'), Argument::type('string'), Argument::type('array'))
            ->willThrow(new \RuntimeException('projection error'));

        $inMemoryEventStore = new InMemoryEventStore($this->inMemoryConnection);

        $eventStore = new ActionEventEmitterEventStore(
            $inMemoryEventStore,
            new ProophActionEventEmitter(ActionEventEmitterEventStore::ALL_EVENTS)
        );

        $streamName = new StreamName('event_stream');

        $this->setUpAggregateProjector($documentStore->reveal(), $eventStore, $streamName);

        $this->transactionManager = new TransactionManager($this->inMemoryConnection);
        $publishedEvents = [];

        $this->eventEngine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $event;
        });

        $this->eventEngine->setImmediateConsistency(true);
        $this->eventEngine->initialize($this->containerChain);

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        $exceptionThrown = false;

        // more tests after exception needed
        try {
            $this->eventEngine->bootstrap()->dispatch($registerUser);
        } catch (TransactionCommitFailed $e) {
            $exceptionThrown = true;
        }
        $this->assertTrue($exceptionThrown);
        $this->assertEmpty(\iterator_to_array($eventStore->load($streamName)));
    }

    /**
     * @test
     */
    public function it_switches_action_event_emitter_with_immediate_consistency(): void
    {
        $this->markTestSkipped("Reactivate Test");
        return;

        $documentStore = new DocumentStore\InMemoryDocumentStore($this->inMemoryConnection);

        $inMemoryEventStore = new InMemoryEventStore($this->inMemoryConnection);

        //A TransactionalActionEventEmitterEventStore conflicts with the immediate consistency mode
        //because the EventPublisher listens on commit event, but it never happens due to transaction managed
        //outside of the event store
        //Event Machine needs to take care of it
        $eventStore = new TransactionalActionEventEmitterEventStore(
            $inMemoryEventStore,
            new ProophActionEventEmitter(TransactionalActionEventEmitterEventStore::ALL_EVENTS)
        );

        $streamName = new StreamName('event_stream');

        $this->setUpAggregateProjector($documentStore, $eventStore, $streamName);

        $this->transactionManager = new TransactionManager($this->inMemoryConnection);
        $publishedEvents = [];

        $this->eventEngine->setImmediateConsistency(true);
        $this->eventEngine->initialize($this->containerChain);

        $this->expectException(RuntimeException::class);

        $this->eventEngine->bootstrap();
    }

    private function assertUserWasRegistered(
        Message $event,
        Message $registerUser,
        string $userId
    ): void {
        self::assertEquals(Event::USER_WAS_REGISTERED, $event->messageName());
        self::assertEquals([
            '_causation_id' => $registerUser->uuid()->toString(),
            '_causation_name' => $registerUser->messageName(),
            '_aggregate_version' => 1,
            '_aggregate_id' => $userId,
            '_aggregate_type' => 'User',
        ], $event->metadata());
    }

    private function getAggregateCollectionName(string $aggregate): string
    {
        return AggregateProjector::aggregateCollectionName(
            '0.1.0',
            $aggregate
        );
    }

    /**
     * @param $event
     * @return Message
     * @throws \Exception
     */
    private function convertToEventMachineMessage($event): Message
    {
        $flavour = $this->getFlavour();
        if ($flavour instanceof MessageFactoryAware) {
            $flavour->setMessageFactory($this->eventEngine->messageFactory());
        }

        switch (\get_class($event)) {
            case UserRegistered::class:
                return $flavour->prepareNetworkTransmission(new MessageBag(
                    Event::USER_WAS_REGISTERED,
                    Message::TYPE_EVENT,
                    $event
                ));
            case UsernameChanged::class:
                return $flavour->prepareNetworkTransmission(new MessageBag(
                    Event::USERNAME_WAS_CHANGED,
                    Message::TYPE_EVENT,
                    $event
                ));
            default:
                return $event;
        }
    }
}
