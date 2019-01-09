<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\EventStore;

use EventEngine\EventStore\Stream\Name;
use EventEngine\Messaging\GenericEvent;
use EventEngine\Process\Pid;
use EventEngine\Process\ProcessType;

interface EventStore
{
    public function createStream(Name $streamName): void;

    public function deleteStream(Name $streamName): void;

    public function appendTo(Name $streamName, GenericEvent ...$events): void;

    /**
     * @param Name $streamName
     * @param ProcessType $processType
     * @param Pid $processId
     * @param int $minVersion
     * @return \Iterator GenericEvent[]
     */
    public function loadProcessEvents(Name $streamName, ProcessType $processType, Pid $processId, int $minVersion = 1): \Iterator;

    /**
     * @param Name $streamName
     * @param string $correlationId
     * @return \Iterator GenericEvent[]
     */
    public function loadEventsByCorrelationId(Name $streamName, string $correlationId): \Iterator;

    /**
     * @param Name $streamName
     * @param string $causationId
     * @return \Iterator GenericEvent[]
     */
    public function loadEventsByCausationId(Name $streamName, string $causationId): \Iterator;
}
