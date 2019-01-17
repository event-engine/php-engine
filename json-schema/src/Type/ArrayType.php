<?php
/**
 * This file is part of event-engine/php-json-schema.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\JsonSchema\Type;

use EventEngine\JsonSchema\AnnotatedType;
use EventEngine\JsonSchema\JsonSchema;
use EventEngine\Schema\TypeSchema;

final class ArrayType implements AnnotatedType
{
    use NullableType,
        HasAnnotations;

    /**
     * @var string|array
     */
    private $type = JsonSchema::TYPE_ARRAY;

    /**
     * @var TypeSchema
     */
    private $itemSchema;

    /**
     * @var null|array
     */
    private $validation;

    public function __construct(TypeSchema $itemSchema, array $validation = null)
    {
        $this->itemSchema = $itemSchema;
        $this->validation = $validation;
    }

    public function toArray(): array
    {
        return \array_merge([
            'type' => $this->type,
            'items' => $this->itemSchema->toArray(),
        ], (array) $this->validation, $this->annotations());
    }
}
