<?php
/**
 * This file is part of the event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Util;

use Traversable;

final class MapIterator extends \IteratorIterator
{
    /**
     * @var callable
     */
    private $callback;

    public function __construct(Traversable $iterator, callable $callback)
    {
        parent::__construct($iterator);
        $this->callback = $callback;
    }

    public function current()
    {
        return \call_user_func($this->callback, parent::current());
    }
}
