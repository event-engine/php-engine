<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\FunctionalFlavour\Process;

use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use EventEngineExample\FunctionalFlavour\Api\Command;
use EventEngineExample\FunctionalFlavour\Api\Event;
use EventEngineExample\FunctionalFlavour\Command\ChangeUsername;
use EventEngineExample\FunctionalFlavour\Command\RegisterUser;
use EventEngineExample\FunctionalFlavour\Event\UsernameChanged;
use EventEngineExample\FunctionalFlavour\Event\UserRegistered;
use EventEngineExample\FunctionalFlavour\Event\UserRegistrationFailed;

/**
 * Class UserDescription
 *
 * Tell EventEngine how to handle commands with processes, which events are yielded by the handle methods
 * and how to apply the yielded events to the process state.
 *
 * Please note:
 * UserDescription uses closures. It is the fastest and most readable way of describing
 * process behaviour BUT closures cannot be serialized/cached.
 * So the closure style is useful for learning and prototyping but if you want to use Event Machine for
 * production, you should consider using a cacheable description like illustrated with CacheableUserDescription.
 * Also see EventMachine::cacheableConfig() which throws an exception if it detects usage of closure
 * The returned array can be used to call EventMachine::fromCachedConfig(). You can json_encode the config and store it
 * in a json file.
 *
 * @package EventEngineExample\Process
 */
final class UserDescription implements EventEngineDescription
{
    public const IDENTIFIER = 'userId';
    public const USERNAME = 'username';
    public const EMAIL = 'email';

    const STATE_CLASS = UserState::class;

    public static function describe(EventEngine $eventEngine): void
    {
        self::describeRegisterUser($eventEngine);
        self::describeChangeUsername($eventEngine);
    }

    private static function describeRegisterUser(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::REGISTER_USER)
            ->withNew(Process::USER)
            ->identifiedBy(self::IDENTIFIER)
            // Note: Our custom command is passed to the function
            ->handle(function (RegisterUser $command) {
                //We can return a custom event
                if ($command->shouldFail) {
                    yield new UserRegistrationFailed([self::IDENTIFIER => $command->userId]);

                    return;
                }

                yield new UserRegistered([
                    'userId' => $command->userId,
                    'username' => $command->username,
                    'email' => $command->email,
                ]);
            })
            ->recordThat(Event::USER_WAS_REGISTERED)
            // The custom event is passed to the apply function
            ->apply(function (UserRegistered $event) {
                return new UserState((array) $event);
            })
            ->orRecordThat(Event::USER_REGISTRATION_FAILED)
            ->apply(function (UserRegistrationFailed $failed): UserState {
                return new UserState([self::IDENTIFIER => $failed->userId, 'failed' => true]);
            });
    }

    private static function describeChangeUsername(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::CHANGE_USERNAME)
            ->withExisting(Process::USER)
            // This time we handle command with existing process, hence we get current user state injected
            ->handle(function (UserState $user, ChangeUsername $changeUsername) {
                yield new UsernameChanged([
                    self::IDENTIFIER => $user->userId,
                    'oldName' => $user->username,
                    'newName' => $changeUsername->username,
                ]);
            })
            ->recordThat(Event::USERNAME_WAS_CHANGED)
            // Same here, UsernameChanged is NOT the first event, so current user state is passed
            ->apply(function (UserState $user, UsernameChanged $event) {
                $user->username = $event->newName;

                return $user;
            });
    }

    private function __construct()
    {
        //static class only
    }
}
