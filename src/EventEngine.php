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
use EventEngine\DocumentStore\FieldIndex;
use EventEngine\DocumentStore\MultiFieldIndex;
use EventEngine\EventStore\EventStore;
use EventEngine\Exception\BadMethodCallException;
use EventEngine\Exception\InvalidArgumentException;
use EventEngine\Exception\RuntimeException;
use EventEngine\Logger\LogEngine;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\GenericSchemaMessageFactory;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageDispatcher;
use EventEngine\Messaging\MessageFactory;
use EventEngine\Messaging\MessageFactoryAware;
use EventEngine\Messaging\MessageProducer;
use EventEngine\Persistence\AggregateStateStore;
use EventEngine\Persistence\Stream;
use EventEngine\Projecting\AggregateProjector;
use EventEngine\Projecting\CustomEventProjector;
use EventEngine\Projecting\DocumentStoreIndexAware;
use EventEngine\Projecting\Exception\ProjectorFailed;
use EventEngine\Projecting\FlavourAware;
use EventEngine\Projecting\OptionsAware;
use EventEngine\Projecting\ProjectionDescription;
use EventEngine\Projecting\ProjectionInfoList;
use EventEngine\Projecting\Projector;
use EventEngine\Projecting\Projection;
use EventEngine\Querying\QueryDescription;
use EventEngine\Runtime\Flavour;
use EventEngine\Schema\InputTypeSchema;
use EventEngine\Schema\MessageBox\CommandMap;
use EventEngine\Schema\MessageBox\EventMap;
use EventEngine\Schema\MessageBox\QueryMap;
use EventEngine\Schema\MessageSchema;
use EventEngine\Schema\PayloadSchema;
use EventEngine\Schema\ResponseTypeSchema;
use EventEngine\Schema\Schema;
use EventEngine\Schema\TypeSchema;
use EventEngine\Schema\TypeSchemaMap;
use EventEngine\Util\VariableType;
use Psr\Container\ContainerInterface;

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
     * @var LogEngine
     */
    private $log;

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

    /**
     * @var mixed[]
     */
    private $aggregateCache = [];

    /**
     * Use EventEngine::disableAutoPublish() to take control of event publishing yourself
     *
     * @var bool
     */
    private $autoPublishEnabled = true;

    /**
     * Use EventEngine::disableAutoPojecting() to take control of projection runs
     *
     * @var bool
     */
    private $autoProjectingEnabled = true;

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
        LogEngine $logEngine,
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

        if (! \array_key_exists('writeModelStreamName', $config)) {
            throw new InvalidArgumentException('Missing key writeModelStreamName in cached event engine config');
        }

        if (! \array_key_exists('autoPublish', $config)) {
            throw new InvalidArgumentException('Missing key autoPublish in cached event engine config');
        }

        if (! \array_key_exists('autoProjecting', $config)) {
            throw new InvalidArgumentException('Missing key autoProjecting in cached event engine config');
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
        $self->responseTypes = array_map(function (string $typeName, array $typeSchema) use ($schema): ResponseTypeSchema {
            return $schema->buildResponseTypeSchemaFromArray($typeName, $typeSchema);
        }, array_keys($config['responseTypes'] ?? []), $config['responseTypes'] ?? []);
        $self->inputTypes = array_map(function (string $typeName, array $typeSchema) use ($schema): ResponseTypeSchema {
            return $schema->buildResponseTypeSchemaFromArray($typeName, $typeSchema);
        }, array_keys($config['inputTypes'] ?? []), $config['inputTypes'] ?? []);
        $self->writeModelStreamName = $config['writeModelStreamName'];

        $self->flavour = $flavour;
        $self->eventStore = $eventStore;
        $self->log = $logEngine;
        $self->container = $container;
        $self->documentStore = $documentStore;
        $self->eventQueue = $eventQueue;
        $self->autoPublishEnabled = $config['autoPublish'];
        $self->autoProjectingEnabled = $config['autoProjecting'];

        foreach ($self->responseTypes as $typeName => $responseType) {
            $self->typeSchemaMap->add($typeName, $responseType);
        }

        foreach ($self->inputTypes as $typeName => $inputType) {
            $self->typeSchemaMap->add($typeName, $inputType);
        }

        $self->initialized = true;

        $self->log->initializedFromCachedConfig($config);

        return $self;
    }

    public function compileCacheableConfig(): array
    {
        $this->assertInitialized(__METHOD__);

        $assertClosure = function ($val) {
            if ($val instanceof \Closure) {
                throw new RuntimeException('At least one EventEngineDescription contains a Closure and is therefor not cacheable!');
            }
        };

        \array_walk_recursive($this->compiledCommandRouting, $assertClosure);
        \array_walk_recursive($this->aggregateDescriptions, $assertClosure);
        \array_walk_recursive($this->eventRouting, $assertClosure);
        \array_walk_recursive($this->projectionMap, $assertClosure);
        \array_walk_recursive($this->compiledQueryDescriptions, $assertClosure);

        $schemaToArray = function (TypeSchema $typeSchema): array  {
            return $typeSchema->toArray();
        };

        return [
            'commandMap' => array_map($schemaToArray, $this->commandMap),
            'eventMap' => array_map($schemaToArray, $this->eventMap),
            'compiledCommandRouting' => $this->compiledCommandRouting,
            'aggregateDescriptions' => $this->aggregateDescriptions,
            'eventRouting' => $this->eventRouting,
            'compiledProjectionDescriptions' => $this->compiledProjectionDescriptions,
            'compiledQueryDescriptions' => $this->compiledQueryDescriptions,
            'queryMap' => array_map($schemaToArray, $this->queryMap),
            'responseTypes' => array_map($schemaToArray, $this->responseTypes),
            'inputTypes' => array_map($schemaToArray, $this->inputTypes),
            'writeModelStreamName' => $this->writeModelStreamName,
            'autoPublish' => $this->autoPublishEnabled,
            'autoProjecting' => $this->autoProjectingEnabled,
        ];
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

    public function disableAutoPublish(): self
    {
        $this->assertNotInitialized(__METHOD__);
        $this->autoPublishEnabled = false;
        return $this;
    }

    public function disableAutoProjecting(): self
    {
        $this->assertNotInitialized(__METHOD__);
        $this->autoProjectingEnabled = false;
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

    public function messageBoxSchema(): array
    {
        $this->assertInitialized(__METHOD__);

        $queryDescriptions = [];

        foreach ($this->compiledQueryDescriptions as $name => $desc) {
            $desc['returnType'] = $this->schema->buildResponseTypeSchemaFromArray($name, $desc['returnType']);
            $queryDescriptions[$name] = $desc;
        }

        return $this->schema->buildMessageBoxSchema(
            CommandMap::fromEventEngineMap($this->commandMap),
            EventMap::fromEventEngineMap($this->eventMap),
            QueryMap::fromEventEngineMapAndQueryDescriptions($this->queryMap, $queryDescriptions),
            $this->typeSchemaMap
        );
    }

    public function initialize(
        Flavour $flavour,
        EventStore $eventStore,
        LogEngine $logEngine,
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
        $this->log = $logEngine;
        $this->container = $container;
        $this->documentStore = $documentStore;
        $this->eventQueue = $eventQueue;

        $this->initialized = true;

        $this->log->initializedAfterLoadingDescriptions($this->commandMap, $this->eventMap, $this->queryMap);

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
            $this->typeSchemaMap,
            $this->flavour
        );

        if($this->flavour instanceof MessageFactoryAware) {
            $this->flavour->setMessageFactory($this->messageFactory);
        }

        $this->bootstrapped = true;
        $this->debugMode = $debugMode;
        $this->env = $env;

        $this->log->bootstrapped($this->env, $this->debugMode);

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws \Throwable
     */
    public function dispatch($messageOrName, array $payload = [])
    {
        $this->assertBootstrapped(__METHOD__);

        if (\is_string($messageOrName)) {
            $messageOrName = $this->messageFactory()->createMessageFromArray($messageOrName, ['payload' => $payload]);
        } else {
            $messageOrName = $this->flavour->convertMessageReceivedFromNetwork($messageOrName);
        }

        if (! $messageOrName instanceof Message) {
            throw new InvalidArgumentException(
                'Invalid message received. Must be either a known message name or an instance of EventEngine\Messaging\Message. Got '
                . VariableType::determine($messageOrName)
            );
        }

        $this->log->dispatchStarted($messageOrName);

        switch ($messageOrName->messageType()) {
            case Message::TYPE_COMMAND:
                $processorDesc = $this->compiledCommandRouting[$messageOrName->messageName()] ?? [];

                $container = $this->container;

                $preProcessors = array_map(function ($preProcessor) use ($container) {
                    if (\is_string($preProcessor)) {
                        $preProcessor = $this->container->get($preProcessor);
                    }
                    return $preProcessor;
                }, $this->commandPreProcessors[$messageOrName->messageName()] ?? []);

                $this->clearAggregateCache();

                return CommandDispatch::exec(
                    $messageOrName,
                    $this->flavour,
                    $this->eventStore,
                    $this->log,
                    $preProcessors,
                    $processorDesc,
                    $this->aggregateDescriptions,
                    $this->autoPublishEnabled,
                    $this->autoProjectingEnabled,
                    $this->eventQueue ?? $this,
                    $this,
                    $this->documentStore,
                    isset($processorDesc['contextProvider']) ? $container->get($processorDesc['contextProvider']) : null
                );
                break;
            case Message::TYPE_EVENT:
                $listeners = $this->eventRouting[$messageOrName->messageName()] ?? [];

                foreach ($listeners as $listener) {
                    if(\is_string($listener)) {
                        $listener = $this->container->get($listener);
                    }

                    $this->flavour->callEventListener($listener, $messageOrName);
                    $this->log->eventListenerCalled($listener, $messageOrName);
                }

                return null;
                break;
            case Message::TYPE_QUERY:
                $queryDesc = $this->compiledQueryDescriptions[$messageOrName->messageName()] ?? null;

                if(!$queryDesc) {
                    throw new RuntimeException("No routing information found for query {$messageOrName->messageName()}");
                }

                $resolver = $this->container->get($queryDesc['resolver'] ?? null);

                $result = $this->flavour->callQueryResolver($resolver, $messageOrName);
                $this->log->queryResolverCalled($resolver, $messageOrName);

                return $result;
                break;
            default:
                throw new RuntimeException('Unsupported message type: ' . $messageOrName->messageType());
        }
    }

    public function produce(Message $message)
    {
        return $this->dispatch($message);
    }

    public function loadAggregateState(string $aggregateType, string $aggregateId, int $expectedVersion = null)
    {
        $this->assertBootstrapped(__METHOD__);

        if (! \array_key_exists($aggregateType, $this->aggregateDescriptions)) {
            throw new InvalidArgumentException('Unknown aggregate type: ' . $aggregateType);
        }

        if($cachedAggregate = $this->loadAggregateStateFromCache($aggregateType, $aggregateId, $expectedVersion)) {
            $this->log->aggregateStateLoadedFromCache($aggregateType, $aggregateId, $expectedVersion);
            return $cachedAggregate;
        }

        $aggregateDesc = $this->aggregateDescriptions[$aggregateType];

        $arRepository = new GenericAggregateRepository(
            $this->flavour,
            $this->eventStore,
            $aggregateDesc['aggregateStream'],
            $this->documentStore,
            $aggregateDesc['aggregateCollection'] ?? null
        );

        /** @var FlavouredAggregateRoot $aggregate */
        $aggregate = $arRepository->getAggregateRoot($aggregateType, $aggregateId, $aggregateDesc['eventApplyMap'], $expectedVersion);

        if (! $aggregate) {
            throw AggregateNotFound::with($aggregateType, $aggregateId);
        }

        $this->log->aggregateStateLoaded($aggregate->aggregateType(), $aggregate->aggregateId(), $aggregate->version());

        $this->cacheAggregateState($aggregateType, $aggregateId, $aggregate->version(), $aggregate->currentState());

        return $aggregate->currentState();
    }

    /**
     * @param string $projectorServiceId
     * @return Projector|CustomEventProjector
     */
    public function loadProjector(string $projectorServiceId, string $projectionName = '')
    {
        if($projectorServiceId === AggregateProjector::class && $this->documentStore
            && !$this->container->has($projectorServiceId)) {
            $projector = new AggregateProjector($this->documentStore, $this);
        } else {
            $projector = $this->container->get($projectorServiceId);
        }

        if (! $projector instanceof Projector
            && ! $projector instanceof CustomEventProjector) {
            throw new RuntimeException(
                \sprintf(
                    "Projector $projectorServiceId should either be an instance of %s or %s",
                    Projector::class,
                    CustomEventProjector::class
                )
            );
        }

        $projectionDesc = $this->compiledProjectionDescriptions[$projectionName] ?? [];

        if ($projector instanceof FlavourAware) {
            $projector->setFlavour($this->flavour());
        }

        if($projector instanceof DocumentStoreIndexAware) {
            if(!empty($projectionDesc[ProjectionDescription::DOCUMENT_STORE_INDICES] ?? [])) {
                $indices = array_map(function (array $index) {
                    if(array_key_exists('fields', $index)) {
                        return MultiFieldIndex::fromArray($index);
                    }

                    return FieldIndex::fromArray($index);
                }, $projectionDesc[ProjectionDescription::DOCUMENT_STORE_INDICES]);

                $projector->setDocumentStoreIndices($indices);
            }
        }

        if($projector instanceof OptionsAware) {
            $projector->setProjectorOptions($projectionDesc[ProjectionDescription::PROJECTOR_OPTIONS] ?? []);
        }

        return $projector;
    }

    public function runAllProjections(string $sourceStream, GenericEvent ...$events): void
    {
        foreach ($this->compiledProjectionDescriptions as $prj => $desc) {
            $this->runProjection($prj, $sourceStream, ...$events);
        }
    }

    public function runProjection(string $projectionName, string $sourceStream, GenericEvent ...$events): void
    {
        $this->assertInitialized(__METHOD__);

        if(! array_key_exists($projectionName, $this->compiledProjectionDescriptions)) {
            throw new RuntimeException("Unknown projection $projectionName.");
        }

        $projection = Projection::fromProjectionDescription(
            $this->compiledProjectionDescriptions[$projectionName],
            $this->flavour,
            $this
        );

        foreach ($events as $event) {
            if($projection->isInterestedIn($sourceStream, $event)) {
                try {
                    $projection->handle($event);
                    $this->log->projectionHandledEvent($projectionName, $event);
                } catch (\Throwable $error) {
                    throw ProjectorFailed::atEvent(
                        $event,
                        $projectionName,
                        $this->compiledProjectionDescriptions[$projectionName][ProjectionDescription::PROJECTOR_SERVICE_ID] ?? 'Unknown',
                        $error
                        );
                }
            }
        }
    }

    public function setUpAllProjections(): void
    {
        foreach ($this->compiledProjectionDescriptions as $prj => $desc) {
            $this->setUpProjection($prj);
        }
    }

    public function setUpProjection(string $projectionName): void
    {
        $this->assertInitialized(__METHOD__);

        if(! array_key_exists($projectionName, $this->compiledProjectionDescriptions)) {
            throw new RuntimeException("Unknown projection $projectionName.");
        }

        $projection = Projection::fromProjectionDescription(
            $this->compiledProjectionDescriptions[$projectionName],
            $this->flavour,
            $this
        );

        $projection->prepareForRun();

        $this->log->projectionSetUp($projectionName);
    }

    public function deleteAllProjections(): void
    {
        foreach ($this->compiledProjectionDescriptions as $prj => $desc) {
            $this->deleteProjection($prj);
        }
    }

    public function deleteProjection(string $projectionName): void
    {
        $this->assertInitialized(__METHOD__);

        if(! array_key_exists($projectionName, $this->compiledProjectionDescriptions)) {
            throw new RuntimeException("Unknown projection $projectionName.");
        }

        $projection = Projection::fromProjectionDescription(
            $this->compiledProjectionDescriptions[$projectionName],
            $this->flavour,
            $this
        );

        $projection->delete();

        $this->log->projectionDeleted($projectionName);
    }

    public function projectionVersion(string $projectionName): string
    {
        $this->assertInitialized(__METHOD__);

        if(! array_key_exists($projectionName, $this->compiledProjectionDescriptions)) {
            throw new RuntimeException("Unknown projection $projectionName.");
        }

        return $this->compiledProjectionDescriptions[$projectionName][ProjectionDescription::PROJECTION_VERSION] ?? '0.1.0';
    }

    public function projectionInfo(): ProjectionInfoList
    {
        return ProjectionInfoList::fromDescriptions($this->compiledProjectionDescriptions);
    }

    public function cacheAggregateState(string $aggregateType, string $aggregateId, int $version, $aggregateState): void
    {
        $this->aggregateCache[$aggregateType][$aggregateId] = [
            'version' => $version,
            'state' => $aggregateState
        ];
    }
    /**
     * @param string $aggregateType
     * @param string $aggregateId
     * @return null|mixed Null is returned if no state is cached, otherwise the cached process state
     */
    public function loadAggregateStateFromCache(string $aggregateType, string $aggregateId, int $expectedVersion = null)
    {
        $cache = $this->aggregateCache[$aggregateType][$aggregateId] ?? null;
        if(!$cache) {
            return null;
        }
        if($expectedVersion) {
            if($expectedVersion !== $cache['version']) {return null;
            }
        }
        return $cache['state'];
    }

    public function clearAggregateCache(): void
    {
        $this->aggregateCache = [];
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
                    'aggregateStream' => $descArr['streamName'],
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

            if(null === $descArr['aggregateIdentifier']) {
                $descArr['aggregateIdentifier'] = $aggregateDesc['aggregateIdentifier'];
            }

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
