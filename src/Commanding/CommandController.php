<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Commanding;

use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\Message;

interface CommandController
{
    /**
     * Message will be of type Message::TYPE_COMMAND
     *
     * A CommandController can process the command in any way and return a CommandDispatchResult
     * or it returns a list of commands or command tuples: [command_name, payload, optional_metadata]
     * that are automatically dispatched by Event Engine
     *
     * @param Message $command
     * @return CommandDispatchResult|Message[]|array[]
     */
    public function process(Message $command);
}
