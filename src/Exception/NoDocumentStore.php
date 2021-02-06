<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Exception;

class NoDocumentStore extends RuntimeException
{
    public static function forAggregate(string $aggregateType, string $aggregateId): NoDocumentStore
    {
        return new self(
            sprintf(
                'No document store or multi model store configured. Can not update state of "%s" %s',
                $aggregateType,
                $aggregateId
            )
        );
    }
}