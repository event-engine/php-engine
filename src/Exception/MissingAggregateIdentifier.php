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

final class MissingAggregateIdentifier extends InvalidArgumentException
{
    public static function inCommand(Message $command, string $aggregateIdPayloadKey): self
    {
        return new self(\sprintf(
            'Missing aggregate identifier %s in payload of command %s',
            $aggregateIdPayloadKey,
            $command->messageName()
        ));
    }
}
