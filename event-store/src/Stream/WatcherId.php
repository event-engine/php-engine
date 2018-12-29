<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\EventStore\Stream;

final class WatcherId
{
    private $watchrId;

    public static function fromString(string $watchrId): self
    {
        return new self($watchrId);
    }

    private function __construct(string $watchrId)
    {
        $this->watchrId = $watchrId;
    }

    public function toString(): string
    {
        return $this->watchrId;
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->watchrId === $other->watchrId;
    }

    public function __toString(): string
    {
        return $this->watchrId;
    }
}
