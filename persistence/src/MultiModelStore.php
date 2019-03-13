<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Persistence;

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\EventStore\EventStore;

interface MultiModelStore extends EventStore, DocumentStore
{
    public const STORAGE_MODE_EVENTS = 'mode_e';
    public const STORAGE_MODE_STATE = 'mode_s';
    public const STORAGE_MODE_EVENTS_AND_STATE = 'mode_e_s';

    public function connection(): TransactionalConnection;
}
