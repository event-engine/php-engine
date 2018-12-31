<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Prooph\V7\EventStore\Exception;

use EventEngine\Util\VariableType;
use Prooph\EventStore\EventStore;
use Prooph\EventStore\TransactionalEventStore;

final class NoTransactionalStore extends \RuntimeException
{
    protected $code = 500;

    public static function withProophEventStore(EventStore $eventStore): self
    {
        return new self(sprintf(
            "Unable to manage transaction. Given prooph event store %s does not implement %s",
            VariableType::determine($eventStore),
            TransactionalEventStore::class
        ));
    }
}
