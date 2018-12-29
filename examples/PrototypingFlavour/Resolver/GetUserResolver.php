<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\PrototypingFlavour\Resolver;

use EventEngine\Messaging\Message;
use EventEngine\Querying\Resolver;

final class GetUserResolver implements Resolver
{
    /**
     * @var array
     */
    private $cachedUserState;

    public function __construct(array $cachedUserState)
    {
        $this->cachedUserState = $cachedUserState;
    }

    /**
     * @param Message $query
     * @return \Generator
     */
    public function resolve(Message $getUser): \Generator
    {
        if ($this->cachedUserState['userId'] === $getUser->get('userId')) {
            yield $this->cachedUserState;
            return;
        }

        new \RuntimeException('User not found');
    }
}
