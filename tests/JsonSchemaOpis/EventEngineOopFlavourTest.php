<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngineTest\JsonSchemaOpis;

use EventEngine\JsonSchema\OpisJsonSchema;
use EventEngine\Schema\Schema;

/**
 * @group opis
 */
class EventEngineOopFlavourTest extends \EventEngineTest\EventEngineOopFlavourTest
{
    protected function getSchemaInstance(): Schema
    {
        return new OpisJsonSchema();
    }
}
