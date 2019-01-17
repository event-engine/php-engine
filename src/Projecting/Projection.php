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
use EventEngine\Messaging\GenericEvent;
use EventEngine\Messaging\Message;
use EventEngine\Persistence\Stream;
use EventEngine\Runtime\Flavour;

final class Projection
{
    /**
     * @var array
     */
    private $desc;

    /**
     * @var Stream
     */
    private $sourceStream;

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

    private function __construct(array $desc, $projector, Flavour $flavour, string $projecionVersion)
    {
        $this->desc = $desc;
        $this->sourceStream = Stream::fromArray($this->desc[ProjectionDescription::SOURCE_STREAM]);
        $this->flavour = $flavour;
        $this->projector = $projector;
        $this->projectionVersion = $projecionVersion;
    }

    public function isInterestedIn(string $sourceStreamName, Message $event): bool
    {
        if ($this->sourceStream->streamName() !== $sourceStreamName) {
            return false;
        }

        if ($this->desc[ProjectionDescription::PROCESS_TYPE_FILTER]) {
            $processType = $event->metadata()[GenericEvent::META_PROCESS_TYPE] ?? null;

            if (! $processType) {
                return false;
            }

            if ($this->desc[ProjectionDescription::PROCESS_TYPE_FILTER] !== $processType) {
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
