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
    private $effectedAggregateId;

    /**
     * @var Message[]
     */
    private $recordedEvents;

    public static function forCommandHandledByAggregate(Message $dispatchedCommand, string $effectedAggregateId, Message ...$recordedEvents): self
    {
        return new self($dispatchedCommand, $effectedAggregateId, ...$recordedEvents);
    }

    public static function forCommandHandledByPreProcessor(Message $dispatchedCommand): self
    {
        return new self($dispatchedCommand);
    }

    private function __construct(Message $dispatchedCommand, string $effectedAggregateId = null, Message ...$recordedEvents)
    {
        $this->dispatchedCommand = $dispatchedCommand;
        $this->effectedAggregateId = $effectedAggregateId;
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
    public function effectedAggregateId(): ?string
    {
        return $this->effectedAggregateId;
    }

    /**
     * @return Message[]
     */
    public function recordedEvents(): array
    {
        return $this->recordedEvents;
    }
}
