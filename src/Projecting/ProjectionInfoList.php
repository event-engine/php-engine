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

final class ProjectionInfoList
{
    /**
     * @var ProjectionInfo[]
     */
    private $projections;

    public static function fromDescriptions(array $descriptions): self
    {
        $infos = [];

        foreach ($descriptions as $desc) {
            $infos[] = ProjectionInfo::fromDescriptionArray($desc);
        }

        return new self(...$infos);
    }

    private function __construct(ProjectionInfo ...$infos)
    {
        $this->projections = $infos;
    }

    /**
     * @return ProjectionInfo[]
     */
    public function projections(): array
    {
        return $this->projections;
    }

    /**
     * @param string $projectionName
     * @return ProjectionInfo
     */
    public function projection(string $projectionName): ?ProjectionInfo
    {
        foreach ($this->projections as $projectionInfo) {
            if ($projectionInfo->name() === $projectionName) {
                return $projectionInfo;
            }
        }
        return null;
    }

}
