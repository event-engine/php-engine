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

class EnumType implements AnnotatedType
{
    use NullableType,
        HasAnnotations;

    /**
     * @var string|array
     */
    private $type = JsonSchema::TYPE_STRING;

    /**
     * @var string[]
     */
    private $entries;

    public function __construct(string ...$entries)
    {
        $this->entries = $entries;
    }

    public function toArray(): array
    {
        return \array_merge([
            'type' => $this->type,
            'enum' => $this->entries,
        ], $this->annotations());
    }
}
