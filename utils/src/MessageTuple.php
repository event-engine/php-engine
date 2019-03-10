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

final class MessageTuple
{
    public static function normalize(array $tuple): array
    {
        $messageName = $tuple[0] ?? null;
        $payload = $tuple[1] ?? null;
        $metadata = $tuple[2] ?? [];

        if(! \is_string($messageName) || ! \is_array($payload) || ! \is_array($metadata)) {
            throw new RuntimeException("Cannot normalize message tuple. Invalid array given: " . \json_encode($tuple));
        }

        return [
            $messageName,
            $payload,
            $metadata
        ];
    }
}
