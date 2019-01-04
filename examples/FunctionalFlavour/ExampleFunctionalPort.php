<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineExample\FunctionalFlavour;

use EventEngine\Messaging\Message;
use EventEngine\Messaging\MessageBag;
use EventEngine\Runtime\Functional\Port;
use EventEngineExample\FunctionalFlavour\Api\Command;
use EventEngineExample\FunctionalFlavour\Api\Event;
use EventEngineExample\FunctionalFlavour\Api\Query;

final class ExampleFunctionalPort implements Port
{
    /**
     * {@inheritdoc}
     */
    public function deserialize(Message $message)
    {
        //Note: we use a very simple mapping strategy here
        //You could also use a deserializer or other techniques
        switch ($message->messageType()) {
            case Message::TYPE_COMMAND:
                if(! Command::canCreate($message->messageName())) {
                    return $message;
                }

                return Command::createFromNameAndPayload($message->messageName(), $message->payload());
            case Message::TYPE_EVENT:
                return Event::createFromNameAndPayload($message->messageName(), $message->payload());
            case Message::TYPE_QUERY:
                return Query::createFromNameAndPayload($message->messageName(), $message->payload());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function serializePayload($customMessage): array
    {
        //Since, we use objects with public properties as custom messages, casting to array is enough
        //In a production setting, you should use your own immutable messages and a serializer
        return (array) $customMessage;
    }

    /**
     * {@inheritdoc}
     */
    public function decorateEvent($customEvent): MessageBag
    {
        return new MessageBag(
            Event::nameOf($customEvent),
            MessageBag::TYPE_EVENT,
            $customEvent
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getAggregateIdFromCommand(string $aggregateIdPayloadKey, $command): string
    {
        //Duck typing, do not do this in production but rather use your own interfaces
        return $command->{$aggregateIdPayloadKey};
    }

    /**
     * {@inheritdoc}
     */
    public function callCommandPreProcessor($customCommand, $preProcessor)
    {
        //Duck typing, do not do this in production but rather use your own interfaces
        return $preProcessor->preProcess($customCommand);
    }

    /**
     * {@inheritdoc}
     */
    public function callContextProvider($customCommand, $contextProvider)
    {
        //Duck typing, do not do this in production but rather use your own interfaces
        return $contextProvider->provide($customCommand);
    }

    /**
     * {@inheritdoc}
     */
    public function callResolver($customQuery, $resolver)
    {
        //Duck typing, do not do this in production but rather use your own interfaces
        return $resolver->resolve($customQuery);
    }
}
