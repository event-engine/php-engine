<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\FunctionalFlavour\PreProcessor;

use EventEngine\Aggregate\Exception\AggregateNotFound;
use EventEngine\Messaging\MessageFactory;
use EventEngine\Persistence\AggregateStateStore;
use EventEngineExample\FunctionalFlavour\Command\ChangeUsername;
use EventEngineExample\FunctionalFlavour\Command\RegisterUser;
use EventEngineExample\PrototypingFlavour\Aggregate\Aggregate;
use EventEngineExample\PrototypingFlavour\Aggregate\UserDescription;
use EventEngineExample\PrototypingFlavour\Messaging\Command;

final class RegisterUserIfNotExists
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
    public function preProcess(ChangeUsername $command)
    {
        try {
            $this->aggregateStateStore->loadAggregateState(Aggregate::USER, $command->userId);
            return $command;
        } catch (AggregateNotFound $err) {
            return new RegisterUser([
                UserDescription::IDENTIFIER => $command->userId,
                UserDescription::USERNAME => $command->username,
                UserDescription::EMAIL => $command->metadata['auth']['email'],
            ]);
        }
    }
}
