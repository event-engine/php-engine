<?php
declare(strict_types=1);

namespace EventEngineExample\FunctionalFlavour\Command;

use EventEngineExample\FunctionalFlavour\Util\ApplyPayload;

final class ConnectWithFriend
{
    use ApplyPayload;

    /**
     * @var string
     */
    public $userId;

    /**
     * @var string
     */
    public $friend;
}
