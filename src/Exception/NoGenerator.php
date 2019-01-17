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

final class NoGenerator extends InvalidArgumentException
{
    public static function forProcessTypeAndCommand(string $processType, Message $command): self
    {
        return new self('Expected processFunction to be of type Generator. ' .
            'Did you forget the yield keyword in your command handler?' .
            "Tried to handle command {$command->messageName()} for process {$processType}"
        );
    }
}
