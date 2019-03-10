<?php
/**
 * This file is part of the event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Util;

use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\Message;

final class VariableType
{
    public static function determine($var): string
    {
        if(\is_object($var)) {
            if($var instanceof Message) {
                return $var->messageName();
            }

            return \get_class($var);
        }

        if(\is_array($var)) {
            $messageName = $var[0] ?? null;
            $payload = $var[1] ?? null;
            $metadata = $var[2] ?? null;
            if(\is_string($messageName) && \is_array($payload)) {
                return "[$messageName, {payload...}" . ((\is_array($metadata)? ", {metadata...}" : "")) . "]";
            }
        }

        return \gettype($var);
    }

    public static function convertCommandDispatchResultOrMessageArrayToString($result): string
    {
        if($result instanceof CommandDispatchResult) {
            return self::convertCommandDispatchResultToString($result);
        }

        if(\is_array($result)) {
            $result = \array_map(function ($command) {
                return VariableType::determine($command);
            }, $result);

            return implode(", ", $result);
        }

        return self::determine($result);
    }

    public static function convertCommandDispatchResultToString(CommandDispatchResult $result): string
    {
        $info = [
            'dispatchedCommand' => $result->dispatchedCommand()->messageName(),
            'effectedAggregate' => $result->effectedAggregateId(),
            'recordedEvents' => \array_map(function (Message $event) {
                return $event->messageName();
            }, $result->recordedEvents())
        ];

        return \json_encode($info);
    }
}
