<?php
declare(strict_types=1);

namespace EventEngineExample\FunctionalFlavour\ContextProvider;

use EventEngineExample\FunctionalFlavour\Command\ConnectWithFriend;

final class SocialPlatformProvider
{
    public function provide(ConnectWithFriend $command): string
    {
        return 'Github';
    }
}
