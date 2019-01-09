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
use EventEngine\DocumentStore\Exception\UnknownCollection;
use EventEngine\DocumentStore\InMemoryDocumentStore;
use EventEngine\EventEngine;
use EventEngine\EventStore\EventStore;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\JustinRainbowJsonSchema;
use EventEngine\JsonSchema\Type\EmailType;
use EventEngine\JsonSchema\Type\EnumType;
use EventEngine\JsonSchema\Type\StringType;
use EventEngine\JsonSchema\Type\UuidType;
use EventEngine\Logger\DevNull;
use EventEngine\Logger\LogEngine;
use EventEngine\Logger\SimpleMessageEngine;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageBag;
use EventEngine\Messaging\MessageDispatcher;
use EventEngine\Messaging\MessageFactoryAware;
use EventEngine\Messaging\MessageProducer;
use EventEngine\Persistence\InMemoryConnection;
use EventEngine\Persistence\Stream;
use EventEngine\Process\Pid;
use EventEngine\Process\ProcessType;
use EventEngine\Projecting\ProcessStateProjector;
use EventEngine\Prooph\V7\EventStore\InMemoryEventStore;
use EventEngine\Prooph\V7\EventStore\InMemoryMultiModelStore;
use EventEngine\Prooph\V7\EventStore\ProophEventStore;
use EventEngine\Runtime\Flavour;
use EventEngineExample\FunctionalFlavour\Api\Query;
use EventEngineExample\FunctionalFlavour\Event\UsernameChanged;
use EventEngineExample\FunctionalFlavour\Event\UserRegistered;
use EventEngineExample\PrototypingFlavour\Process\Process;
use EventEngineExample\PrototypingFlavour\Process\UserDescription;
use EventEngineExample\PrototypingFlavour\Messaging\Command;
use EventEngineExample\PrototypingFlavour\Messaging\Event;
use EventEngineTest\Stubs\TestIdentityVO;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Ramsey\Uuid\Uuid;

abstract class EventEngineTestAbstract extends BasicTestCase
{
    abstract protected function loadEventMachineDescriptions(EventEngine $eventEngine);

    abstract protected function getFlavour(): Flavour;

    abstract protected function getRegisteredUsersProjector(DocumentStore $documentStore);

    abstract protected function getUserRegisteredListener(MessageDispatcher $messageDispatcher);

    abstract protected function getUserResolver(array $cachedUserState);

    abstract protected function getUsersResolver(array $cachedUsers);

    abstract protected function assertLoadedUserState($userState): void;

    /**
     * @var EventEngine
     */
    protected $eventEngine;

    /**
     * @var EventStore
     */
    private $eventStore;

    /**
     * @var ObjectProphecy
     */
    protected $appContainer;

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

        $this->eventStore = new ProophEventStore(new InMemoryEventStore($this->inMemoryConnection), true);

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

    protected function setUpProcessProjector(
        DocumentStore $documentStore
    ): void {
        $processStateProjector = new ProcessStateProjector($documentStore, $this->eventEngine, true);

        $this->appContainer->has(ProcessStateProjector::class)->willReturn(true);
        $this->appContainer->get(ProcessStateProjector::class)->will(function ($args) use ($processStateProjector) {
            return $processStateProjector;
        });

        $this->eventEngine->watch(Stream::ofWriteModel())
            ->withProcessProjection(Process::USER);
    }

    protected function setUpRegisteredUsersProjector(
        DocumentStore $documentStore
    ): void {
        $projector = $this->getRegisteredUsersProjector($documentStore);

        $this->appContainer->has('Test.Projector.RegisteredUsers')->willReturn(true);
        $this->appContainer->get('Test.Projector.RegisteredUsers')->will(function ($args) use ($projector) {
            return $projector;
        });

        $this->eventEngine->watch(Stream::ofWriteModel())
            ->with('registered_users', 'Test.Projector.RegisteredUsers')
            ->filterEvents([
                \EventEngineExample\PrototypingFlavour\Messaging\Event::USER_WAS_REGISTERED,
            ]);
    }

    protected function initializeEventEngine(
        LogEngine $logEngine = null,
        DocumentStore $documentStore = null,
        MessageProducer $eventQueue = null,
        bool $autoProjecting = false): void {
        if(!$logEngine) {
            $logEngine = new SimpleMessageEngine(new DevNull());
        }

        if(!$autoProjecting) {
            $this->eventEngine->disableAutoProjecting();
        }

        $this->eventEngine->initialize(
            $this->flavour,
            $this->eventStore,
            $logEngine,
            $this->appContainer->reveal(),
            $documentStore,
            $eventQueue
        );
    }

    protected function bootstrapEventEngine(): void
    {
        $this->eventEngine->bootstrap(EventEngine::ENV_TEST, true);
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

        $this->initializeEventEngine();
        $this->bootstrapEventEngine();

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        $result = $this->eventEngine->dispatch($registerUser);

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
        $userId = Uuid::uuid4()->toString();

        $getUserResolver = $this->getUserResolver([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
        ]);

        $this->appContainer->get(\get_class($getUserResolver))->willReturn($getUserResolver);

        $this->initializeEventEngine();
        $this->bootstrapEventEngine();


        $getUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Query::GET_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
            ]]
        );

        $userData = $this->eventEngine->dispatch($getUser);

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
        $getUsersResolver = $this->getUsersResolver([
            [
                UserDescription::IDENTIFIER => '123',
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ],
        ]);

        $this->appContainer->get(\get_class($getUsersResolver))->willReturn($getUsersResolver);

        $this->initializeEventEngine();
        $this->bootstrapEventEngine();

        $getUsers = $this->eventEngine->messageFactory()->createMessageFromArray(
            Query::GET_USERS,
            ['payload' => []]
        );

        $userList = $this->eventEngine->dispatch($getUsers);

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
        $publishedEvents = [];

        $this->eventEngine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $this->convertToEventMachineMessage($event);
        });

        $this->initializeEventEngine();
        $this->bootstrapEventEngine();

        $userId = Uuid::uuid4()->toString();

        $result = $this->eventEngine->dispatch(Command::REGISTER_USER, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
            UserDescription::EMAIL => 'contact@prooph.de',
        ]);

        $recordedEvents = $result->recordedEvents();

        self::assertCount(1, $recordedEvents);
        self::assertCount(1, $publishedEvents);
        $event = $recordedEvents[0];
        self::assertEquals(Event::USER_WAS_REGISTERED, $event->messageName());
    }

    /**
     * @test
     */
    public function it_can_handle_command_for_existing_process()
    {

        $publishedEvents = [];

        $this->eventEngine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $this->convertToEventMachineMessage($event);
        });

        $this->eventEngine->on(Event::USERNAME_WAS_CHANGED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $this->convertToEventMachineMessage($event);
        });

        $this->initializeEventEngine();
        $this->bootstrapEventEngine();

        $userId = Uuid::uuid4()->toString();

        $firstResult = $this->eventEngine->dispatch(Command::REGISTER_USER, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'Alex',
            UserDescription::EMAIL => 'contact@prooph.de',
        ]);

        $secondResult = $this->eventEngine->dispatch(Command::CHANGE_USERNAME, [
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => 'John',
        ]);

        $recordedEvents = array_merge($firstResult->recordedEvents(), $secondResult->recordedEvents());

        self::assertCount(2, $recordedEvents);
        self::assertCount(2, $publishedEvents);
        /** @var GenericEvent $event */
        $event = $recordedEvents[1];
        self::assertEquals(Event::USERNAME_WAS_CHANGED, $event->messageName());
    }

    /**
     * @test
     */
    public function it_uses_event_queue()
    {
        $producedEvents = [];

        $eventEngine = $this->eventEngine;

        $messageProducer = $this->prophesize(MessageProducer::class);
        $messageProducer->produce(Argument::type(Message::class))
            ->will(function ($args) use (&$producedEvents, $eventEngine) {
                $producedEvents[] = $args[0];
                return $eventEngine->dispatch($args[0]);
            });


        $publishedEvents = [];

        $this->eventEngine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $this->convertToEventMachineMessage($event);
        });

        $this->initializeEventEngine(null, null, $messageProducer->reveal());
        $this->bootstrapEventEngine();

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        $result = $this->eventEngine->dispatch($registerUser);

        $recordedEvents = $result->recordedEvents();

        self::assertCount(1, $recordedEvents);
        self::assertCount(1, $publishedEvents);
        self::assertCount(1, $producedEvents);
        $event = $recordedEvents[0];

        $this->assertUserWasRegistered($event, $registerUser, $userId);

        self::assertEquals(Event::USER_WAS_REGISTERED, $producedEvents[0]->messageName());
        self::assertEquals(Event::USER_WAS_REGISTERED, $publishedEvents[0]->messageName());
    }

    /**
     * @test
     */
    public function it_can_load_process_state()
    {

        $this->initializeEventEngine();
        $this->bootstrapEventEngine();

        $userId = Uuid::uuid4()->toString();

        $this->eventStore->appendTo($this->eventEngine->writeModelStreamName(),
            GenericEvent::fromMessage($this->flavour->prepareNetworkTransmission(
                $this->eventEngine->messageFactory()->createMessageFromArray(Event::USER_WAS_REGISTERED, [
                    'payload' => [
                        'userId' => $userId,
                        'username' => 'Tester',
                        'email' => 'tester@test.com',
                    ],
                    'metadata' => [
                        GenericEvent::META_PROCESS_ID => $userId,
                        GenericEvent::META_PROCESS_TYPE => Process::USER,
                        GenericEvent::META_PROCESS_VERSION => 1,
                    ],
                ])
            ))
        );

        $userState = $this->eventEngine->loadProcessState(ProcessType::fromString(Process::USER), Pid::fromString($userId));

        $this->assertLoadedUserState($userState);
    }

    /**
     * @test
     */
    public function it_builds_a_message_box_schema(): void
    {
        $this->initializeEventEngine();
        $this->bootstrapEventEngine();


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
            'title' => 'Event Engine MessageBox',
            'description' => 'A mechanism for handling Event Engine messages',
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
        $documentStore = new InMemoryDocumentStore($this->inMemoryConnection);

        $this->setUpProcessProjector($documentStore);

        $this->initializeEventEngine();
        $this->bootstrapEventEngine();

        $this->eventEngine->setUpAllProjections();

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        $result = $this->eventEngine->dispatch($registerUser);

        $this->eventEngine->runAllProjections($this->eventEngine->writeModelStreamName(), ...$result->recordedEvents());

        $userState = $documentStore->getDoc(
            $this->getProcessStateCollectionName(Process::USER),
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
        $documentStore = new InMemoryDocumentStore($this->inMemoryConnection);

        $this->setUpRegisteredUsersProjector($documentStore);

        $this->initializeEventEngine();
        $this->bootstrapEventEngine();

        $this->eventEngine->setUpAllProjections();

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        $result = $this->eventEngine->dispatch($registerUser);

        $this->eventEngine->runAllProjections($this->eventEngine->writeModelStreamName(), ...$result->recordedEvents());

        //We expect RegisteredUsersProjector to use collection naming convention: <projection_name>_<projection_version>
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
        $messageDispatcher = $this->prophesize(MessageDispatcher::class);

        $newCmdName = null;
        $newCmdPayload = null;
        $messageDispatcher->dispatch(Argument::any(), Argument::any())->will(function ($args) use (&$newCmdName, &$newCmdPayload) {
            $newCmdName = $args[0] ?? null;
            $newCmdPayload = $args[1] ?? null;
            return null;
        });

        $this->eventEngine->on(Event::USER_WAS_REGISTERED, 'Test.Listener.UserRegistered');

        $listener = $this->getUserRegisteredListener($messageDispatcher->reveal());

        $this->appContainer->has('Test.Listener.UserRegistered')->willReturn(true);
        $this->appContainer->get('Test.Listener.UserRegistered')->will(function ($args) use ($listener) {
            return $listener;
        });

        $this->initializeEventEngine();
        $this->bootstrapEventEngine();

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        $this->eventEngine->dispatch($registerUser);

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
        $this->eventEngine->registerType('UserState', JsonSchema::object([
            'id' => JsonSchema::string(['minLength' => 3]),
            'email' => JsonSchema::string(['format' => 'email']),
        ], [], true));

        $visitorSchema = JsonSchema::object(['role' => JsonSchema::enum(['guest'])], [], true);

        $identifiedVisitorSchema = JsonSchema::implementTypes(
            $visitorSchema,
            'UserState'
        );

        $this->eventEngine->registerCommand('Guest', $visitorSchema);
        $this->eventEngine->registerCommand('IdentifiedVisitor', $identifiedVisitorSchema);

        $this->initializeEventEngine();
        $this->bootstrapEventEngine();

        $guest = ['id' => '123', 'role' => 'guest'];

        $this->eventEngine->messageFactory()->createMessageFromArray('Guest', ['payload' => $guest]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Validation of IdentifiedVisitor payload failed: \[email\] The property email is required/');

        $this->eventEngine->messageFactory()->createMessageFromArray('IdentifiedVisitor', ['payload' => $guest]);
    }

    /**
     * @test
     */
    public function it_registers_enum_type_as_type()
    {
        $colorSchema = new EnumType('red', 'blue', 'yellow');

        $this->eventEngine->registerType('color', $colorSchema);

        $ballSchema = JsonSchema::object([
            'color' => JsonSchema::typeRef('color'),
        ]);

        $this->eventEngine->registerCommand('ball', $ballSchema);

        $this->initializeEventEngine();
        $this->bootstrapEventEngine();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Validation of ball payload failed: \[color\] Does not have a value in the enumeration \["red","blue","yellow"\]/');

        $this->eventEngine->messageFactory()->createMessageFromArray('ball', ['payload' => ['color' => 'green']]);
    }

    /**
     * @test
     */
    public function it_uses_immutable_record_info_to_register_a_type()
    {
        $this->eventEngine->registerType(TestIdentityVO::class);

        $this->eventEngine->registerCommand('AddIdentity', JsonSchema::object([
            'identity' => JsonSchema::typeRef(TestIdentityVO::class)
        ]));

        $this->initializeEventEngine();
        $this->bootstrapEventEngine();

        $userIdentityData = [
            'identity' => [
                'email' => 'test@test.local',
                'password' => 12345,
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageRegExp('/Validation of AddIdentity payload failed: \[identity.password\] Integer value found, but a string is required/');

        $this->eventEngine->messageFactory()->createMessageFromArray('AddIdentity', ['payload' => $userIdentityData]);
    }

    /**
     * @test
     */
    public function it_dispatches_a_known_command_with_process_state_consistency(): void
    {
        $this->eventStore = InMemoryMultiModelStore::fromConnection($this->inMemoryConnection);

        $this->eventStore->addCollection($this->getProcessStateCollectionName(Process::USER));

        $publishedEvents = [];

        $this->eventEngine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $event;
        });

        $this->initializeEventEngine();
        $this->bootstrapEventEngine();

        $userId = Uuid::uuid4()->toString();

        $registerUser = $this->eventEngine->messageFactory()->createMessageFromArray(
            Command::REGISTER_USER,
            ['payload' => [
                UserDescription::IDENTIFIER => $userId,
                UserDescription::USERNAME => 'Alex',
                UserDescription::EMAIL => 'contact@prooph.de',
            ]]
        );

        $result = $this->eventEngine->dispatch($registerUser);


        $recordedEvents = iterator_to_array($this->eventStore->loadProcessEvents($this->eventEngine->writeModelStreamName(), ProcessType::fromString(Process::USER), Pid::fromString($userId)));

        self::assertCount(1, $recordedEvents);
        self::assertCount(1, $publishedEvents);
        $event = $recordedEvents[0];
        $this->assertUserWasRegistered($event, $registerUser, $userId);

        $userState = $this->eventStore->getDoc(
            $this->getProcessStateCollectionName(Process::USER),
            $userId
        );

        $this->assertNotNull($userState);

        $this->assertEquals([
            'userId' => $userId,
            'username' => 'Alex',
            'email' => 'contact@prooph.de',
            'failed' => null,
        ], $userState['state']);
    }

    /**
     * @test
     */
    public function it_rolls_back_events_with_process_state_consistency(): void
    {
        $this->eventStore = InMemoryMultiModelStore::fromConnection($this->inMemoryConnection);

        $publishedEvents = [];

        $this->eventEngine->on(Event::USER_WAS_REGISTERED, function ($event) use (&$publishedEvents) {
            $publishedEvents[] = $event;
        });

        $this->initializeEventEngine();
        $this->bootstrapEventEngine();

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

        try {
            $this->eventEngine->dispatch($registerUser);
        } catch (UnknownCollection $e) {
            $exceptionThrown = true;
        }
        $this->assertTrue($exceptionThrown);
        $this->assertEmpty(\iterator_to_array($this->eventStore->loadProcessEvents(
            $this->eventEngine->writeModelStreamName(),
            ProcessType::fromString(Process::USER),
            Pid::fromString($userId)
        )));
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
            GenericEvent::META_PROCESS_VERSION => 1,
            GenericEvent::META_PROCESS_ID => $userId,
            GenericEvent::META_PROCESS_TYPE => 'User',
        ], $event->metadata());
    }

    private function getProcessStateCollectionName(string $process): string
    {
        return ProcessStateProjector::processStateCollectionName(
            '0.1.0',
            ProcessType::fromString($process)
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
