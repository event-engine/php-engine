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
use EventEngine\Messaging\MessageFactory;
use EventEngineExample\PrototypingFlavour\Messaging\Command;
use EventEngineExample\PrototypingFlavour\Messaging\Event;
use EventEngineExample\PrototypingFlavour\Resolver\GetUserResolver;

/**
 * Class CacheableUserDescription
 *
 * CacheableUserDescription illustrates an alternative way to describe aggregate behaviour. Advantage of the shown style
 * is that you can make use of EventMachine::compileCacheableConfig(). See note of UserDescription for more details.
 *
 * @package EventEngineExample\Aggregate
 */
final class CacheableUserDescription implements EventEngineDescription
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
        self::describeDoNothing($eventEngine);
        self::describeChangeEmail($eventEngine);
        self::describeConnectWithFriend($eventEngine);
    }

    private static function describeRegisterUser(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::REGISTER_USER)
            ->withNew(Aggregate::USER)
            ->identifiedBy(self::IDENTIFIER)
            //Use callable array syntax, so that event machine config can be cached (not possible with closures)
            //A modern IDE like PHPStorm is able to resolve this reference so that it is found by usage/refactoring look ups
            ->handle([CachableUserFunction::class, 'registerUser'])
            ->recordThat(Event::USER_WAS_REGISTERED)
            ->apply([CachableUserFunction::class, 'whenUserWasRegistered'])
            ->orRecordThat(Event::USER_REGISTRATION_FAILED)
            ->apply([CachableUserFunction::class, 'whenUserRegistrationFailed']);
    }

    private static function describeChangeUsername(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::CHANGE_USERNAME)
            ->withExisting(Aggregate::USER)
            ->handle([CachableUserFunction::class, 'changeUsername'])
            ->recordThat(Event::USERNAME_WAS_CHANGED)
            ->apply([CachableUserFunction::class, 'whenUsernameWasChanged']);
    }

    private static function describeChangeEmail(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::CHANGE_EMAIL)
            ->withExisting(Aggregate::USER)
            ->identifiedBy(CacheableUserDescription::IDENTIFIER_ALIAS)
            ->handle([CachableUserFunction::class, 'changeEmail'])
            ->recordThat(Event::EMAIL_WAS_CHANGED)
            ->apply([CachableUserFunction::class, 'whenEmailWasChanged']);
    }

    private static function describeConnectWithFriend(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::CONNECT_WITH_FRIEND)
            ->withExisting(Aggregate::USER)
            ->provideService(GetUserResolver::class)
            ->provideService(MessageFactory::class)
            ->handle([CachableUserFunction::class, 'connectWithFriend'])
            ->recordThat(Event::FRIEND_CONNECTED)
            ->apply([CachableUserFunction::class, 'whenFriendConnected']);
    }

    private static function describeDoNothing(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::DO_NOTHING)
            ->withExisting(Aggregate::USER)
            ->handle([CachableUserFunction::class, 'doNothing'])
            ->orRecordThat(Event::USERNAME_WAS_CHANGED)
            ->apply([CachableUserFunction::class, 'whenUsernameWasChanged']);
    }

    private function __construct()
    {
        //static class only
    }
}
