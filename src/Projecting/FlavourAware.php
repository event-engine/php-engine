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

use EventEngine\Runtime\Flavour;

/**
 * Interface FlavourAware
 *
 * When projectors are loaded using EventMacine::loadProjector()
 * and implement this interface, they get current Flavour of Event Machine injected
 *
 * @package EventEngine\Projecting
 */
interface FlavourAware
{
    public function setFlavour(Flavour $flavour): void;
}
