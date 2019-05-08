<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\PrototypingFlavour\Messaging;

use EventEngine\EventEngine;
use EventEngine\EventEngineDescription;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\Type\EmailType;
use EventEngine\JsonSchema\Type\StringType;
use EventEngine\JsonSchema\Type\UuidType;
use EventEngineExample\PrototypingFlavour\Aggregate\UserDescription;
use EventEngineExample\PrototypingFlavour\Resolver\GetUserResolver;
use EventEngineExample\PrototypingFlavour\Resolver\GetUsersResolver;

/**
 * You're free to organize EventMachineDescriptions in the way that best fits your personal preferences
 *
 * We decided to describe all messages of the bounded context in a centralized MessageDescription.
 * Another idea would be to register messages within an aggregate description.
 *
 * You only need to follow one rule:
 * Messages need be registered BEFORE they are referenced by handling or listing descriptions
 *
 * Class MessageDescription
 * @package EventEngineExample\Messaging
 */
final class MessageDescription implements EventEngineDescription
{
    public static function describe(EventEngine $eventEngine): void
    {
        /* Schema Definitions */
        $userId = new UuidType();

        $username = (new StringType())->withMinLength(1);

        $userDataSchema = JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => $username,
            UserDescription::EMAIL => new EmailType(),
        ], [
            //If it is set to true user registration handler will record a UserRegistrationFailed event
            //when using CachableUserFunction
            'shouldFail' => JsonSchema::boolean(),
        ]);

        /* Message Registration */
        $eventEngine->registerCommand(Command::REGISTER_USER, $userDataSchema);
        $eventEngine->registerCommand(Command::CHANGE_USERNAME, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => $username,
        ]));
        $eventEngine->registerCommand(Command::CHANGE_EMAIL, JsonSchema::object([
            UserDescription::IDENTIFIER_ALIAS => $userId,
            UserDescription::EMAIL => JsonSchema::email(),
        ]));
        $eventEngine->registerCommand(Command::CONNECT_WITH_FRIEND, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::FRIEND => $userId,
        ]));
        $eventEngine->registerCommand(Command::DO_NOTHING, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
        ]));

        $eventEngine->registerEvent(Event::USER_WAS_REGISTERED, $userDataSchema);
        $eventEngine->registerEvent(Event::USERNAME_WAS_CHANGED, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            'oldName' => $username,
            'newName' => $username,
        ]));
        $eventEngine->registerEvent(Event::EMAIL_WAS_CHANGED, JsonSchema::object([
            UserDescription::IDENTIFIER_ALIAS => $userId,
            'oldMail' => JsonSchema::email(),
            'newMail' => JsonSchema::email(),
        ]));

        $eventEngine->registerEvent(Event::FRIEND_CONNECTED, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::FRIEND => $userId,
        ]));

        $eventEngine->registerEvent(Event::USER_REGISTRATION_FAILED, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
        ]));

        //Register user state as a Type so that we can reference it as query return type
        $eventEngine->registerType('User', $userDataSchema);
        $eventEngine->registerQuery(Query::GET_USER, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
        ]))
        ->resolveWith(GetUserResolver::class)
        ->setReturnType(JsonSchema::typeRef('User'));

        $eventEngine->registerQuery(Query::GET_USERS)
            ->resolveWith(GetUsersResolver::class)
            ->setReturnType(JsonSchema::array(JsonSchema::typeRef('User')));

        $filterInput = JsonSchema::object([
            'username' => JsonSchema::nullOr(JsonSchema::string()),
            'email' => JsonSchema::nullOr(JsonSchema::email()),
        ]);
        $eventEngine->registerQuery(Query::GET_FILTERED_USERS, JsonSchema::object([], [
            'filter' => $filterInput,
        ]))
            ->resolveWith(GetUsersResolver::class)
            ->setReturnType(JsonSchema::array(JsonSchema::typeRef('User')));
    }
}
