<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineTest;

use EventEngine\Commanding\CommandPreProcessor;
use EventEngine\DocumentStore\DocumentStore;
use EventEngine\EventEngine;
use EventEngine\Messaging\CommandDispatchResult;
use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageDispatcher;
use EventEngine\Querying\Resolver;
use EventEngine\Runtime\Flavour;
use EventEngine\Runtime\PrototypingFlavour;
use EventEngine\Util\Await;
use EventEngineExample\PrototypingFlavour\Aggregate\UserDescription;
use EventEngineExample\PrototypingFlavour\Aggregate\UserState;
use EventEngineExample\PrototypingFlavour\Messaging\CommandWithCustomHandler;
use EventEngineExample\PrototypingFlavour\Messaging\MessageDescription;
use EventEngineExample\PrototypingFlavour\ProcessManager\SendWelcomeEmail;
use EventEngineExample\PrototypingFlavour\Projector\RegisteredUsersProjector;
use EventEngineExample\PrototypingFlavour\Resolver\GetUserResolver;
use EventEngineExample\PrototypingFlavour\Resolver\GetUsersResolver;
use Prophecy\Argument;

class EventEnginePrototypingFlavourTest extends EventEngineTestAbstract
{
    protected function loadEventMachineDescriptions(EventEngine $eventEngine)
    {
        $eventEngine->load(MessageDescription::class);
        $eventEngine->load(UserDescription::class);
    }

    protected function getFlavour(): Flavour
    {
        return new PrototypingFlavour();
    }

    protected function getRegisteredUsersProjector(DocumentStore $documentStore)
    {
        return new RegisteredUsersProjector($documentStore);
    }

    protected function getUserRegisteredListener(MessageDispatcher $messageDispatcher)
    {
        return new SendWelcomeEmail($messageDispatcher);
    }

    protected function getUserResolver(array $cachedUserState): Resolver
    {
        return new GetUserResolver($cachedUserState);
    }

    protected function getUsersResolver(array $cachedUsers): Resolver
    {
        return new GetUsersResolver($cachedUsers);
    }

    protected function assertLoadedUserState($userState): void
    {
        self::assertInstanceOf(UserState::class, $userState);
        self::assertEquals('Tester', $userState->username);
    }

    /**
     * @test
     */
    public function it_throws_exception_if_config_should_be_cached_but_contains_closures()
    {
        $this->initializeEventEngine();

        self::expectException(\RuntimeException::class);
        self::expectExceptionMessage('At least one EventEngineDescription contains a Closure and is therefor not cacheable!');

        $this->eventEngine->compileCacheableConfig();
    }

    /**
     * @test
     */
    public function it_stops_dispatch_if_preprocessor_yields_dispatch_result()
    {
        $this->eventEngine->load(CommandWithCustomHandler::class);

        $noOpHandler = new class() implements CommandPreProcessor {
            private $msg;

            /**
             * {@inheritdoc}
             */
            public function preProcess(Message $message): CommandDispatchResult
            {
                if ($message instanceof Message) {
                    $this->msg = $message->get('msg');
                }

                return CommandDispatchResult::forCommandHandledByPreProcessor($message);
            }

            public function msg(): ?string
            {
                return $this->msg;
            }
        };

        $this->appContainer->get(Argument::exact(CommandWithCustomHandler::NO_OP_HANDLER))->willReturn($noOpHandler);


        $this->initializeEventEngine();
        $this->bootstrapEventEngine();

        Await::lastResult($this->eventEngine->dispatch(CommandWithCustomHandler::CMD_DO_NOTHING_NO_HANDLER, [
            'msg' => 'test',
        ]));

        $this->assertEquals('test', $noOpHandler->msg());
    }
}
