<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\PrototypingFlavour\Resolver;

use EventEngine\Messaging\Message;
use EventEngine\Querying\Resolver;

final class GetUsersResolver implements Resolver
{
    private $cachedUsers;

    public function __construct(array $cachedUsers)
    {
        $this->cachedUsers = $cachedUsers;
    }

    /**
     * @param Message $query
     */
    public function resolve(Message $getUsers)
    {
        $usernameFilter = $getUsers->getOrDefault('username', null);
        $emailFilter = $getUsers->getOrDefault('email', null);

        return \array_filter($this->cachedUsers, function (array $user) use ($usernameFilter, $emailFilter): bool {
            return (null === $usernameFilter || $user['username'] === $usernameFilter)
                && (null === $emailFilter || $user['email'] === $emailFilter);
        });
    }
}
