<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\PrototypingFlavour\PreProcessor;

use EventEngine\Aggregate\Exception\AggregateNotFound;
use EventEngine\Commanding\CommandPreProcessor;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageFactory;
use EventEngine\Persistence\AggregateStateStore;
use EventEngineExample\PrototypingFlavour\Aggregate\Aggregate;
use EventEngineExample\PrototypingFlavour\Aggregate\UserDescription;
use EventEngineExample\PrototypingFlavour\Messaging\Command;

final class RegisterUserIfNotExists implements CommandPreProcessor
{
    /**
     * @var MessageFactory
     */
    private $messageFactory;

    /**
     * @var AggregateStateStore
     */
    private $aggregateStateStore;

    public function __construct(MessageFactory $messageFactory, AggregateStateStore $stateStore)
    {
        $this->messageFactory = $messageFactory;
        $this->aggregateStateStore = $stateStore;
    }

    /**
     * @inheritdoc
     */
    public function preProcess(Message $command)
    {
        if($command->messageName() !== Command::CHANGE_USERNAME) {
            return $command;
        }

        try {
            $this->aggregateStateStore->loadAggregateState(Aggregate::USER, $command->get(UserDescription::IDENTIFIER));
            return $command;
        } catch (AggregateNotFound $err) {
            return $this->messageFactory->createMessageFromArray(Command::REGISTER_USER, [
                'payload' => [
                    UserDescription::IDENTIFIER => $command->get(UserDescription::IDENTIFIER),
                    UserDescription::USERNAME => $command->get(UserDescription::USERNAME),
                    UserDescription::EMAIL => $command->metadata()['auth']['email'],
                ],
                'metadata' => $command->metadata(),
                'uuid' => $command->uuid()->toString()
            ]);
        }
    }
}
