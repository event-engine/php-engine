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

use EventEngineExample\FunctionalFlavour\Event\UsernameChanged;
use EventEngineExample\FunctionalFlavour\Event\UserRegistered;
use EventEngineExample\FunctionalFlavour\Event\UserRegistrationFailed;

final class Event
{
    const USER_WAS_REGISTERED = 'UserWasRegistered';
    const USER_REGISTRATION_FAILED = 'UserRegistrationFailed';
    const USERNAME_WAS_CHANGED = 'UsernameWasChanged';

    const CLASS_MAP = [
        self::USER_WAS_REGISTERED => UserRegistered::class,
        self::USER_REGISTRATION_FAILED => UserRegistrationFailed::class,
        self::USERNAME_WAS_CHANGED => UsernameChanged::class,
    ];

    public static function createFromNameAndPayload(string $commandName, array $payload)
    {
        $class = self::CLASS_MAP[$commandName];

        return new $class($payload);
    }

    public static function nameOf($event): string
    {
        $map = \array_flip(self::CLASS_MAP);

        return $map[\get_class($event)];
    }

    private function __construct()
    {
        //static class only
    }
}
