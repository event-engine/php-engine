<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\FunctionalFlavour\Event;

use EventEngineExample\FunctionalFlavour\Util\ApplyPayload;

final class UsernameChanged
{
    use ApplyPayload;

    /**
     * @var string
     */
    public $userId;

    /**
     * @var string
     */
    public $oldName;

    public $newName;
}
