<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\FunctionalFlavour\Api;

use EventEngineExample\FunctionalFlavour\Query\GetUser;
use EventEngineExample\FunctionalFlavour\Query\GetUsers;

final class Query
{
    const GET_USER = 'GetUser';
    const GET_USERS = 'GetUsers';
    const GET_FILTERED_USERS = 'GetFilteredUsers';

    const CLASS_MAP = [
        self::GET_USER => GetUser::class,
        self::GET_USERS => GetUsers::class,
    ];

    public static function createFromNameAndPayload(string $queryName, array $payload)
    {
        $class = self::CLASS_MAP[$queryName];

        return new $class($payload);
    }

    public static function nameOf($query): string
    {
        $map = \array_flip(self::CLASS_MAP);

        return $map[\get_class($query)];
    }

    private function __construct()
    {
        //static class only
    }
}
