<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Prooph\V7\EventStore;

use EventEngine\DocumentStore\InMemoryDocumentStore;
use EventEngine\Persistence\InMemoryConnection;
use EventEngine\Persistence\InnerConnection;
use EventEngine\Persistence\InnerDocumentStore;
use EventEngine\Persistence\InnerEventStore;
use EventEngine\Persistence\MultiModelStore;

final class InMemoryMultiModelStore implements MultiModelStore
{
    use InnerConnection, InnerEventStore, InnerDocumentStore;

    public static function fromConnection(InMemoryConnection $connection): self
    {
        $self = new self();

        $self->connection = $connection;
        $self->eventStore = new ProophEventStore(new InMemoryEventStore($connection));
        $self->documentStore = new InMemoryDocumentStore($connection);

        return $self;
    }
}
