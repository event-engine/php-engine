<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Messaging;

use EventEngine\Runtime\Flavour;

final class FlavouredMessageProducer implements MessageProducer
{
    /**
     * @var Flavour
     */
    private $flavour;

    /**
     * @var MessageProducer
     */
    private $producer;

    public function __construct(Flavour $flavour, MessageProducer $producer)
    {
        $this->flavour = $flavour;
        $this->producer = $producer;
    }

    /**
     * @param Message $message
     * @return \Generator In case of a query a result is yielded otherwise null
     */
    public function produce(Message $message): \Generator
    {
        $message = $this->flavour->prepareNetworkTransmission($message);

        yield from $this->producer->produce($message);
    }
}
