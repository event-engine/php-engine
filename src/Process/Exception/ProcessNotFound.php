<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Process\Exception;

use EventEngine\Process\Pid;
use EventEngine\Process\ProcessType;

final class ProcessNotFound extends \RuntimeException implements EventEngineException
{
    public static function with(ProcessType $processType, Pid $pid): self
    {
        return new self(\sprintf(
            'Process of type %s with pid %s not found.',
            $processType->toString(),
            $pid->toString()
        ), 404);
    }
}
