<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Persistence;

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\EventStore\EventStore;

final class ComposedMultiModelStore implements MultiModelStore
{
    use InnerEventStore, InnerDocumentStore, InnerConnection;

    public function __construct(
        TransactionalConnection $connection,
        EventStore $eventStore,
        DocumentStore $documentStore
    ) {
        $this->connection = $connection;
        $this->eventStore = $eventStore;
        $this->documentStore = $documentStore;
    }
}