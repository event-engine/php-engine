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

use EventEngine\Exception\RuntimeException;
use EventEngine\Util\VariableType;

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

    public function toArray(): iterable
    {
        return $this->results;
    }

    /**
     * @param CommandDispatchResult|CommandDispatchResultCollection $result
     * @return CommandDispatchResultCollection
     */
    public function push($result): CommandDispatchResultCollection
    {
        $copy = clone $this;

        if($result instanceof CommandDispatchResult) {
            $copy->results[] = $result;
            return $copy;
        }

        if($result instanceof CommandDispatchResultCollection) {
            foreach ($result->toArray() as $item) {
                $copy->results[] = $item;
            }

            return $copy;
        }

        if(null === $result) {
            return $copy;
        }

        throw new RuntimeException("Cannot push result to " . __CLASS__ . ". Got wrong type: " . VariableType::determine($result));
    }
}
