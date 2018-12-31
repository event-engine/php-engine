<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Util;

use EventEngine\Exception\RuntimeException;
use EventEngine\Messaging\CommandDispatchResult;

final class Await
{
    public static function commandDispatchResult(\Generator $generator): CommandDispatchResult
    {
        foreach ($generator as $result) {
            if($result instanceof CommandDispatchResult) return $result;
        }

        throw new RuntimeException("No " . CommandDispatchResult::class . " yielded!");
    }

    public static function oneResult(\Generator $generator)
    {
        foreach ($generator as $result) {
            return $result;
        }

        return null;
    }

    public static function lastResult(\Generator $generator)
    {
        $lastResult = null;

        foreach ($generator as $result) {
            $lastResult = $result;
        }

        return $lastResult;
    }

    public static function manyResults(\Generator $generator): array
    {
        $results = [];

        foreach ($generator as $result) {
            $results[] = $result;
        }

        return $results;
    }

    public static function join(\Generator $generator): void
    {
        foreach ($generator as $result) {}
    }
}
