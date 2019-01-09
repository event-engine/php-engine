<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Process;

/**
 * Class Pid
 *
 * The Pid identifies a process. Pids MUST be unique across a ProcessType.
 * It's recommended to use globally unique Pids for example UUIDs.
 *
 * @package EventEngine\Process
 */
final class Pid
{
    private $pid;

    public static function fromString(string $pid): self
    {
        return new self($pid);
    }

    private function __construct(string $pid)
    {
        $this->pid = $pid;
    }

    public function toString(): string
    {
        return $this->pid;
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->pid === $other->pid;
    }

    public function __toString(): string
    {
        return $this->pid;
    }
}
