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

/**
 * Interface CustomEventProjector
 *
 * Similar interface like Projector, but handles mixed $event
 *
 * @package EventEngine\Projecting
 */
interface CustomEventProjector
{
    public function prepareForRun(string $projectionVersion, string $projectionName): void;

    public function handle(string $projectionVersion, string $projectionName, $event): void;

    public function deleteReadModel(string $projectionVersion, string $projectionName): void;
}
