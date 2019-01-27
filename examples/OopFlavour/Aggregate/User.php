<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\OopFlavour\Aggregate;

use EventEngine\Exception\RuntimeException;
use EventEngineExample\FunctionalFlavour\Command\ChangeEmail;
use EventEngineExample\FunctionalFlavour\Command\ChangeUsername;
use EventEngineExample\FunctionalFlavour\Command\RegisterUser;
use EventEngineExample\FunctionalFlavour\Event\EmailChanged;
use EventEngineExample\FunctionalFlavour\Event\UsernameChanged;
use EventEngineExample\FunctionalFlavour\Event\UserRegistered;
use EventEngineExample\FunctionalFlavour\Event\UserRegistrationFailed;

final class User
{
    public const TYPE = 'User';

    private $userId;

    private $username;

    private $email;

    private $failed;

    private $recordedEvents = [];

    public static function reconstituteFromHistory(iterable $history): self
    {
        $self = new self();
        foreach ($history as $event) {
            $self->apply($event);
        }

        return $self;
    }

    public static function reconstituteFromState(array $state): self
    {
        $self = new self();

        $self->userId = $state['userId'];
        $self->username = $state['username'];
        $self->email = $state['email'];
        $self->failed = $state['failed'];

        return $self;
    }

    public static function register(RegisterUser $command): self
    {
        $self = new self();

        if ($command->shouldFail) {
            $self->recordThat(new UserRegistrationFailed([
                'userId' => $command->userId,
            ]));

            return $self;
        }

        $self->recordThat(new UserRegistered([
            'userId' => $command->userId,
            'username' => $command->username,
            'email' => $command->email,
        ]));

        return $self;
    }

    public function changeName(ChangeUsername $command): void
    {
        $this->recordThat(new UsernameChanged([
            'userId' => $this->userId,
            'oldName' => $this->username,
            'newName' => $command->username,
        ]));
    }

    public function changeEmail(ChangeEmail $command): void
    {
        $this->recordThat(new EmailChanged([
            'user_id' => $this->userId,
            'oldMail' => $this->email,
            'newMail' => $command->email,
        ]));
    }

    public function popRecordedEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    public function apply($event): void
    {
        switch (\get_class($event)) {
            case UserRegistered::class:
                /** @var UserRegistered $event */
                $this->userId = $event->userId;
                $this->username = $event->username;
                $this->email = $event->email;
                break;
            case UserRegistrationFailed::class:
                /** @var UserRegistrationFailed $event */
                $this->userId = $event->userId;
                $this->failed = true;
                break;
            case UsernameChanged::class:
                /** @var UsernameChanged $event */
                $this->username = $event->newName;
                break;
            case EmailChanged::class:
                /** @var EmailChanged $event */
                $this->email = $event->newMail;
                break;
            default:
                throw new RuntimeException('Unknown event: ' . \get_class($event));
        }
    }

    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'username' => $this->username,
            'email' => $this->email,
            'failed' => $this->failed,
        ];
    }

    private function recordThat($event): void
    {
        $this->recordedEvents[] = $event;
    }

    private function __construct()
    {
    }
}
