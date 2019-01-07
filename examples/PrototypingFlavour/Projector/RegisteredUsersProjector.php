<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\PrototypingFlavour\Projector;

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\Exception\RuntimeException;
use EventEngine\Messaging\Message;
use EventEngine\Projecting\Projector;
use EventEngineExample\PrototypingFlavour\Messaging\Event;

final class RegisteredUsersProjector implements Projector
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    public function __construct(DocumentStore $documentStore)
    {
        $this->documentStore = $documentStore;
    }

    public function handle(string $projectionVersion, string $projectionName, Message $event): void
    {
        switch ($event->messageName()) {
            case Event::USER_WAS_REGISTERED:
                $this->documentStore->addDoc($projectionName . '_' . $projectionVersion, $event->get('userId'), [
                    'userId' => $event->get('userId'),
                    'username' => $event->get('username'),
                    'email' => $event->get('email'),
                ]);
                break;
            default:
                throw new RuntimeException('Cannot handle event: ' . $event->messageName());
        }
    }

    public function prepareForRun(string $projectionVersion, string $projectionName): void
    {
        $this->documentStore->addCollection($projectionName . '_' . $projectionVersion);
    }

    public function deleteReadModel(string $projectionVersion, string $projectionName): void
    {
        $this->documentStore->dropCollection($projectionName . '_' . $projectionVersion);
    }
}
