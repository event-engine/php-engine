<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\PrototypingFlavour\Messaging;

final class Command
{
    const REGISTER_USER = 'RegisterUser';
    const CHANGE_USERNAME = 'ChangeUsername';
    const CHANGE_EMAIL = 'ChangeEmail';
    const CONNECT_WITH_FRIEND = 'ConnectWithFriend';
    const DO_NOTHING = 'DoNothing';

    private function __construct()
    {
        //static class only
    }
}
