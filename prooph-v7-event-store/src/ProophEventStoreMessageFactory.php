<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Prooph\V7\EventStore;

use Prooph\Common\Messaging\Message;
use Prooph\Common\Messaging\MessageFactory;

/**
 * Class ProophEventStoreMessageFactory
 *
 * The message factory only works with events loaded from prooph/event-store v7!
 *
 * @package EventEngine\Pooph\V7\EventStore
 */
final class ProophEventStoreMessageFactory implements MessageFactory
{
    /**
     * @inheritdoc
     */
    public function createMessageFromArray(string $messageName, array $messageData): Message
    {
        $messageData['message_name'] = $messageName;

        return GenericProophEvent::fromArray($messageData);
    }
}
