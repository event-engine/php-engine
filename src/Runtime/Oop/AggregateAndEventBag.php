<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Runtime\Oop;

/**
 * Class AggregateAndEventBag
 *
 * Immutable DTO used by the OopFlavour to pass a newly created aggregate instance together with the first
 * event to the first apply method. The DTO is put into a MessageBag, because Event Engine only takes care of events
 * produced by aggregate factories.
 *
 * @package EventEngine\Runtime\Oop
 */
final class AggregateAndEventBag
{
    /**
     * @var mixed
     */
    private $aggregate;

    /**
     * @var mixed
     */
    private $event;

    public function __construct($aggregate, $event)
    {
        $this->aggregate = $aggregate;
        $this->event = $event;
    }

    /**
     * @return mixed
     */
    public function aggregate()
    {
        return $this->aggregate;
    }

    /**
     * @return mixed
     */
    public function event()
    {
        return $this->event;
    }
}
