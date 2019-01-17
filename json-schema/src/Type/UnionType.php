<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\JsonSchema\Type;

use EventEngine\Schema\TypeSchema;

final class UnionType implements TypeSchema
{
    /**
     * @var TypeSchema[]
     */
    private $types;

    public function __construct(TypeSchema ...$types)
    {
        $this->types = $types;
    }

    /**
     * Array representation of the schema
     */
    public function toArray(): array
    {
        return [
            'oneOf' => array_map(function (TypeSchema $typeSchema) {
                return $typeSchema->toArray();
            }, $this->types)
        ];
    }
}
