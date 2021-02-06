<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Aggregate\Exception;

final class AggregateNotFound extends \RuntimeException implements EventEngineException
{
    public static function with(string $aggregateType, string $aggregateId): self
    {
        return new self(\sprintf(
            'Aggregate of type %s with id %s not found.',
            $aggregateType,
            $aggregateId
        ), 404);
    }
}
