<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\FunctionalFlavour\Api;

use EventEngineExample\FunctionalFlavour\Command\ChangeEmail;
use EventEngineExample\FunctionalFlavour\Command\ChangeUsername;
use EventEngineExample\FunctionalFlavour\Command\ConnectWithFriend;
use EventEngineExample\FunctionalFlavour\Command\RegisterUser;

final class Command
{
    const REGISTER_USER = 'RegisterUser';
    const CHANGE_USERNAME = 'ChangeUsername';
    const CHANGE_EMAIL = 'ChangeEmail';
    const CONNECT_WITH_FRIEND = 'ConnectWithFriend';
    const DO_NOTHING = 'DoNothing';

    const CLASS_MAP = [
        self::REGISTER_USER => RegisterUser::class,
        self::CHANGE_USERNAME => ChangeUsername::class,
        self::CHANGE_EMAIL => ChangeEmail::class,
        self::CONNECT_WITH_FRIEND => ConnectWithFriend::class,
    ];

    public static function canCreate(string $commandName): bool
    {
        return array_key_exists($commandName, self::CLASS_MAP);
    }

    public static function createFromNameAndPayload(string $commandName, array $payload, array $metadata = null)
    {
        $class = self::CLASS_MAP[$commandName];

        $cmd = new $class($payload);

        if($metadata && property_exists($cmd, 'metadata')) {
            $cmd->metadata = $metadata;
        }

        return $cmd;
    }

    public static function nameOf($command): string
    {
        $map = \array_flip(self::CLASS_MAP);

        return $map[\get_class($command)];
    }

    private function __construct()
    {
        //static class only
    }
}
