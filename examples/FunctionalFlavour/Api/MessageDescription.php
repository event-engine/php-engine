<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\FunctionalFlavour\Api;

use Prooph\EventMachine\EventMachine;
use Prooph\EventMachine\EventMachineDescription;
use Prooph\EventMachine\JsonSchema\JsonSchema;
use Prooph\EventMachine\JsonSchema\Type\EmailType;
use Prooph\EventMachine\JsonSchema\Type\StringType;
use Prooph\EventMachine\JsonSchema\Type\UuidType;
use EventEngineExample\FunctionalFlavour\Resolver\GetUserResolver;
use EventEngineExample\FunctionalFlavour\Resolver\GetUsersResolver;
use EventEngineExample\PrototypingFlavour\Aggregate\UserDescription;

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
final class MessageDescription implements EventMachineDescription
{
    public static function describe(EventMachine $eventMachine): void
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
        'shouldFail' => JsonSchema::boolean(),
        ]);
        $eventMachine->registerCommand(Command::DO_NOTHING, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
        ]));

        /* Message Registration */
        $eventMachine->registerCommand(Command::REGISTER_USER, $userDataSchema);
        $eventMachine->registerCommand(Command::CHANGE_USERNAME, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            UserDescription::USERNAME => $username,
        ]));

        $eventMachine->registerEvent(Event::USER_WAS_REGISTERED, $userDataSchema);
        $eventMachine->registerEvent(Event::USERNAME_WAS_CHANGED, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
            'oldName' => $username,
            'newName' => $username,
        ]));

        $eventMachine->registerEvent(Event::USER_REGISTRATION_FAILED, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
        ]));

        //Register user state as a Type so that we can reference it as query return type
        $eventMachine->registerType('User', $userDataSchema);
        $eventMachine->registerQuery(Query::GET_USER, JsonSchema::object([
            UserDescription::IDENTIFIER => $userId,
        ]))
        ->resolveWith(GetUserResolver::class)
        ->setReturnType(JsonSchema::typeRef('User'));

        $eventMachine->registerQuery(Query::GET_USERS)
            ->resolveWith(GetUsersResolver::class)
            ->setReturnType(JsonSchema::array(JsonSchema::typeRef('User')));

        $filterInput = JsonSchema::object([
            'username' => JsonSchema::nullOr(JsonSchema::string()),
            'email' => JsonSchema::nullOr(JsonSchema::email()),
        ]);
        $eventMachine->registerQuery(Query::GET_FILTERED_USERS, JsonSchema::object([], [
            'filter' => $filterInput,
        ]))
            ->resolveWith(GetUsersResolver::class)
            ->setReturnType(JsonSchema::array(JsonSchema::typeRef('User')));
    }
}
