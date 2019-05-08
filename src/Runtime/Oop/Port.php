<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Runtime\Oop;

interface Port
{
    /**
     * @param string $aggregateType
     * @param callable $aggregateFactory
     * @param $customCommand
     * @param array $contextServices
     * @return mixed Created aggregate
     */
    public function callAggregateFactory(string $aggregateType, callable $aggregateFactory, $customCommand, ...$contextServices);

    /**
     * @param mixed $aggregate
     * @param mixed $customCommand
     * @param array $contextServices
     */
    public function callAggregateWithCommand($aggregate, $customCommand, ...$contextServices): void;

    /**
     * @param mixed $aggregate
     * @return array of custom events
     */
    public function popRecordedEvents($aggregate): array;

    /**
     * @param mixed $aggregate
     * @param mixed $customEvent
     */
    public function applyEvent($aggregate, $customEvent): void;

    /**
     * @param mixed $aggregate
     * @return array
     */
    public function serializeAggregate($aggregate): array;

    /**
     * @param string $aggregateType
     * @param iterable $events history
     * @return mixed Aggregate instance
     */
    public function reconstituteAggregate(string $aggregateType, iterable $events);

    /**
     * @param string $aggregateType
     * @param array $state
     * @param int $version
     * @return mixed Aggregate instance
     */
    public function reconstituteAggregateFromStateArray(string $aggregateType, array $state, int $version);
}
