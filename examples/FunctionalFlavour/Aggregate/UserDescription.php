<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\FunctionalFlavour\Aggregate;

use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use EventEngineExample\FunctionalFlavour\Api\Command;
use EventEngineExample\FunctionalFlavour\Api\Event;
use EventEngineExample\FunctionalFlavour\Command\ChangeEmail;
use EventEngineExample\FunctionalFlavour\Command\ChangeUsername;
use EventEngineExample\FunctionalFlavour\Command\ConnectWithFriend;
use EventEngineExample\FunctionalFlavour\Command\RegisterUser;
use EventEngineExample\FunctionalFlavour\ContextProvider\MatchingHobbiesProvider;
use EventEngineExample\FunctionalFlavour\ContextProvider\SocialPlatformProvider;
use EventEngineExample\FunctionalFlavour\Event\EmailChanged;
use EventEngineExample\FunctionalFlavour\Event\FriendConnected;
use EventEngineExample\FunctionalFlavour\Event\UsernameChanged;
use EventEngineExample\FunctionalFlavour\Event\UserRegistered;
use EventEngineExample\FunctionalFlavour\Event\UserRegistrationFailed;
use EventEngineExample\FunctionalFlavour\Query\GetUser;
use EventEngineExample\FunctionalFlavour\Resolver\GetUserResolver;

/**
 * Class UserDescription
 *
 * Tell EventMachine how to handle commands with aggregates, which events are yielded by the handle methods
 * and how to apply the yielded events to the aggregate state.
 *
 * Please note:
 * UserDescription uses closures. It is the fastest and most readable way of describing
 * aggregate behaviour BUT closures cannot be serialized/cached.
 * So the closure style is useful for learning and prototyping but if you want to use Event Machine for
 * production, you should consider using a cacheable description like illustrated with CacheableUserDescription.
 * Also see EventMachine::cacheableConfig() which throws an exception if it detects usage of closure
 * The returned array can be used to call EventMachine::fromCachedConfig(). You can json_encode the config and store it
 * in a json file.
 *
 * @package EventEngineExample\Aggregate
 */
final class UserDescription implements EventEngineDescription
{
    public const IDENTIFIER = 'userId';
    public const IDENTIFIER_ALIAS = 'user_id';
    public const USERNAME = 'username';
    public const EMAIL = 'email';
    public const FRIEND = 'friend';

    const STATE_CLASS = UserState::class;

    public static function describe(EventEngine $eventEngine): void
    {
        self::describeRegisterUser($eventEngine);
        self::describeChangeUsername($eventEngine);
        self::describeChangeEmail($eventEngine);
        self::describeConnectWithFriend($eventEngine);
    }

    private static function describeRegisterUser(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::REGISTER_USER)
            ->withNew(Aggregate::USER)
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
            ->withExisting(Aggregate::USER)
            // This time we handle command with existing aggregate, hence we get current user state injected
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

    private static function describeChangeEmail(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::CHANGE_EMAIL)
            ->withExisting(Aggregate::USER)
            ->identifiedBy(self::IDENTIFIER_ALIAS)
            ->handle(function (UserState $user, ChangeEmail $changeEmail) {
                yield new EmailChanged([
                    UserDescription::IDENTIFIER_ALIAS => $user->userId,
                    'oldMail' => $user->email,
                    'newMail' => $changeEmail->email,
                ]);
            })
            ->recordThat(Event::EMAIL_WAS_CHANGED)
            ->apply(function (UserState $user, EmailChanged $emailWasChanged) {
                $user->email = $emailWasChanged->newMail;

                return $user;
            });
    }

    private static function describeConnectWithFriend(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::CONNECT_WITH_FRIEND)
            ->withExisting(Aggregate::USER)
            ->provideContext(SocialPlatformProvider::class)
            ->provideContext(MatchingHobbiesProvider::class)
            ->provideService(GetUserResolver::class)
            ->handle(function (UserState $user, ConnectWithFriend $command, string $socialPlatform, array $matchingHobbies, GetUserResolver $resolver): \Generator
            {
                //Check that friend exists using a resolver dependency
                $friend = $resolver->resolve(new GetUser([UserDescription::IDENTIFIER => $command->friend]));

                yield new FriendConnected([
                    UserDescription::IDENTIFIER => $user->userId,
                    UserDescription::FRIEND => $friend[UserDescription::IDENTIFIER],
                    //Context providers can provide additional data that is not part of current aggregate state or command
                    'socialPlatform' => $socialPlatform,
                    'matchingHobbies' => $matchingHobbies,
                ]);
            })
            ->recordThat(Event::FRIEND_CONNECTED)
            ->apply(function (UserState $user, FriendConnected $event): UserState
            {
                $user->friends[] = $event->friend;
                return $user;
            });
    }

    private function __construct()
    {
        //static class only
    }
}
