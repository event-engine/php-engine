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

final class CommandDispatchResult
{
    /**
     * @var Message
     */
    private $dispatchedCommand;

    /**
     * @var string
     */
    private $pid;

    /**
     * @var Message[]
     */
    private $recordedEvents;

    public static function forCommandHandledByProcess(Message $dispatchedCommand, string $pid, Message ...$recordedEvents): self
    {
        return new self($dispatchedCommand, $pid, ...$recordedEvents);
    }

    public static function forCommandHandledByPreProcessor(Message $dispatchedCommand): self
    {
        return new self($dispatchedCommand);
    }

    private function __construct(Message $dispatchedCommand, string $pid = null, Message ...$recordedEvents)
    {
        $this->dispatchedCommand = $dispatchedCommand;
        $this->pid = $pid;
        $this->recordedEvents = $recordedEvents;
    }

    /**
     * @return Message
     */
    public function dispatchedCommand(): Message
    {
        return $this->dispatchedCommand;
    }

    /**
     * @return string|null
     */
    public function pid(): ?string
    {
        return $this->pid;
    }

    /**
     * @return Message[]
     */
    public function recordedEvents(): array
    {
        return $this->recordedEvents;
    }
}
