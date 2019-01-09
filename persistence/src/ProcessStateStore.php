<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Persistence;

use EventEngine\Process\Exception\ProcessNotFound;
use EventEngine\Exception\InvalidArgumentException;
use EventEngine\Process\Pid;
use EventEngine\Process\ProcessType;

interface ProcessStateStore
{
    /**
     * @param ProcessType $processType
     * @param Pid $processId
     * @param int|null $expectedVersion
     * @return mixed State of the process
     * @throws ProcessNotFound
     */
    public function loadProcessState(ProcessType $processType, Pid $processId, int $expectedVersion = null);
}
