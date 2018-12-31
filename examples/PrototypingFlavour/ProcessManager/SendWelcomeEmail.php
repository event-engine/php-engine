<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\PrototypingFlavour\ProcessManager;

use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageDispatcher;
use EventEngine\Util\Await;

final class SendWelcomeEmail
{
    /**
     * @var MessageDispatcher
     */
    private $messageDispatcher;

    public function __construct(MessageDispatcher $messageDispatcher)
    {
        $this->messageDispatcher = $messageDispatcher;
    }

    public function __invoke(Message $event)
    {
        Await::join($this->messageDispatcher->dispatch('SendWelcomeEmail', ['email' => $event->get('email')]));
    }
}
