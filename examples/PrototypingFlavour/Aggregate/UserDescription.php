<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\PrototypingFlavour\Aggregate;

use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageFactory;
use EventEngineExample\PrototypingFlavour\ContextProvider\MatchingHobbiesProvider;
use EventEngineExample\PrototypingFlavour\ContextProvider\SocialPlatformProvider;
use EventEngineExample\PrototypingFlavour\Messaging\Command;
use EventEngineExample\PrototypingFlavour\Messaging\Event;
use EventEngineExample\PrototypingFlavour\Messaging\Query;
use EventEngineExample\PrototypingFlavour\Resolver\GetUserResolver;

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
    const IDENTIFIER = 'userId';
    const IDENTIFIER_ALIAS = 'user_id';
    const USERNAME = 'username';
    const EMAIL = 'email';
    const FRIEND = 'friend';

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
            // Every command for that aggregate SHOULD include the identifier property specified here
            // If not called, identifier defaults to "id"
            ->identifiedBy(self::IDENTIFIER)
            // If command is handled with a new aggregate no state is passed only the command
            ->handle(function (Message $registerUser) {
                //We just turn the command payload into event payload by yielding an event tuple
                yield [Event::USER_WAS_REGISTERED, $registerUser->payload()];
            })
            ->recordThat(Event::USER_WAS_REGISTERED)
            // Apply callback of the first recorded event don't get aggregate state injected
            // what you return in an apply method will be passed to the next pair of handle & apply methods as aggregate state
            // you can use anything for aggregate state - we use a simple class with public properties
            ->apply(function (Message $userWasRegistered) {
                $user = new UserState();
                $user->userId = $userWasRegistered->payload()[self::IDENTIFIER];
                $user->username = $userWasRegistered->payload()['username'];
                $user->email = $userWasRegistered->payload()['email'];

                return $user;
            });
    }

    private static function describeChangeUsername(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::CHANGE_USERNAME)
            ->withExisting(Aggregate::USER)
            // This time we handle command with existing aggregate, hence we get current user state injected
            ->handle(function (UserState $user, Message $changeUsername) {
                yield [Event::USERNAME_WAS_CHANGED, [
                    self::IDENTIFIER => $user->userId,
                    'oldName' => $user->username,
                    'newName' => $changeUsername->payload()['username'],
                ]];
            })
            ->recordThat(Event::USERNAME_WAS_CHANGED)
            // Same here, UsernameWasChanged is NOT the first event, so current user state is injected
            ->apply(function (UserState $user, Message $usernameWasChanged) {
                $user->username = $usernameWasChanged->payload()['newName'];

                return $user;
            });
    }

    private static function describeChangeEmail(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::CHANGE_EMAIL)
            ->withExisting(Aggregate::USER)
            ->identifiedBy(UserDescription::IDENTIFIER_ALIAS)
            ->handle(function (UserState $user, Message $changeEmail) {
                yield [Event::EMAIL_WAS_CHANGED, [
                    UserDescription::IDENTIFIER_ALIAS => $user->userId,
                    'oldMail' => $user->email,
                    'newMail' => $changeEmail->get(UserDescription::EMAIL),
                ]];
            })
            ->recordThat(Event::EMAIL_WAS_CHANGED)
            ->apply(function (UserState $user, Message $emailWasChanged) {
                $user->email = $emailWasChanged->get('newMail');

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
            ->provideService(MessageFactory::class)
            ->handle(function (UserState $user, Message $connectWithFriend, string $socialPlatform, array $matchingHobbies, GetUserResolver $resolver, MessageFactory $messageFactory): \Generator
            {
                $friendId = $connectWithFriend->get(CacheableUserDescription::FRIEND);

                //Check that friend exists using a resolver dependency
                $friend = $resolver->resolve($messageFactory->createMessageFromArray(Query::GET_USER, [
                    'payload' => [CacheableUserDescription::IDENTIFIER => $friendId]
                ]));

                yield [Event::FRIEND_CONNECTED, [
                    CacheableUserDescription::IDENTIFIER => $user->userId,
                    CacheableUserDescription::FRIEND => $friend[CacheableUserDescription::IDENTIFIER],
                    //Context providers can provide additional data that is not part of current aggregate state or command
                    'socialPlatform' => $socialPlatform,
                    'matchingHobbies' => $matchingHobbies,
                ]];
            })
            ->recordThat(Event::FRIEND_CONNECTED)
            ->apply(function (UserState $user, Message $friendConnected): UserState
            {
                $user->friends[] = $friendConnected->get(CacheableUserDescription::FRIEND);
                return $user;
            });
    }

    private function __construct()
    {
        //static class only
    }
}
