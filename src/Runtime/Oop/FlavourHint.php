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

use EventEngine\Runtime\OopFlavour;

final class FlavourHint
{
    public static function __callStatic($name, $arguments)
    {
        throw new \BadMethodCallException(__CLASS__  . "::$name should never be called. Check that EventEngine uses " . OopFlavour::class);
    }
    
    public static function useAggregate()
    {
        throw new \BadMethodCallException(__METHOD__  . ' should never be called. Check that EventEngine uses ' . OopFlavour::class);
    }
}
