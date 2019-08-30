<?php
declare(strict_types=1);

namespace EventEngineExample\FunctionalFlavour\ContextProvider;

use EventEngineExample\FunctionalFlavour\Command\ConnectWithFriend;

final class MatchingHobbiesProvider
{
    public function provide(ConnectWithFriend $command): array
    {
        return ['coding', 'EventStorming', 'EventModeling'];
    }
}
