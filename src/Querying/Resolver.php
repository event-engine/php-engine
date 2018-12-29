<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Querying;

use EventEngine\Messaging\Message;

interface Resolver
{
    /**
     * @param Message $query
     * @return \Generator
     */
    public function resolve(Message $query): \Generator;
}
