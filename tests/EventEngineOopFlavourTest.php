<?php
/**
 * This file is part of the proophsoftware/event-machine.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineTest;

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\EventEngine;
use EventEngine\Messaging\MessageDispatcher;
use EventEngine\Messaging\MessageFactory;
use EventEngine\Persistence\AggregateStateStore;
use EventEngine\Querying\Resolver;
use EventEngine\Runtime\Flavour;
use EventEngine\Runtime\FunctionalFlavour;
use EventEngine\Runtime\OopFlavour;
use EventEngineExample\FunctionalFlavour\Api\MessageDescription;
use EventEngineExample\FunctionalFlavour\ExampleFunctionalPort;
use EventEngineExample\FunctionalFlavour\PreProcessor\RegisterUserIfNotExists;
use EventEngineExample\FunctionalFlavour\ProcessManager\SendWelcomeEmail;
use EventEngineExample\FunctionalFlavour\Projector\RegisteredUsersProjector;
use EventEngineExample\FunctionalFlavour\Resolver\GetUserResolver;
use EventEngineExample\FunctionalFlavour\Resolver\GetUsersResolver;
use EventEngineExample\OopFlavour\Aggregate\User;
use EventEngineExample\OopFlavour\Aggregate\UserDescription;
use EventEngineExample\OopFlavour\ExampleOopPort;
use EventEngineExample\OopFlavour\ExampleOopPortWithUserMetadataProvider;

class EventEngineOopFlavourTest extends EventEngineTestAbstract
{
    protected function loadEventMachineDescriptions(EventEngine $eventMachine)
    {
        $eventMachine->load(MessageDescription::class);
        $eventMachine->load(UserDescription::class);
    }

    protected function getFlavour(): Flavour
    {
        return new OopFlavour(
            new ExampleOopPort(),
            new FunctionalFlavour(new ExampleFunctionalPort())
        );
    }

    protected function getFlavourWithUserMetadataProvider(): Flavour
    {
        return new OopFlavour(
            new ExampleOopPortWithUserMetadataProvider(),
            new FunctionalFlavour(new ExampleFunctionalPort())
        );
    }

    protected function getChangeUsernamePreProcessor(MessageFactory $messageFactory, AggregateStateStore $stateStore)
    {
        return new RegisterUserIfNotExists($messageFactory, $stateStore);
    }

    protected function getRegisteredUsersProjector(DocumentStore $documentStore)
    {
        return new RegisteredUsersProjector($documentStore);
    }

    protected function getUserRegisteredListener(MessageDispatcher $messageDispatcher)
    {
        return new SendWelcomeEmail($messageDispatcher);
    }

    protected function getUserResolver(array $cachedUserState)
    {
        return new GetUserResolver($cachedUserState);
    }

    protected function getUsersResolver(array $cachedUsers)
    {
        return new GetUsersResolver($cachedUsers);
    }

    protected function assertLoadedUserState($userState): void
    {
        self::assertInstanceOf(User::class, $userState);
        self::assertEquals('Tester', $userState->toArray()['username']);
    }
}
