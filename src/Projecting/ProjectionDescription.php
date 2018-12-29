<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Projecting;

use EventEngine\EventEngine;
use EventEngine\Exception\InvalidArgumentException;
use EventEngine\Persistence\Stream;

final class ProjectionDescription
{
    public const PROJECTION_NAME = 'projection_name';
    public const PROJECTION_VERSION = 'projection_version';
    public const SOURCE_STREAM = 'source_stream';
    public const PROJECTOR_SERVICE_ID = 'projector_service_id';
    public const AGGREGATE_TYPE_FILTER = 'aggregate_type_filter';
    public const EVENTS_FILTER = 'events_filter';

    /**
     * @var Stream
     */
    private $sourceStream;

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
     * @var EventEngine
     */
    private $eventEngine;

    public function __construct(Stream $stream, EventEngine $eventEngine)
    {
        $this->sourceStream = $stream;
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

    public function __invoke()
    {
        $this->assertWithProjectionIsCalled('EventEngine::initialize');

        return [
            self::PROJECTION_NAME => $this->projectionName,
            self::PROJECTION_VERSION => $this->projectionVersion,
            self::PROJECTOR_SERVICE_ID => $this->projectorServiceId,
            self::SOURCE_STREAM => $this->sourceStream->toArray(),
            self::AGGREGATE_TYPE_FILTER => $this->aggregateTypeFilter,
            self::EVENTS_FILTER => $this->eventsFilter,
        ];
    }

    private function assertWithProjectionIsCalled(string $method): void
    {
        if (null === $this->projectionName) {
            throw new \BadMethodCallException("Method with projection was not called. You need to call it before calling $method");
        }
    }
}
