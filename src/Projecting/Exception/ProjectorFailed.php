<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2021 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Projecting\Exception;

use EventEngine\Exception\EventEngineException;
use EventEngine\Messaging\GenericEvent;

final class ProjectorFailed extends \RuntimeException implements EventEngineException
{
    protected $code = 500;

    /**
     * @var GenericEvent
     */
    private $failedEvent;

    public static function atEvent(GenericEvent $event, string $projectionName, string $projectorServiceId, \Throwable $error): self
    {
        $self = new self(sprintf(
            "Projector %s failed to process event with id %s for projection %s. Reason: %s",
            $projectorServiceId,
            $event->uuid()->toString(),
            $projectionName,
            $error->getMessage()
        ));

        $self->failedEvent = $event;

        return $self;
    }

    /**
     * @return GenericEvent
     */
    public function failedEvent(): GenericEvent
    {
        return $this->failedEvent;
    }
}
