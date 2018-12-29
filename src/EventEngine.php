<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine;

use EventEngine\Aggregate\Exception\AggregateNotFound;
use EventEngine\Aggregate\FlavouredAggregateRoot;
use EventEngine\Aggregate\GenericAggregateRepository;
use EventEngine\Commanding\CommandDispatch;
use EventEngine\Commanding\CommandPreProcessor;
use EventEngine\Commanding\CommandProcessorDescription;
use EventEngine\Data\ImmutableRecord;
use EventEngine\DocumentStore\DocumentStore;
use EventEngine\EventStore\EventStore;
use EventEngine\Exception\BadMethodCallException;
use EventEngine\Exception\InvalidArgumentException;
use EventEngine\Exception\RuntimeException;
use EventEngine\Messaging\GenericSchemaMessageFactory;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageDispatcher;
use EventEngine\Messaging\MessageFactory;
use EventEngine\Messaging\MessageFactoryAware;
use EventEngine\Messaging\MessageProducer;
use EventEngine\Persistence\AggregateStateStore;
use EventEngine\Persistence\Stream;
use EventEngine\Projecting\AggregateProjector;
use EventEngine\Projecting\ProjectionDescription;
use EventEngine\Querying\QueryDescription;
use EventEngine\Runtime\Flavour;
use EventEngine\Schema\InputTypeSchema;
use EventEngine\Schema\MessageSchema;
use EventEngine\Schema\PayloadSchema;
use EventEngine\Schema\ResponseTypeSchema;
use EventEngine\Schema\Schema;
use EventEngine\Schema\TypeSchemaMap;
use EventEngine\Util\VariableType;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class EventEngine implements MessageDispatcher, MessageProducer, AggregateStateStore
{
    public const ENV_PROD = 'prod';
    public const ENV_DEV = 'dev';
    public const ENV_TEST = 'test';

    /**
     * Map of command message schemas indexed by message name
     *
     * @var MessageSchema[]
     */
    private $commandMap = [];

    /**
     * Map of command names and corresponding list of preprocessors given as container service id
     *
     * @var string[]
     */
    private $commandPreProcessors = [];

    /**
     * @var array
     */
    private $commandRouting = [];

    /**
     * @var array
     */
    private $compiledCommandRouting;

    /**
     * @var array
     */
    private $aggregateDescriptions;

    /**
     * Map of event message schemas indexed by event message name
     *
     * @var MessageSchema[]
     */
    private $eventMap = [];

    /**
     * Map of event names and corresponding list of listeners given as container service id
     *
     * @var string[]
     */
    private $eventRouting = [];

    /**
     * Map of projection names and corresponding projection descriptions
     *
     * @var ProjectionDescription[] indexed by projection name
     */
    private $projectionMap = [];

    /**
     * @var QueryDescription[string] list of QueryDescription indexed by query name
     */
    private $queryDescriptions = [];

    /**
     * @var array list of compiled query descriptions indexed by query name
     */
    private $compiledQueryDescriptions = [];

    /**
     * Map of query message schemas indexed by message name
     *
     * @var MessageSchema[]
     */
    private $queryMap = [];

    /**
     * Map of response type schemas indexed by type name
     *
     * @var ResponseTypeSchema[]
     */
    private $responseTypes = [];

    /**
     * Map of input type schemas indexed by type name
     *
     * @var InputTypeSchema[]
     */
    private $inputTypes = [];

    private $typeSchemaMap;

    /**
     * @var array
     */
    private $compiledProjectionDescriptions = [];

    private $initialized = false;

    private $bootstrapped = false;

    private $debugMode = false;

    private $env = self::ENV_PROD;

    private $testMode = false;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Flavour
     */
    private $flavour;

    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var Schema
     */
    private $schema;

    private $writeModelStreamName = 'event_stream';

    /**
     * @var EventStore
     */
    private $eventStore;

    /**
     * @var DocumentStore|null
     */
    private $documentStore;

    /**
     * @var MessageProducer|null
     */
    private $eventQueue;

    public function __construct(Schema $schema)
    {
        $this->schema = $schema;
        $this->typeSchemaMap = new TypeSchemaMap();
    }

    public static function fromCachedConfig(
        array $config,
        Schema $schema,
        Flavour $flavour,
        EventStore $eventStore,
        LoggerInterface $logger,
        ContainerInterface $container,
        DocumentStore $documentStore = null,
        MessageProducer $eventQueue = null
    ): self {
        $self = new self($schema);

        if (! \array_key_exists('commandMap', $config)) {
            throw new InvalidArgumentException('Missing key commandMap in cached event engine config');
        }

        if (! \array_key_exists('eventMap', $config)) {
            throw new InvalidArgumentException('Missing key eventMap in cached event engine config');
        }

        if (! \array_key_exists('compiledCommandRouting', $config)) {
            throw new InvalidArgumentException('Missing key compiledCommandRouting in cached event engine config');
        }

        if (! \array_key_exists('aggregateDescriptions', $config)) {
            throw new InvalidArgumentException('Missing key aggregateDescriptions in cached event engine config');
        }

        if (! \array_key_exists('appVersion', $config)) {
            throw new InvalidArgumentException('Missing key appVersion in cached event engine config');
        }

        if (! \array_key_exists('writeModelStreamName', $config)) {
            throw new InvalidArgumentException('Missing key writeModelStreamName in cached event engine config');
        }

        $mapPayloadSchema = function (array $payloadSchema) use ($schema): PayloadSchema {
            return $schema->buildPayloadSchemaFromArray($payloadSchema);
        };

        $self->commandMap = array_map($mapPayloadSchema, $config['commandMap']);
        $self->eventMap = array_map($mapPayloadSchema, $config['eventMap']);
        $self->compiledCommandRouting = $config['compiledCommandRouting'];
        $self->aggregateDescriptions = $config['aggregateDescriptions'];
        $self->eventRouting = $config['eventRouting'] ?? [];
        $self->compiledProjectionDescriptions = $config['compiledProjectionDescriptions'] ?? [];
        $self->compiledQueryDescriptions = $config['compiledQueryDescriptions'];
        $self->queryMap = array_map($mapPayloadSchema, $config['queryMap'] ?? []);
        $self->responseTypes = array_map(function (array $typeSchema, string $typeName) use ($schema): ResponseTypeSchema {
            return $schema->buildResponseTypeSchemaFromArray($typeName, $typeSchema);
        }, $config['responseTypes'] ?? []);
        $self->inputTypes = array_map(function (array $typeSchema, string $typeName) use ($schema): ResponseTypeSchema {
            return $schema->buildResponseTypeSchemaFromArray($typeName, $typeSchema);
        }, $config['inputTypes'] ?? []);
        $self->writeModelStreamName = $config['writeModelStreamName'];

        $self->flavour = $flavour;
        $self->eventStore = $eventStore;
        $self->logger = $logger;
        $self->container = $container;
        $self->documentStore = $documentStore;
        $self->eventQueue = $eventQueue;

        foreach ($self->responseTypes as $typeName => $responseType) {
            $self->typeSchemaMap->add($typeName, $responseType);
        }

        foreach ($self->inputTypes as $typeName => $inputType) {
            $self->typeSchemaMap->add($typeName, $inputType);
        }

        $self->initialized = true;

        return $self;
    }

    public function load(string $description): void
    {
        $this->assertNotInitialized(__METHOD__);
        \call_user_func([$description, 'describe'], $this);
    }

    public function setWriteModelStreamName(string $streamName): self
    {
        $this->assertNotInitialized(__METHOD__);
        $this->writeModelStreamName = $streamName;

        return $this;
    }

    public function registerCommand(string $commandName, PayloadSchema $schema): self
    {
        $this->assertNotInitialized(__METHOD__);
        if (\array_key_exists($commandName, $this->commandMap)) {
            throw new RuntimeException("Command $commandName was already registered.");
        }

        $this->commandMap[$commandName] = $schema;

        return $this;
    }

    public function registerEvent(string $eventName, PayloadSchema $schema): self
    {
        $this->assertNotInitialized(__METHOD__);

        if (\array_key_exists($eventName, $this->eventMap)) {
            throw new RuntimeException("Event $eventName was already registered.");
        }

        $this->schema->assertPayloadSchema($eventName, $schema);
        $this->eventMap[$eventName] = $schema;

        return $this;
    }

    public function registerQuery(string $queryName, PayloadSchema $payloadSchema = null): QueryDescription
    {
        $this->assertNotInitialized(__METHOD__);

        if ($payloadSchema) {
            $this->schema()->assertPayloadSchema($queryName, $payloadSchema);
        } else {
            $payloadSchema = $this->schema()->emptyPayloadSchema();
        }

        if ($this->isKnownQuery($queryName)) {
            throw new RuntimeException("Query $queryName was already registered");
        }

        $this->queryMap[$queryName] = $payloadSchema;
        $queryDesc = new QueryDescription($queryName, $this);
        $this->queryDescriptions[$queryName] = $queryDesc;

        return $queryDesc;
    }

    public function registerProjection(string $projectionName, ProjectionDescription $projectionDescription): void
    {
        $this->assertNotInitialized(__METHOD__);

        if ($this->isKnownProjection($projectionName)) {
            throw new RuntimeException("Projection with name $projectionName is already registered.");
        }
        $this->projectionMap[$projectionName] = $projectionDescription;
    }

    public function registerType(string $nameOrImmutableRecordClass, ResponseTypeSchema $schema = null): void
    {
        $this->registerResponseType($nameOrImmutableRecordClass, $schema);
    }

    public function registerResponseType(string $nameOrImmutableRecordClass, ResponseTypeSchema $schema = null): void
    {
        $this->assertNotInitialized(__METHOD__);

        if (null === $schema) {
            $refObj = new \ReflectionClass($nameOrImmutableRecordClass);

            if (! $refObj->implementsInterface(ImmutableRecord::class)) {
                throw new InvalidArgumentException("Invalid type given. $nameOrImmutableRecordClass does not implement " . ImmutableRecord::class);
            }

            $name = \call_user_func([$nameOrImmutableRecordClass, '__type']);
            $schema = \call_user_func([$nameOrImmutableRecordClass, '__schema']);
        } else {
            $name = $nameOrImmutableRecordClass;
        }

        if ($this->isKnownType($name)) {
            throw new RuntimeException("Type $name is already registered");
        }

        $this->schema()->assertResponseTypeSchema($name, $schema);

        $this->responseTypes[$name] = $schema;
        $this->typeSchemaMap->add($name, $schema);
    }

    public function registerInputType(string $nameOrImmutableRecordClass, InputTypeSchema $schema = null): void
    {
        $this->assertNotInitialized(__METHOD__);

        if (null === $schema) {
            $refObj = new \ReflectionClass($nameOrImmutableRecordClass);

            if (! $refObj->implementsInterface(ImmutableRecord::class)) {
                throw new InvalidArgumentException("Invalid type given. $nameOrImmutableRecordClass does not implement " . ImmutableRecord::class);
            }

            $name = \call_user_func([$nameOrImmutableRecordClass, '__type']);
            $schema = \call_user_func([$nameOrImmutableRecordClass, '__schema']);
        } else {
            $name = $nameOrImmutableRecordClass;
        }

        if ($this->isKnownType($name)) {
            throw new RuntimeException("Type $name is already registered");
        }

        $this->schema()->assertInputTypeSchema($name, $schema);

        $this->inputTypes[$name] = $schema;
        $this->typeSchemaMap->add($name, $schema);
    }

    public function preProcess(string $commandName, $preProcessor): self
    {
        $this->assertNotInitialized(__METHOD__);

        if (! $this->isKnownCommand($commandName)) {
            throw new InvalidArgumentException("Preprocessor attached to unknown command $commandName. You should register the command first");
        }

        if (! \is_string($preProcessor) && ! $preProcessor instanceof CommandPreProcessor) {
            throw new InvalidArgumentException('PreProcessor should either be a service id given as string or an instance of '.CommandPreProcessor::class.'. Got '
                . VariableType::determine($preProcessor));
        }

        $this->commandPreProcessors[$commandName][] = $preProcessor;

        return $this;
    }

    public function process(string $commandName): CommandProcessorDescription
    {
        $this->assertNotInitialized(__METHOD__);
        if (\array_key_exists($commandName, $this->commandRouting)) {
            throw new \BadMethodCallException('Method process was called twice for the same command: ' . $commandName);
        }

        if (! \array_key_exists($commandName, $this->commandMap)) {
            throw new \BadMethodCallException("Command $commandName is unknown. You should register it first.");
        }

        $this->commandRouting[$commandName] = new CommandProcessorDescription($commandName, $this);

        return $this->commandRouting[$commandName];
    }

    public function on(string $eventName, $listener): self
    {
        $this->assertNotInitialized(__METHOD__);

        if (! $this->isKnownEvent($eventName)) {
            throw new InvalidArgumentException("Listener attached to unknown event $eventName. You should register the event first");
        }

        if (! \is_string($listener) && ! \is_callable($listener)) {
            throw new InvalidArgumentException('Listener should be either a service id given as string or a callable. Got '
                . (\is_object($listener) ? \get_class($listener) : \gettype($listener)));
        }

        $this->eventRouting[$eventName][] = $listener;

        return $this;
    }

    public function watch(Stream $stream): ProjectionDescription
    {
        if ($stream->streamName() === Stream::WRITE_MODEL_STREAM) {
            $stream = $stream->withStreamName($this->writeModelStreamName);
        }
        //ProjectionDescriptions register itself using EventMachine::registerProjection within ProjectionDescription::with call
        return new ProjectionDescription($stream, $this);
    }

    public function isKnownCommand(string $commandName): bool
    {
        return \array_key_exists($commandName, $this->commandMap);
    }

    public function isKnownEvent(string $eventName): bool
    {
        return \array_key_exists($eventName, $this->eventMap);
    }

    public function isKnownQuery(string $queryName): bool
    {
        return \array_key_exists($queryName, $this->queryMap);
    }

    public function isKnownProjection(string $projectionName): bool
    {
        return \array_key_exists($projectionName, $this->projectionMap);
    }

    public function isKnownType(string $typeName): bool
    {
        return $this->typeSchemaMap->contains($typeName);
    }

    public function isTestMode(): bool
    {
        return $this->testMode;
    }

    public function schema(): Schema
    {
        return $this->schema;
    }

    public function writeModelStreamName(): string
    {
        return $this->writeModelStreamName;
    }

    public function messageFactory(): MessageFactory
    {
        $this->assertBootstrapped(__METHOD__);
        return $this->messageFactory;
    }

    public function flavour(): Flavour
    {
        return $this->flavour;
    }

    public function initialize(
        Flavour $flavour,
        EventStore $eventStore,
        LoggerInterface $logger,
        ContainerInterface $container,
        DocumentStore $documentStore = null,
        MessageProducer $eventQueue = null
    ): self {
        $this->assertNotInitialized(__METHOD__);

        $this->compileAggregateAndRoutingDescriptions();
        $this->compileProjectionDescriptions();
        $this->compileQueryDescriptions();

        $this->flavour = $flavour;
        $this->eventStore = $eventStore;
        $this->logger = $logger;
        $this->container = $container;
        $this->documentStore = $documentStore;
        $this->eventQueue = $eventQueue;

        $this->initialized = true;

        return $this;
    }

    public function bootstrap(string $env = self::ENV_PROD, $debugMode = false): self
    {
        $envModes = [self::ENV_PROD, self::ENV_DEV, self::ENV_TEST];
        if (! \in_array($env, $envModes)) {
            throw new InvalidArgumentException("Invalid env. Got $env but expected is one of " . \implode(', ', $envModes));
        }
        $this->assertInitialized(__METHOD__);
        $this->assertNotBootstrapped(__METHOD__);

        $this->messageFactory = new GenericSchemaMessageFactory(
            $this->schema,
            $this->commandMap,
            $this->eventMap,
            $this->queryMap,
            $this->typeSchemaMap
        );

        if($this->flavour instanceof MessageFactoryAware) {
            $this->flavour->setMessageFactory($this->messageFactory);
        }

        $this->bootstrapped = true;
        $this->debugMode = $debugMode;
        $this->env = $env;

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws \Throwable
     */
    public function dispatch($messageOrName, array $payload = []): \Generator
    {
        $this->assertBootstrapped(__METHOD__);

        if (\is_string($messageOrName)) {
            $messageOrName = $this->messageFactory()->createMessageFromArray($messageOrName, ['payload' => $payload]);
        }

        if (! $messageOrName instanceof Message) {
            throw new InvalidArgumentException(
                'Invalid message received. Must be either a known message name or an instance of EventEngine\Messaging\Message. Got '
                . VariableType::determine($messageOrName)
            );
        }

        switch ($messageOrName->messageType()) {
            case Message::TYPE_COMMAND:
                $processorDesc = $this->compiledCommandRouting[$messageOrName->messageName()] ?? null;

                if(!$processorDesc) {
                    throw new RuntimeException("No routing information found for command {$messageOrName->messageName()}");
                }

                $container = $this->container;

                $preProcessors = array_map(function ($preProcessor) use ($container) {
                    if (\is_string($preProcessor)) {
                        $preProcessor = $this->container->get($preProcessor);
                    }
                    return $preProcessor;
                }, $this->commandPreProcessors[$messageOrName->messageName()] ?? []);

                yield CommandDispatch::exec(
                    $messageOrName,
                    $this->flavour,
                    $this->eventStore,
                    $preProcessors,
                    $processorDesc,
                    $this->aggregateDescriptions,
                    $this->eventQueue ?? $this,
                    $this->documentStore,
                    $processorDesc['contextProvider'] ? $container->get($processorDesc['contextProvider']) : null
                );
                break;
            case Message::TYPE_EVENT:
                $listeners = $this->eventRouting[$messageOrName->messageName()] ?? [];

                foreach ($listeners as $listener) {
                    if(\is_string($listener)) {
                        $listener = $this->container->get($listener);
                    }

                    $this->flavour->callEventListener($listener, $messageOrName);
                }

                yield null;
                break;
            case Message::TYPE_QUERY:
                $queryDesc = $this->compiledQueryDescriptions[$messageOrName->messageName()] ?? null;

                if(!$queryDesc) {
                    throw new RuntimeException("No routing information found for query {$messageOrName->messageName()}");
                }

                $resolver = $this->container->get($queryDesc['resolver'] ?? null);

                yield from $this->flavour->callQueryResolver($resolver, $messageOrName);
                break;
            default:
                throw new RuntimeException('Unsupported message type: ' . $messageOrName->messageType());
        }
    }

    public function produce(Message $message): \Generator
    {
        yield from $this->dispatch($message);
    }

    public function loadAggregateState(string $aggregateType, string $aggregateId, int $expectedVersion = null)
    {
        $this->assertBootstrapped(__METHOD__);

        if (! \array_key_exists($aggregateType, $this->aggregateDescriptions)) {
            throw new InvalidArgumentException('Unknown aggregate type: ' . $aggregateType);
        }

        $aggregateDesc = $this->aggregateDescriptions[$aggregateType];

        $arRepository = new GenericAggregateRepository(
            $this->flavour,
            $this->eventStore,
            $this->writeModelStreamName(),
            $this->documentStore,
            $aggregateDesc['aggregateCollection'] ?? null
        );

        /** @var FlavouredAggregateRoot $aggregate */
        $aggregate = $arRepository->getAggregateRoot($aggregateType, $aggregateId, $aggregateDesc['eventApplyMap'], $expectedVersion);

        if (! $aggregate) {
            throw AggregateNotFound::with($aggregateType, $aggregateId);
        }

        return $aggregate->currentState();
    }

    private function compileAggregateAndRoutingDescriptions(): void
    {
        $aggregateDescriptions = [];

        $this->compiledCommandRouting = [];

        foreach ($this->commandRouting as $commandName => $commandProcessorDesc) {
            $descArr = $commandProcessorDesc();

            if ($descArr['createAggregate']) {
                $aggregateDescriptions[$descArr['aggregateType']] = [
                    'aggregateType' => $descArr['aggregateType'],
                    'aggregateIdentifier' => $descArr['aggregateIdentifier'],
                    'eventApplyMap' => $descArr['eventRecorderMap'],
                    'aggregateCollection' => $descArr['aggregateCollection'] ?? AggregateProjector::aggregateCollectionName(
                            '0.1.0',
                            $descArr['aggregateType']
                        )
                ];
            }

            $this->compiledCommandRouting[$commandName] = $descArr;
        }

        foreach ($this->compiledCommandRouting as $commandName => &$descArr) {
            $aggregateDesc = $aggregateDescriptions[$descArr['aggregateType']] ?? null;

            if (null === $aggregateDesc) {
                throw new RuntimeException('Missing aggregate handle method that creates the aggregate of type: ' . $descArr['aggregateType']);
            }

            $descArr['aggregateIdentifier'] = $aggregateDesc['aggregateIdentifier'];

            $aggregateDesc['eventApplyMap'] = \array_merge($aggregateDesc['eventApplyMap'], $descArr['eventRecorderMap']);
            $aggregateDescriptions[$descArr['aggregateType']] = $aggregateDesc;
        }

        $this->aggregateDescriptions = $aggregateDescriptions;
    }

    private function compileProjectionDescriptions(): void
    {
        foreach ($this->projectionMap as $prjName => $projectionDesc) {
            $this->compiledProjectionDescriptions[$prjName] = $projectionDesc();
        }
    }

    private function compileQueryDescriptions(): void
    {
        foreach ($this->queryDescriptions as $name => $description) {
            $this->compiledQueryDescriptions[$name] = $description();
        }
    }

    private function assertNotInitialized(string $method)
    {
        if ($this->initialized) {
            throw new BadMethodCallException("Method $method cannot be called after event machine is initialized");
        }
    }

    private function assertInitialized(string $method)
    {
        if (! $this->initialized) {
            throw new BadMethodCallException("Method $method cannot be called before event machine is initialized");
        }
    }

    private function assertNotBootstrapped(string $method)
    {
        if ($this->bootstrapped) {
            throw new BadMethodCallException("Method $method cannot be called after event machine is bootstrapped");
        }
    }

    private function assertBootstrapped(string $method)
    {
        if (! $this->bootstrapped) {
            throw new BadMethodCallException("Method $method cannot be called before event machine is bootstrapped");
        }
    }

    private function assertTestMode(string $method)
    {
        if (! $this->testMode) {
            throw new BadMethodCallException("Method $method cannot be called if event machine is not bootstrapped in test mode");
        }
    }
}
