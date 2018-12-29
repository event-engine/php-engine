<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\FunctionalFlavour\Projector;

use Prooph\EventMachine\Exception\RuntimeException;
use Prooph\EventMachine\Persistence\DocumentStore;
use Prooph\EventMachine\Projecting\CustomEventProjector;
use EventEngineExample\FunctionalFlavour\Event\UserRegistered;

final class RegisteredUsersProjector implements CustomEventProjector
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    public function __construct(DocumentStore $documentStore)
    {
        $this->documentStore = $documentStore;
    }

    public function handle(string $appVersion, string $projectionName, $event): void
    {
        switch (\get_class($event)) {
            case UserRegistered::class:
                /** @var UserRegistered $event */
                $this->documentStore->addDoc($projectionName . '_' . $appVersion, $event->userId, [
                    'userId' => $event->userId,
                    'username' => $event->username,
                    'email' => $event->email,
                ]);
                break;
            default:
                throw new RuntimeException('Cannot handle event: ' . $event->messageName());
        }
    }

    public function prepareForRun(string $appVersion, string $projectionName): void
    {
        $this->documentStore->addCollection($projectionName . '_' . $appVersion);
    }

    public function deleteReadModel(string $appVersion, string $projectionName): void
    {
        $this->documentStore->dropCollection($projectionName . '_' . $appVersion);
    }
}
