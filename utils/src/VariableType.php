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

final class VariableType
{
    public static function determine($var): string
    {
        return \is_object($var) ? \get_class($var) : \gettype($var);
    }
}
