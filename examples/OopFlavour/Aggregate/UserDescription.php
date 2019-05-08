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

use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use EventEngine\Runtime\Oop\FlavourHint;
use EventEngineExample\FunctionalFlavour\Api\Command;
use EventEngineExample\FunctionalFlavour\Api\Event;
use EventEngineExample\FunctionalFlavour\Resolver\GetUserResolver;

/**
 * Class UserDescription
 *
 * @package EventEngineExample\Aggregate
 */
final class UserDescription implements EventEngineDescription
{
    public const IDENTIFIER = 'userId';
    public const IDENTIFIER_ALIAS = 'user_id';
    public const USERNAME = 'username';
    public const EMAIL = 'email';

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
            ->withNew(User::TYPE)
            ->identifiedBy(self::IDENTIFIER)
            // Note: Our custom command is passed to the function
            ->handle([User::class, 'register'])
            ->recordThat(Event::USER_WAS_REGISTERED)
            // We pass a call hint. This is a No-Op callable
            // because OOPAggregateCallInterceptor does not use this callable
            // see OOPAggregateCallInterceptor::callApplyFirstEvent()
            // and OOPAggregateCallInterceptor::callApplySubsequentEvent()
            ->apply([FlavourHint::class, User::class]);
    }

    private static function describeChangeUsername(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::CHANGE_USERNAME)
            ->withExisting(User::TYPE)
            ->handle([FlavourHint::class, User::class])
            ->recordThat(Event::USERNAME_WAS_CHANGED)
            ->apply([FlavourHint::class, User::class]);
    }

    private static function describeChangeEmail(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::CHANGE_EMAIL)
            ->withExisting(User::TYPE)
            ->identifiedBy(self::IDENTIFIER_ALIAS)
            ->handle([FlavourHint::class, User::class])
            ->recordThat(Event::EMAIL_WAS_CHANGED)
            ->apply([FlavourHint::class, User::class]);
    }

    private static function describeConnectWithFriend(EventEngine $eventEngine): void
    {
        $eventEngine->process(Command::CONNECT_WITH_FRIEND)
            ->withExisting(User::TYPE)
            ->provideService(GetUserResolver::class)
            ->handle([FlavourHint::class, User::class])
            ->recordThat(Event::FRIEND_CONNECTED)
            ->apply([FlavourHint::class, User::class]);
    }

    private function __construct()
    {
        //static class only
    }
}
