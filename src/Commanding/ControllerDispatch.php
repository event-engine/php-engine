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

use EventEngine\Exception\RuntimeException;
use EventEngine\Logger\LogEngine;
use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\CommandDispatchResultCollection;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageDispatcher;
use EventEngine\Runtime\Flavour;
use EventEngine\Util\MessageTuple;
use EventEngine\Util\VariableType;

final class ControllerDispatch
{
    public static function exec(
        Message $command,
        Flavour $flavour,
        LogEngine $logEngine,
        MessageDispatcher $dispatcher,
        $controller,
        bool $forwardMetadata
    ): CommandDispatchResultCollection {
        $result = $flavour->callCommandController($controller, $command);

        $logEngine->commandControllerCalled($controller, $command, $result);

        if($result instanceof CommandDispatchResult) {
            return new CommandDispatchResultCollection($result);
        }

        if(!\is_array($result)) {
            throw new RuntimeException("Command controller " . VariableType::determine($controller) . " returned invalid result for command {$command->messageName()}."
                . " Should either be a CommandDispatchResult or an array of commands to be dispatched. Got: " . VariableType::determine($result));
        }

        $resultCollection = new CommandDispatchResultCollection();
        foreach ($result as $index => $followUpCommand) {
            if($followUpCommand instanceof Message) {
                if($forwardMetadata) {
                    $followUpCommand = $followUpCommand->withMetadata(array_merge($followUpCommand->metadata(), $command->metadata()));
                }

                $resultCollection = $resultCollection->push($dispatcher->dispatch($followUpCommand));
            } elseif (\is_array($followUpCommand)) {
                [$messageName, $payload, $metadata] = MessageTuple::normalize($followUpCommand);

                if($forwardMetadata) {
                    $metadata = array_merge($metadata, $command->metadata());
                }

                $resultCollection = $resultCollection->push($dispatcher->dispatch($messageName, $payload, $metadata));
            } else {
                throw new RuntimeException("Command controller " . VariableType::determine($controller) . " returned invalid result for command {$command->messageName()}."
                    . " Should either be a CommandDispatchResult or an array of commands to be dispatched. Got an array, but item at index {$index} is invalid: " . VariableType::determine($followUpCommand));
            }
        }

        return $resultCollection;
    }
}
