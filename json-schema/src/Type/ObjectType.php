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
use EventEngine\JsonSchema\Type;
use EventEngine\Schema\InputTypeSchema;
use EventEngine\Schema\PayloadSchema;

class ObjectType implements AnnotatedType, PayloadSchema, InputTypeSchema
{
    use NullableType,
        HasAnnotations;

    /**
     * @var array
     */
    private $properties;

    /**
     * @var array
     */
    private $requiredProps;

    private $allowAdditionalProps;

    /**
     * @var TypeRef[]
     */
    private $implementedTypes = [];

    /**
     * @var string|array
     */
    private $type = JsonSchema::TYPE_OBJECT;

    public static function fromArray(): self
    {

    }

    public function __construct(array $requiredProps = [], array $optionalProps = [], bool $allowAdditionalProperties = false)
    {
        $props = \array_merge($requiredProps, $optionalProps);

        JsonSchema::assertAllInstanceOfType($props);

        $this->properties = $props;
        $this->requiredProps = \array_keys($requiredProps);
        $this->allowAdditionalProps = $allowAdditionalProperties;
    }

    public function withMergedOptionalProps(array $props): self
    {
        JsonSchema::assertAllInstanceOfType($props);

        $cp = clone $this;
        $cp->properties = \array_merge($cp->properties, $props);

        return $cp;
    }

    public function withMergedRequiredProps(array $props): self
    {
        JsonSchema::assertAllInstanceOfType($props);

        $cp = clone $this;
        $cp->properties = \array_merge($cp->properties, $props);
        $cp->requiredProps = \array_unique(\array_merge($cp->requiredProps, \array_keys($props)));

        return $cp;
    }

    public function withAllowAdditionalProps(bool $flag): self
    {
        $cp = clone $this;
        $cp->allowAdditionalProps = $flag;

        return $cp;
    }

    public function withImplementedType(TypeRef $typeRef): self
    {
        $cp = clone $this;
        $cp->implementedTypes[$typeRef->referencedTypeName()] = $typeRef;

        return $cp;
    }

    public function toArray(): array
    {
        $allOf = \array_map(function (TypeRef $typeRef) {
            return $typeRef->toArray();
        }, $this->implementedTypes);

        $schema = [
            'type' => $this->type,
            'required' => $this->requiredProps,
            'additionalProperties' => $this->allowAdditionalProps,
            'properties' => \array_map(function (Type $type) {
                return $type->toArray();
            }, $this->properties),
        ];

        if (\count($allOf)) {
            $schema['allOf'] = $allOf;
        }

        return \array_merge($schema, $this->annotations());
    }
}
