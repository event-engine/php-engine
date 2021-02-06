<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Projecting;

use EventEngine\DocumentStore\Index;
use EventEngine\EventEngine;
use EventEngine\Exception\InvalidArgumentException;
use EventEngine\Messaging\Exception\RuntimeException;
use EventEngine\Messaging\GenericSchemaMessage;
use EventEngine\Persistence\Stream;
use EventEngine\Persistence\StreamCollection;
use EventEngine\Util\VariableType;

final class ProjectionDescription
{
    public const PROJECTION_NAME = 'projection_name';
    public const PROJECTION_VERSION = 'projection_version';
    public const SOURCE_STREAMS = 'source_streams';
    public const PROJECTOR_SERVICE_ID = 'projector_service_id';
    public const AGGREGATE_TYPE_FILTER = 'aggregate_type_filter';
    public const EVENTS_FILTER = 'events_filter';
    public const DOCUMENT_STORE_INDICES = 'document_store_indices';
    public const PROJECTOR_OPTIONS = 'projector_options';

    /**
     * @var StreamCollection
     */
    private $sourceStreams;

    /**
     * @var string
     */
    private $projectionName;

    /**
     * @var string
     */
    private $projectorServiceId;

    /**
     * @var string|null
     */
    private $aggregateTypeFilter;

    /**
     * @var array|null
     */
    private $eventsFilter;

    /**
     * @var string
     */
    private $projectionVersion;

    /**
     * @var array
     */
    private $documentStoreIndices = [];

    /**
     * @var array
     */
    private $projectorOptions = [];

    /**
     * @var EventEngine
     */
    private $eventEngine;

    public function __construct(EventEngine $eventEngine, Stream ...$streams)
    {
        $this->sourceStreams = StreamCollection::fromItems(...$streams);
        $this->eventEngine = $eventEngine;
    }

    public function with(string $projectionName, string $projectorServiceId, string $projectionVersion = '0.1.0'): self
    {
        if (\mb_strlen($projectionName) === 0) {
            throw new InvalidArgumentException('Projection name must not be empty');
        }

        if (\mb_strlen($projectorServiceId) === 0) {
            throw new InvalidArgumentException('Projector service id must not be empty');
        }

        if ($this->eventEngine->isKnownProjection($projectionName)) {
            throw new InvalidArgumentException("Projection $projectionName is already registered!");
        }

        $this->projectionName = $projectionName;
        $this->projectorServiceId = $projectorServiceId;
        $this->projectionVersion = $projectionVersion;

        $this->eventEngine->registerProjection($projectionName, $this);

        return $this;
    }

    public function withAggregateProjection(string $aggregateType, string $projectionVersion = '0.1.0'): self
    {
        return $this->with(AggregateProjector::generateProjectionName($aggregateType), AggregateProjector::class, $projectionVersion)
            ->filterAggregateType($aggregateType);
    }

    public function filterAggregateType(string $aggregateType): self
    {
        $this->assertWithProjectionIsCalled(__METHOD__);

        if (\mb_strlen($aggregateType) === 0) {
            throw new InvalidArgumentException('Aggregate type filter must not be empty');
        }

        $this->aggregateTypeFilter = $aggregateType;

        return $this;
    }

    public function filterEvents(array $listOfEvents): self
    {
        $this->assertWithProjectionIsCalled(__METHOD__);

        foreach ($listOfEvents as $event) {
            if (! \is_string($event)) {
                throw new InvalidArgumentException('Event filter must be a list of event names. Got a ' . (\is_object($event) ? \get_class($event) : \gettype($event)));
            }
        }

        $this->eventsFilter = $listOfEvents;

        return $this;
    }

    public function useIndices(Index ...$indices): self
    {
        $this->documentStoreIndices = array_map(function (Index $index) {
            return $index->toArray();
        }, $indices);

        return $this;
    }

    public function setProjectorOptions(array $options): self
    {
        try {
            GenericSchemaMessage::assertPayload($options);
        } catch (RuntimeException $error) {
            throw new \EventEngine\Exception\RuntimeException("Projector options should only contain scalar values and arrays.");
        }

        $this->projectorOptions = $options;

        return $this;
    }

    public function __invoke()
    {
        $this->assertWithProjectionIsCalled('EventEngine::initialize');

        return [
            self::PROJECTION_NAME => $this->projectionName,
            self::PROJECTION_VERSION => $this->projectionVersion,
            self::PROJECTOR_SERVICE_ID => $this->projectorServiceId,
            self::SOURCE_STREAMS => $this->sourceStreams->toArray(),
            self::AGGREGATE_TYPE_FILTER => $this->aggregateTypeFilter,
            self::EVENTS_FILTER => $this->eventsFilter,
            self::DOCUMENT_STORE_INDICES => $this->documentStoreIndices,
            self::PROJECTOR_OPTIONS => $this->projectorOptions,
        ];
    }

    private function assertWithProjectionIsCalled(string $method): void
    {
        if (null === $this->projectionName) {
            throw new \BadMethodCallException("Method with projection was not called. You need to call it before calling $method");
        }
    }
}
