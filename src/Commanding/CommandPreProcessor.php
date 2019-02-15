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

interface CommandPreProcessor
{
    /**
     * Message will be of type Message::TYPE_COMMAND
     *
     * A PreProcessor can modify a copy of the command and return that copy (commands itself should be immutable).
     *
     * @param Message $command
     * @return Message|CommandDispatchResult The modified command or a DispatchResult if processing should be stopped immediately
     */
    public function preProcess(Message $command);
}
