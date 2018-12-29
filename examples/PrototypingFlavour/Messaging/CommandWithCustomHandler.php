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

final class CommandWithCustomHandler implements EventEngineDescription
{
    public const CMD_DO_NOTHING = 'DoNothing';
    public const NO_OP_HANDLER = 'NoOpHandler';

    public static function describe(EventEngine $eventEngine): void
    {
        $eventEngine->registerCommand(self::CMD_DO_NOTHING, JsonSchema::object(['msg' => JsonSchema::string()]));
        $eventEngine->preProcess(self::CMD_DO_NOTHING, self::NO_OP_HANDLER);
    }
}
