<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Schema\MessageBox;

final class EventMap
{
    private $events;

    public static function fromEventEngineMap(array $map): self
    {
        $events = [];

        foreach ($map as $name => $payloadSchema) {
            $events[] = new Event($name, $payloadSchema);
        }

        return new self(...$events);
    }

    private function __construct(Event ...$events)
    {
        foreach ($events as $event) $this->events[$event->name()] = $event;
    }

    /**
     * @return Event[]
     */
    public function events(): array
    {
        return $this->events;
    }
}
