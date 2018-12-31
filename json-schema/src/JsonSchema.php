<?php
/**
 * This file is part of event-engine/php-json-schema.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\JsonSchema;

use EventEngine\JsonSchema\Type\ArrayType;
use EventEngine\JsonSchema\Type\BoolType;
use EventEngine\JsonSchema\Type\EmailType;
use EventEngine\JsonSchema\Type\EnumType;
use EventEngine\JsonSchema\Type\FloatType;
use EventEngine\JsonSchema\Type\IntType;
use EventEngine\JsonSchema\Type\ObjectType;
use EventEngine\JsonSchema\Type\StringType;
use EventEngine\JsonSchema\Type\TypeRef;
use EventEngine\JsonSchema\Type\UuidType;

final class JsonSchema
{
    public const DEFINITIONS = 'definitions';

    public const TYPE_STRING = 'string';
    public const TYPE_INT = 'integer';
    public const TYPE_FLOAT = 'number';
    public const TYPE_BOOL = 'boolean';
    public const TYPE_ARRAY = 'array';
    public const TYPE_OBJECT = 'object';
    public const TYPE_NULL = 'null';

    public const KEYWORD_ENUM = 'enum';

    public static function schemaFromScalarPhpType(string $type, bool $nullable): Type
    {
        switch ($type) {
            case 'string':
                $schema = self::string();
                break;
            case 'int':
                $schema = self::integer();
                break;
            case 'float':
                $schema = self::float();
                break;
            case 'bool':
                $schema = self::boolean();
                break;
            default:
                throw new \RuntimeException("Invalid scalar PHP type given. Got $type");
        }

        if ($nullable) {
            $schema = self::nullOr($schema);
        }

        return $schema;
    }

    public static function object(array $requiredProps, array $optionalProps = [], $additionalProperties = false): ObjectType
    {
        return new ObjectType($requiredProps, $optionalProps, $additionalProperties);
    }

    public static function array(Type $itemSchema, array $validation = null): ArrayType
    {
        return new ArrayType($itemSchema, $validation);
    }

    public static function string(array $validation = null): StringType
    {
        return new StringType($validation);
    }

    public static function email(): EmailType
    {
        return new EmailType();
    }

    public static function uuid(): UuidType
    {
        return new UuidType();
    }

    public static function integer(array $validation = null): IntType
    {
        return new IntType($validation);
    }

    public static function float(array $validation = null): FloatType
    {
        return new FloatType($validation);
    }

    public static function boolean(): BoolType
    {
        return new BoolType();
    }

    public static function enum(array $entries): EnumType
    {
        return new EnumType(...$entries);
    }

    public static function nullOr(Type $schema): Type
    {
        return $schema->asNullable();
    }

    public static function implementTypes(ObjectType $schema, string ...$types): ObjectType
    {
        foreach ($types as $typeName) {
            $schema = $schema->withImplementedType(new TypeRef($typeName));
        }

        return $schema;
    }

    public static function typeRef(string $typeName): TypeRef
    {
        if(strpos($typeName, '\\') !== -1 && class_exists($typeName) && is_callable([$typeName, '__type'])) {
            $typeName = call_user_func([$typeName, '__type']);
        }

        return new TypeRef($typeName);
    }

    public static function isArrayType(array $typeSchema): bool
    {
        return self::isType('array', $typeSchema);
    }

    public static function isObjectType(array $typeSchema): bool
    {
        return self::isType('object', $typeSchema);
    }

    public static function isStringEnum(array $typeSchema): bool
    {
        if (! \array_key_exists(self::KEYWORD_ENUM, $typeSchema)) {
            return false;
        }

        foreach ($typeSchema[self::KEYWORD_ENUM] as $val) {
            if (! \is_string($val)) {
                return false;
            }
        }

        return true;
    }

    public static function isType(string $type, array $typeSchema): bool
    {
        if (\array_key_exists('type', $typeSchema)) {
            if (\is_array($typeSchema['type'])) {
                foreach ($typeSchema['type'] as $possibleType) {
                    if ($possibleType === $type) {
                        return true;
                    }
                }
            } elseif (\is_string($typeSchema['type'])) {
                return $typeSchema['type'] === $type;
            }
        }

        return false;
    }

    public static function extractTypeFromRef(string $ref): string
    {
        return \str_replace('#/' . JsonSchema::DEFINITIONS . '/', '', $ref);
    }

    public static function assertAllInstanceOfType(array $types): void
    {
        foreach ($types as $key => $type) {
            if (! $type instanceof Type) {
                throw new \InvalidArgumentException(
                    "Invalid type at key $key. Type must implement EventEngine\JsonSchema\Type. Got "
                    . ((\is_object($type) ? \get_class($type) : \gettype($type))));
            }
        }
    }

    public static function metaSchema(): array
    {
        static $schema = [
            '$schema' => 'http://json-schema.org/draft-06/schema#',
            '$id' => 'http://json-schema.org/draft-06/schema#',
            'title' => 'Core schema meta-schema',
            'definitions' => [
                    'schemaArray' => [
                            'type' => 'array',
                            'minItems' => 1,
                            'items' => [
                                    '$ref' => '#',
                                ],
                        ],
                    'nonNegativeInteger' => [
                            'type' => 'integer',
                            'minimum' => 0,
                        ],
                    'nonNegativeIntegerDefault0' => [
                            'allOf' => [
                                    0 => [
                                            '$ref' => '#/definitions/nonNegativeInteger',
                                        ],
                                    1 => [
                                            'default' => 0,
                                        ],
                                ],
                        ],
                    'simpleTypes' => [
                            'enum' => [
                                    0 => 'array',
                                    1 => 'boolean',
                                    2 => 'integer',
                                    3 => 'null',
                                    4 => 'number',
                                    5 => 'object',
                                    6 => 'string',
                                ],
                        ],
                    'stringArray' => [
                            'type' => 'array',
                            'items' => [
                                    'type' => 'string',
                                ],
                            'uniqueItems' => true,
                            'default' => [
                                ],
                        ],
                ],
            'type' => [
                    0 => 'object',
                    1 => 'boolean',
                ],
            'properties' => [
                    '$id' => [
                            'type' => 'string',
                            'format' => 'uri-reference',
                        ],
                    '$schema' => [
                            'type' => 'string',
                            'format' => 'uri',
                        ],
                    '$ref' => [
                            'type' => 'string',
                            'format' => 'uri-reference',
                        ],
                    'title' => [
                            'type' => 'string',
                        ],
                    'description' => [
                            'type' => 'string',
                        ],
                    'default' => [
                        ],
                    'examples' => [
                            'type' => 'array',
                            'items' => [
                                ],
                        ],
                    'multipleOf' => [
                            'type' => 'number',
                            'exclusiveMinimum' => 0,
                        ],
                    'maximum' => [
                            'type' => 'number',
                        ],
                    'exclusiveMaximum' => [
                            'type' => 'number',
                        ],
                    'minimum' => [
                            'type' => 'number',
                        ],
                    'exclusiveMinimum' => [
                            'type' => 'number',
                        ],
                    'maxLength' => [
                            '$ref' => '#/definitions/nonNegativeInteger',
                        ],
                    'minLength' => [
                            '$ref' => '#/definitions/nonNegativeIntegerDefault0',
                        ],
                    'pattern' => [
                            'type' => 'string',
                            'format' => 'regex',
                        ],
                    'additionalItems' => [
                            '$ref' => '#',
                        ],
                    'items' => [
                            'anyOf' => [
                                    0 => [
                                            '$ref' => '#',
                                        ],
                                    1 => [
                                            '$ref' => '#/definitions/schemaArray',
                                        ],
                                ],
                            'default' => [
                                ],
                        ],
                    'maxItems' => [
                            '$ref' => '#/definitions/nonNegativeInteger',
                        ],
                    'minItems' => [
                            '$ref' => '#/definitions/nonNegativeIntegerDefault0',
                        ],
                    'uniqueItems' => [
                            'type' => 'boolean',
                            'default' => false,
                        ],
                    'contains' => [
                            '$ref' => '#',
                        ],
                    'maxProperties' => [
                            '$ref' => '#/definitions/nonNegativeInteger',
                        ],
                    'minProperties' => [
                            '$ref' => '#/definitions/nonNegativeIntegerDefault0',
                        ],
                    'required' => [
                            '$ref' => '#/definitions/stringArray',
                        ],
                    'additionalProperties' => [
                            '$ref' => '#',
                        ],
                    'definitions' => [
                            'type' => 'object',
                            'additionalProperties' => [
                                    '$ref' => '#',
                                ],
                            'default' => [
                                ],
                        ],
                    'properties' => [
                            'type' => 'object',
                            'additionalProperties' => [
                                    '$ref' => '#',
                                ],
                            'default' => [
                                ],
                        ],
                    'patternProperties' => [
                            'type' => 'object',
                            'additionalProperties' => [
                                    '$ref' => '#',
                                ],
                            'default' => [
                                ],
                        ],
                    'dependencies' => [
                            'type' => 'object',
                            'additionalProperties' => [
                                    'anyOf' => [
                                            0 => [
                                                    '$ref' => '#',
                                                ],
                                            1 => [
                                                    '$ref' => '#/definitions/stringArray',
                                                ],
                                        ],
                                ],
                        ],
                    'propertyNames' => [
                            '$ref' => '#',
                        ],
                    'const' => [
                        ],
                    'enum' => [
                            'type' => 'array',
                            'minItems' => 1,
                            'uniqueItems' => true,
                        ],
                    'type' => [
                            'anyOf' => [
                                    0 => [
                                            '$ref' => '#/definitions/simpleTypes',
                                        ],
                                    1 => [
                                            'type' => 'array',
                                            'items' => [
                                                    '$ref' => '#/definitions/simpleTypes',
                                                ],
                                            'minItems' => 1,
                                            'uniqueItems' => true,
                                        ],
                                ],
                        ],
                    'format' => [
                            'type' => 'string',
                        ],
                    'allOf' => [
                            '$ref' => '#/definitions/schemaArray',
                        ],
                    'anyOf' => [
                            '$ref' => '#/definitions/schemaArray',
                        ],
                    'oneOf' => [
                            '$ref' => '#/definitions/schemaArray',
                        ],
                    'not' => [
                            '$ref' => '#',
                        ],
                ],
            'default' => [
                ],

        ];

        return $schema;
    }

    private function __construct()
    {
        //static class only
    }
}
