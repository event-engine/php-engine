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
 * Class ProcessType
 *
 * Processes are grouped by type.
 *
 * @package EventEngine\Process
 */
final class ProcessType
{
    private $type;

    public static function fromString(string $type): self
    {
        return new self($type);
    }

    private function __construct(string $type)
    {
        $this->type = $type;
    }

    public function toString(): string
    {
        return $this->type;
    }

    public function equals($other): bool
    {
        if(!$other instanceof self) {
            return false;
        }

        return $this->type === $other->type;
    }

    public function __toString(): string
    {
        return $this->type;
    }
}
