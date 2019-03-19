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

use EventEngine\Exception\InvalidArgumentException;
use EventEngine\Persistence\Stream;
use EventEngine\Persistence\StreamCollection;

final class ProjectionInfo
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $version;

    /**
     * @var StreamCollection
     */
    private $sourceStreams;

    /**
     * @var string|null
     */
    private $aggregateTypeFilter;

    /**
     * @var string[]|null
     */
    private $eventsFilter;

    public static function fromDescriptionArray(array $desc): self
    {
        if(! array_key_exists(ProjectionDescription::PROJECTION_NAME, $desc)) {
            throw new InvalidArgumentException(sprintf(
                "Missing key %s in projection description",
                ProjectionDescription::PROJECTION_NAME
            ));
        }

        if(! array_key_exists(ProjectionDescription::PROJECTION_VERSION, $desc)) {
            throw new InvalidArgumentException(sprintf(
                "Missing key %s in projection description",
                ProjectionDescription::PROJECTION_VERSION
            ));
        }

        if(! array_key_exists(ProjectionDescription::SOURCE_STREAMS, $desc)) {
            throw new InvalidArgumentException(sprintf(
                "Missing key %s in projection description",
                ProjectionDescription::SOURCE_STREAMS
            ));
        }

        return new self(
            $desc[ProjectionDescription::PROJECTION_NAME],
            $desc[ProjectionDescription::PROJECTION_VERSION],
            StreamCollection::fromArray($desc[ProjectionDescription::SOURCE_STREAMS]),
            $desc[ProjectionDescription::AGGREGATE_TYPE_FILTER] ?? null,
            $desc[ProjectionDescription::EVENTS_FILTER] ?? null,
        );
    }

    private function __construct(string $name, string $version, StreamCollection $sourceStreams, string $aggregateTypeFilter = null, array $eventsFilter = null)
    {
        $this->name = $name;
        $this->version = $version;
        $this->sourceStreams = $sourceStreams;
        $this->aggregateTypeFilter = $aggregateTypeFilter;
        $this->eventsFilter = $eventsFilter;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function version(): string
    {
        return $this->version;
    }

    /**
     * @return StreamCollection
     */
    public function sourceStreams(): StreamCollection
    {
        return $this->sourceStreams;
    }

    /**
     * @return null|string
     */
    public function aggregateTypeFilter(): ?string
    {
        return $this->aggregateTypeFilter;
    }

    /**
     * @return null|string[]
     */
    public function eventsFilter(): ?array
    {
        return $this->eventsFilter;
    }
}
