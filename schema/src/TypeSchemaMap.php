<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Schema;

use EventEngine\Schema\Exception\RuntimeException;

final class TypeSchemaMap
{
    private $rawMap = [];

    private $typeMap = [];

    public function add(string $typeName, TypeSchema $typeSchema): void
    {
        $this->typeMap[$typeName] = $typeSchema;
        $this->rawMap[$typeName] = $typeSchema->toArray();
    }

    public function contains(string $typeName): bool
    {
        return array_key_exists($typeName, $this->rawMap);
    }

    /**
     * @param string $typeName
     * @return TypeSchema
     *
     */
    public function get(string $typeName): TypeSchema
    {
        if(!$this->contains($typeName)) {
            throw new RuntimeException("TypeSchemaMap does not has a type with name $typeName");
        }

        return $this->typeMap[$typeName];
    }

    /**
     * @return TypeSchema[] indexed by type name
     */
    public function all(): array
    {
        return $this->typeMap;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->rawMap;
    }
}
