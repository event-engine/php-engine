<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\FunctionalFlavour\Resolver;

use EventEngine\Querying\Resolver;
use EventEngineExample\FunctionalFlavour\Query\GetUsers;

final class GetUsersResolver
{
    private $cachedUsers;

    public function __construct(array $cachedUsers)
    {
        $this->cachedUsers = $cachedUsers;
    }

    public function resolve(GetUsers $getUsers)
    {
        return \array_filter($this->cachedUsers, function (array $user) use ($getUsers): bool {
            return (null === $getUsers->username || $user['username'] === $getUsers->username)
                && (null === $getUsers->email || $user['email'] === $getUsers->email);
        });
    }
}
