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
 * Class ProcessAndEventBag
 *
 * Immutable DTO used by the OopFlavour to pass a newly created process instance together with the first
 * event to the first apply method. The DTO is put into a MessageBag, because Event Engine only takes care of events
 * produced by process factories.
 *
 * @package EventEngine\Runtime\Oop
 */
final class ProcessAndEventBag
{
    /**
     * @var mixed
     */
    private $process;

    /**
     * @var mixed
     */
    private $event;

    public function __construct($process, $event)
    {
        $this->process = $process;
        $this->event = $event;
    }

    /**
     * @return mixed
     */
    public function process()
    {
        return $this->process;
    }

    /**
     * @return mixed
     */
    public function event()
    {
        return $this->event;
    }
}
