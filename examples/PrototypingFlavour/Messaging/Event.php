<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\PrototypingFlavour\Messaging;

final class Event
{
    const USER_WAS_REGISTERED = 'UserWasRegistered';
    const USER_REGISTRATION_FAILED = 'UserRegistrationFailed';
    const USERNAME_WAS_CHANGED = 'UsernameWasChanged';

    private function __construct()
    {
        //static class only
    }
}
