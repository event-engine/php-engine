<?php
declare(strict_types=1);

namespace EventEngineExample\PrototypingFlavour\ContextProvider;

use EventEngine\Aggregate\ContextProvider;
use EventEngine\Messaging\Message;

final class SocialPlatformProvider implements ContextProvider
{
    public function provide(Message $connectWithFriend): string
    {
        return 'Github';
    }
}
