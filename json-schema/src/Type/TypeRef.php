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

use EventEngine\JsonSchema\JsonSchema;
use EventEngine\JsonSchema\Type;

class TypeRef implements Type
{
    /**
     * @var string
     */
    private $referencedTypeName;

    private $nullable = false;

    public function __construct(string $referencedTypeName)
    {
        $this->referencedTypeName = $referencedTypeName;
    }

    public function referencedTypeName(): string
    {
        return $this->referencedTypeName;
    }

    public function toArray(): array
    {
        $refArr = ['$ref' => '#/'.JsonSchema::DEFINITIONS.'/'.$this->referencedTypeName];

        if ($this->nullable) {
            return [
                'oneOf' => [
                    ['type' => JsonSchema::TYPE_NULL],
                    $refArr,
                ],
            ];
        }

        return $refArr;
    }

    public function asNullable(): Type
    {
        $cp = clone $this;
        $cp->nullable = true;

        return $cp;
    }
}
