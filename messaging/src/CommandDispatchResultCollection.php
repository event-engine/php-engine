<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Messaging;

final class CommandDispatchResultCollection
{
    /**
     * @var CommandDispatchResult[]
     */
    private $results;

    public function __construct(CommandDispatchResult ...$results)
    {
        $this->results = $results;
    }

    public function toIterator(): iterable
    {
        return $this->results;
    }

    public function push(CommandDispatchResult $result): void
    {
        $this->results[] = $result;
    }
}
