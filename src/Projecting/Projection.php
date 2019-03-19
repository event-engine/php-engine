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
use EventEngine\Messaging\Message;
use EventEngine\Persistence\Stream;
use EventEngine\Persistence\StreamCollection;
use EventEngine\Runtime\Flavour;

final class Projection
{
    /**
     * @var array
     */
    private $desc;

    /**
     * @var StreamCollection
     */
    private $sourceStreams;

    /**
     * @var Projector|CustomEventProjector
     */
    private $projector;

    /**
     * @var Flavour
     */
    private $flavour;

    /**
     * @var string
     */
    private $projectionVersion;

    public static function fromProjectionDescription(array $desc, Flavour $flavour, EventEngine $eventEngine): Projection
    {
        $projector = $eventEngine->loadProjector($desc[ProjectionDescription::PROJECTOR_SERVICE_ID]);

        return new self($desc, $projector, $flavour, $desc[ProjectionDescription::PROJECTION_VERSION]);
    }

    private function __construct(array $desc, $projector, Flavour $flavour, string $projectionVersion)
    {
        $this->desc = $desc;
        $this->sourceStreams = StreamCollection::fromArray($this->desc[ProjectionDescription::SOURCE_STREAMS]);
        $this->flavour = $flavour;
        $this->projector = $projector;
        $this->projectionVersion = $projectionVersion;
    }

    public function isInterestedIn(string $sourceStreamName, Message $event): bool
    {
        if (!$this->sourceStreams->containsSourceStreamName($sourceStreamName)) {
            return false;
        }

        if ($this->desc[ProjectionDescription::AGGREGATE_TYPE_FILTER]) {
            $aggregateType = $event->metadata()['_aggregate_type'] ?? null;

            if (! $aggregateType) {
                return false;
            }

            if ($this->desc[ProjectionDescription::AGGREGATE_TYPE_FILTER] !== $aggregateType) {
                return false;
            }
        }

        if ($this->desc[ProjectionDescription::EVENTS_FILTER]) {
            if (! \in_array($event->messageName(), $this->desc[ProjectionDescription::EVENTS_FILTER])) {
                return false;
            }
        }

        return true;
    }

    public function prepareForRun(): void
    {
        $this->projector->prepareForRun($this->projectionVersion, $this->desc[ProjectionDescription::PROJECTION_NAME]);
    }

    public function handle(Message $event): void
    {
        $this->flavour->callProjector($this->projector, $this->projectionVersion, $this->desc[ProjectionDescription::PROJECTION_NAME], $event);
    }

    public function delete(): void
    {
        $this->projector->deleteReadModel($this->projectionVersion, $this->desc[ProjectionDescription::PROJECTION_NAME]);
    }
}
