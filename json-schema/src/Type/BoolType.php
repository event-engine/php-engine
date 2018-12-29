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

final class BoolType implements AnnotatedType
{
    use NullableType,
        HasAnnotations;

    /**
     * @var string|array
     */
    private $type = JsonSchema::TYPE_BOOL;

    public function toArray(): array
    {
        return \array_merge(['type' => $this->type], $this->annotations());
    }
}
