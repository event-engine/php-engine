<?php
declare(strict_types=1);

namespace EventEngineExample\PrototypingFlavour\ContextProvider;

use EventEngine\Aggregate\ContextProvider;
use EventEngine\Messaging\Message;

final class MatchingHobbiesProvider implements ContextProvider
{
    public function provide(Message $connectWithFriend): array
    {
        return ['coding', 'EventStorming', 'EventModeling'];
    }
}
