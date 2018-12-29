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

use EventEngine\Messaging\Message;

/**
 * Projections are rebuilt on each deployment
 *
 * A projector should always include the app version in table/collection names.
 *
 * A blue/green deployment strategy can be used:
 * This means that the read model for the new app version is built during deployment.
 * The old read model remains active. In case of a rollback it is still available and can be accessed.
 *
 * The old read model is first deleted when yet another new version of the app is deployed.
 *
 * Interface Projector
 * @package EventEngine\Projecting
 */
interface Projector
{
    public function prepareForRun(string $projectionVersion, string $projectionName): void;

    public function handle(string $projectionVersion, string $projectionName, Message $event): void;

    public function deleteReadModel(string $projectionVersion, string $projectionName): void;
}
