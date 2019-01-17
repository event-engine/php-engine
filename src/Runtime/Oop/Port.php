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
     * @param string $processType
     * @param callable $processFactory
     * @param $customCommand
     * @param null|mixed $context
     * @return mixed Created process
     */
    public function callProcessFactory(string $processType, callable $processFactory, $customCommand, $context = null);

    /**
     * @param mixed $process
     * @param mixed $customCommand
     * @param null|mixed $context
     */
    public function callProcessWithCommand($process, $customCommand, $context = null): void;

    /**
     * @param mixed $process
     * @return array of custom events
     */
    public function popRecordedEvents($process): array;

    /**
     * @param mixed $process
     * @param mixed $customEvent
     */
    public function applyEvent($process, $customEvent): void;

    /**
     * @param mixed $process
     * @return array
     */
    public function serializeProcess($process): array;

    /**
     * @param string $processType
     * @param iterable $events history
     * @return mixed Process instance
     */
    public function reconstituteProcess(string $processType, iterable $events);

    /**
     * @param string $processType
     * @param array $state
     * @return mixed Process instance
     */
    public function reconstituteProcessFromStateArray(string $processType, array $state);
}
