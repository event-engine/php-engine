<?php
/**
 * This file is part of event-engine/schema.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Schema;

/**
 * Interface InputTypeSchema
 *
 * Input type schemas can describe objects used in message payloads.
 * This is especially useful when the schema implementation does not allow arbitrary objects within an object
 * (like it is the case for GraphQL)
 *
 * @package EventEngine\Schema
 */
interface InputTypeSchema extends TypeSchema
{
}
