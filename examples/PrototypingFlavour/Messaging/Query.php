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

final class Query
{
    const GET_USER = 'GetUser';
    const GET_USERS = 'GetUsers';
    const GET_FILTERED_USERS = 'GetFilteredUsers';

    private function __construct()
    {
        //static class only
    }
}
