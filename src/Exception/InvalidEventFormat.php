<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Exception;

use EventEngine\Messaging\Message;
use EventEngine\Process\ProcessType;
use EventEngine\Util\VariableType;

final class InvalidEventFormat extends InvalidArgumentException
{
    public static function invalidEvent(ProcessType $processType, Message $command): self
    {
        return new self(
            \sprintf(
                'Event returned by process of type %s while handling command %s does not have the format [string eventName, array payload]!',
                $processType->toString(),
                $command->messageName()
            )
        );
    }

    public static function invalidMetadata($metadata, ProcessType $processType, Message $command): self
    {
        return new self(
            \sprintf(
                'Event returned by process of type %s while handling command %s contains additional metadata but metadata type is not array. Detected type is: %s',
                $processType->toString(),
                $command->messageName(),
                VariableType::determine($metadata)
            )
        );
    }
}
