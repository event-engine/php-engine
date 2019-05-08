<?php
declare(strict_types=1);

namespace EventEngineExample\FunctionalFlavour\Event;

use EventEngineExample\FunctionalFlavour\Util\ApplyPayload;

final class FriendConnected
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
