<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\JsonSchema;

use EventEngine\Schema\InputTypeSchema;
use EventEngine\Schema\PayloadSchema;
use EventEngine\Schema\ResponseTypeSchema;

final class JsonSchemaArray implements PayloadSchema, ResponseTypeSchema, InputTypeSchema
{
    /**
     * @var array
     */
    private $schema;

    public function __construct(array $schema)
    {
        $this->schema = $schema;
    }

    public function toArray(): array
    {
        return $this->schema;
    }
}
