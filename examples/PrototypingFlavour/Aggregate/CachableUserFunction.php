<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\PrototypingFlavour\Aggregate;

use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageFactory;
use EventEngineExample\PrototypingFlavour\Messaging\Event;
use EventEngineExample\PrototypingFlavour\Messaging\Query;
use EventEngineExample\PrototypingFlavour\Resolver\GetUserResolver;

final class CachableUserFunction
{
    public static function registerUser(Message $registerUser)
    {
        if (! \array_key_exists('shouldFail', $registerUser->payload()) || ! $registerUser->payload()['shouldFail']) {
            //We just turn the command payload into event payload by yielding it
            yield [Event::USER_WAS_REGISTERED, $registerUser->payload()];
        } else {
            yield [Event::USER_REGISTRATION_FAILED, [
                CacheableUserDescription::IDENTIFIER => $registerUser->payload()[CacheableUserDescription::IDENTIFIER],
            ]];
        }
    }

    public static function whenUserWasRegistered(Message $userWasRegistered): UserState
    {
        $user = new UserState();
        $user->userId = $userWasRegistered->payload()[CacheableUserDescription::IDENTIFIER];
        $user->username = $userWasRegistered->payload()['username'];
        $user->email = $userWasRegistered->payload()['email'];

        return $user;
    }

    public static function whenUserRegistrationFailed(Message $userRegistrationFailed): UserState
    {
        $user = new UserState();
        $user->failed = true;

        return $user;
    }

    public static function changeUsername(UserState $user, Message $changeUsername)
    {
        yield [Event::USERNAME_WAS_CHANGED, [
            CacheableUserDescription::IDENTIFIER => $user->userId,
            'oldName' => $user->username,
            'newName' => $changeUsername->payload()['username'],
        ]];
    }

    public static function whenUsernameWasChanged(UserState $user, Message $usernameWasChanged): UserState
    {
        $user->username = $usernameWasChanged->payload()['newName'];

        return $user;
    }

    public static function changeEmail(UserState $user, Message $changeEmail)
    {
        yield [Event::EMAIL_WAS_CHANGED, [
            CacheableUserDescription::IDENTIFIER_ALIAS => $user->userId,
            'oldMail' => $user->email,
            'newMail' => $changeEmail->get(UserDescription::EMAIL),
        ]];
    }

    public static function whenEmailWasChanged(UserState $user, Message $emailWasChanged): UserState
    {
        $user->email = $emailWasChanged->get('newMail');

        return $user;
    }

    public static function connectWithFriend(UserState $user, Message $connectWithFriend, GetUserResolver $resolver, MessageFactory $messageFactory): \Generator
    {
        $friendId = $connectWithFriend->get(CacheableUserDescription::FRIEND);

        //Check that friend exists using a resolver dependency
        $friend = $resolver->resolve($messageFactory->createMessageFromArray(Query::GET_USER, [
            CacheableUserDescription::IDENTIFIER => $friendId
        ]));

        yield [Event::FRIEND_CONNECTED, [
            CacheableUserDescription::IDENTIFIER => $user->userId,
            CacheableUserDescription::FRIEND => $friend[CacheableUserDescription::IDENTIFIER],
        ]];
    }

    public static function whenFriendConnected(UserState $user, Message $friendConnected): UserState
    {
        $user->friends[] = $friendConnected->get(CacheableUserDescription::FRIEND);
        return $user;
    }

    public static function doNothing(UserState $user, Message $doNothing)
    {
        yield null;
    }

    private function __construct()
    {
        //static class only
    }
}
