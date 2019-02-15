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

use EventEngine\Data\ImmutableRecord;
use EventEngine\JsonSchema\Exception\InvalidArgumentException;
use EventEngine\Schema\TypeSchema;

trait ImmutableRecordLogic
{
    /**
     * Override the method in the class using this trait to provide type hints for items of array properties
     *
     * @return array
     */
    private static function arrayPropItemTypeMap(): array
    {
        return [];
    }

    /**
     * Called in constructor after setting props but before not null assertion
     *
     * Override to set default props after construction
     */
    private function init(): void
    {
    }

    /**
     * @param array $recordData
     * @return self
     */
    public static function fromRecordData(array $recordData)
    {
        return new self($recordData);
    }

    /**
     * @param array $nativeData
     * @return self
     */
    public static function fromArray(array $nativeData)
    {
        return new self(null, $nativeData);
    }

    public static function __type(): string
    {
        return self::convertClassToTypeName(\get_called_class());
    }

    public static function __schema(): TypeSchema
    {
        return self::generateSchemaFromPropTypeMap();
    }

    private function __construct(array $recordData = null, array $nativeData = null)
    {
        if (null === self::$__propTypeMap) {
            self::$__schema = self::__schema();
            self::$__propTypeMap = self::buildPropTypeMap();
        }

        if ($recordData) {
            $this->setRecordData($recordData);
        }

        if ($nativeData) {
            $this->setNativeData($nativeData);
        }

        $this->init();

        $this->assertAllNotNull();
    }

    /**
     * @param array $recordData
     * @return self
     */
    public function with(array $recordData)
    {
        $copy = clone $this;
        $copy->setRecordData($recordData);

        return $copy;
    }

    public function toArray(): array
    {
        $nativeData = [];
        $arrayPropItemTypeMap = self::getArrayPropItemTypeMapFromMethodOrCache();

        foreach (self::$__propTypeMap as $key => [$type, $isNative, $isNullable]) {
            switch ($type) {
                case ImmutableRecord::PHP_TYPE_STRING:
                case ImmutableRecord::PHP_TYPE_INT:
                case ImmutableRecord::PHP_TYPE_FLOAT:
                case ImmutableRecord::PHP_TYPE_BOOL:
                case ImmutableRecord::PHP_TYPE_ARRAY:
                    if (\array_key_exists($key, $arrayPropItemTypeMap) && ! self::isScalarType($arrayPropItemTypeMap[$key])) {
                        if ($isNullable && $this->{$key}() === null) {
                            $nativeData[$key] = null;
                            continue 2;
                        }

                        $nativeData[$key] = \array_map(function ($item) use ($key, &$arrayPropItemTypeMap) {
                            return $this->voTypeToNative($item, $key, $arrayPropItemTypeMap[$key]);
                        }, $this->{$key}());
                    } else {
                        $nativeData[$key] = $this->{$key}();
                    }
                    break;
                default:
                    if ($isNullable && $this->{$key}() === null) {
                        $nativeData[$key] = null;
                        continue 2;
                    }
                    $nativeData[$key] = $this->voTypeToNative($this->{$key}(), $key, $type);
            }
        }

        return $nativeData;
    }

    private function setRecordData(array $recordData)
    {
        foreach ($recordData as $key => $value) {
            $this->assertType($key, $value);
            $this->{$key} = $value;
        }
    }

    private function setNativeData(array $nativeData)
    {
        $recordData = [];
        $arrayPropItemTypeMap = self::getArrayPropItemTypeMapFromMethodOrCache();

        foreach ($nativeData as $key => $val) {
            if (! isset(self::$__propTypeMap[$key])) {
                throw new \InvalidArgumentException(\sprintf(
                    'Invalid property passed to Record %s. Got property with key ' . $key,
                    \get_called_class()
                ));
            }
            [$type, $isNative, $isNullable] = self::$__propTypeMap[$key];

            if ($val === null) {
                if (! $isNullable) {
                    throw new \RuntimeException("Got null for non nullable property $key of Record " . \get_called_class());
                }

                $recordData[$key] = null;
                continue;
            }

            switch ($type) {
                case ImmutableRecord::PHP_TYPE_STRING:
                case ImmutableRecord::PHP_TYPE_INT:
                case ImmutableRecord::PHP_TYPE_FLOAT:
                case ImmutableRecord::PHP_TYPE_BOOL:
                    $recordData[$key] = $val;
                    break;
                case ImmutableRecord::PHP_TYPE_ARRAY:
                    if (\array_key_exists($key, $arrayPropItemTypeMap) && ! self::isScalarType($arrayPropItemTypeMap[$key])) {
                        $recordData[$key] = \array_map(function ($item) use ($key, &$arrayPropItemTypeMap) {
                            return $this->fromType($item, $arrayPropItemTypeMap[$key]);
                        }, $val);
                    } else {
                        $recordData[$key] = $val;
                    }
                    break;
                default:
                    $recordData[$key] = $this->fromType($val, $type);
            }
        }

        $this->setRecordData($recordData);
    }

    private function assertAllNotNull()
    {
        foreach (self::$__propTypeMap as $key => [$type, $isNative, $isNullable]) {
            if (null === $this->{$key} && ! $isNullable) {
                throw new \InvalidArgumentException(\sprintf(
                    'Missing record data for key %s of record %s.',
                    $key,
                    __CLASS__
                ));
            }
        }
    }

    private function assertType(string $key, $value)
    {
        if (! isset(self::$__propTypeMap[$key])) {
            throw new \InvalidArgumentException(\sprintf(
                'Invalid property passed to Record %s. Got property with key ' . $key,
                __CLASS__
            ));
        }
        [$type, $isNative, $isNullable] = self::$__propTypeMap[$key];

        if (null === $value && $isNullable) {
            return;
        }

        if (! $this->isType($type, $key, $value)) {
            if ($type === ImmutableRecord::PHP_TYPE_ARRAY && \gettype($value) === ImmutableRecord::PHP_TYPE_ARRAY) {
                $arrayPropItemTypeMap = self::getArrayPropItemTypeMapFromMethodOrCache();
                throw new \InvalidArgumentException(\sprintf(
                    'Record %s data contains invalid value for property %s. Value should be an array of %s, but at least one item of the array has the wrong type.',
                    \get_called_class(),
                    $key,
                    $arrayPropItemTypeMap[$key]
                ));
            }

            throw new \InvalidArgumentException(\sprintf(
                'Record %s data contains invalid value for property %s. Expected type is %s. Got type %s.',
                \get_called_class(),
                $key,
                $type,
                (\is_object($value)
                    ? \get_class($value)
                    : \gettype($value))
            ));
        }
    }

    private function isType(string $type, string $key, $value): bool
    {
        switch ($type) {
            case ImmutableRecord::PHP_TYPE_STRING:
                return \is_string($value);
            case ImmutableRecord::PHP_TYPE_INT:
                return \is_int($value);
            case ImmutableRecord::PHP_TYPE_FLOAT:
                return \is_float($value) || \is_int($value);
            case ImmutableRecord::PHP_TYPE_BOOL:
                return \is_bool($value);
            case ImmutableRecord::PHP_TYPE_ARRAY:
                $isType = \is_array($value);

                if ($isType) {
                    $arrayPropItemTypeMap = self::getArrayPropItemTypeMapFromMethodOrCache();

                    if (\array_key_exists($key, $arrayPropItemTypeMap)) {
                        foreach ($value as $item) {
                            if (! $this->isType($arrayPropItemTypeMap[$key], $key, $item)) {
                                return false;
                            }
                        }
                    }
                }

                return $isType;
            default:
                return $value instanceof $type;
        }
    }

    private static function buildPropTypeMap()
    {
        $refObj = new \ReflectionClass(__CLASS__);

        $props = $refObj->getProperties();

        $propTypeMap = [];

        foreach ($props as $prop) {
            if ($prop->getName() === '__propTypeMap' || $prop->getName() === '__schema' || $prop->getName() === '__arrayPropItemTypeMap') {
                continue;
            }

            if (! $refObj->hasMethod($prop->getName())) {
                throw new \RuntimeException(
                    \sprintf(
                        'No method found for Record property %s of %s that has the same name.',
                        $prop->getName(),
                        __CLASS__
                    )
                );
            }

            $method = $refObj->getMethod($prop->getName());

            if (! $method->hasReturnType()) {
                throw new \RuntimeException(
                    \sprintf(
                        'Method %s of Record %s does not have a return type',
                        $method->getName(),
                        __CLASS__
                    )
                );
            }

            $type = (string) $method->getReturnType();

            $propTypeMap[$prop->getName()] = [$type, self::isScalarType($type), $method->getReturnType()->allowsNull()];
        }

        return $propTypeMap;
    }

    private static function isScalarType(string $type): bool
    {
        switch ($type) {
            case ImmutableRecord::PHP_TYPE_STRING:
            case ImmutableRecord::PHP_TYPE_INT:
            case ImmutableRecord::PHP_TYPE_FLOAT:
            case ImmutableRecord::PHP_TYPE_BOOL:
                return true;
            default:
                return false;
        }
    }

    private function fromType($value, string $type)
    {
        if (! \class_exists($type)) {
            throw new InvalidArgumentException("Type class $type not found");
        }

        //Note: gettype() returns "integer" and "boolean" which does not match the type hints "int", "bool"
        switch (\gettype($value)) {
            case 'array':
                return $type::fromArray($value);
            case 'string':
                return $type::fromString($value);
            case 'integer':
                return \method_exists($type, 'fromInt')
                    ? $type::fromInt($value)
                    : $type::fromFloat($value);
            case 'float':
            case 'double':
                return $type::fromFloat($value);
            case 'boolean':
                return $type::fromBool($value);
            default:
                throw new InvalidArgumentException("Cannot convert value to $type, because native type of value is not supported. Got " . \gettype($value));
        }
    }

    private function voTypeToNative($value, string $key, string $type)
    {
        if (\method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        if (\method_exists($value, 'toString')) {
            return $value->toString();
        }

        if (\method_exists($value, 'toInt')) {
            return $value->toInt();
        }

        if (\method_exists($value, 'toFloat')) {
            return $value->toFloat();
        }

        if (\method_exists($value, 'toBool')) {
            return $value->toBool();
        }

        throw new InvalidArgumentException("Cannot convert property $key to its native counterpart. Missing to{nativeType}() method in the type class $type.");
    }

    /**
     * @param array $arrayPropTypeMap Map of array property name to array item type
     * @return Type
     */
    private static function generateSchemaFromPropTypeMap(array $arrayPropTypeMap = []): Type
    {
        if (null === self::$__propTypeMap) {
            self::$__propTypeMap = self::buildPropTypeMap();
        }

        //To keep BC, we cache arrayPropTypeMap internally.
        //New recommended way to provide the map is that one should override the static method  self::arrayPropItemTypeMap()
        //Hence, we check if this method returns a non empty array and only in this case cache the map
        if (\count($arrayPropTypeMap) && ! \count(self::arrayPropItemTypeMap())) {
            self::$__arrayPropItemTypeMap = $arrayPropTypeMap;
        }

        $arrayPropTypeMap = self::getArrayPropItemTypeMapFromMethodOrCache();

        if (null === self::$__schema) {
            $props = [];

            foreach (self::$__propTypeMap as $prop => [$type, $isScalar, $isNullable]) {
                if ($isScalar) {
                    $props[$prop] = JsonSchema::schemaFromScalarPhpType($type, $isNullable);
                    continue;
                }

                if ($type === ImmutableRecord::PHP_TYPE_ARRAY) {
                    if (! \array_key_exists($prop, $arrayPropTypeMap)) {
                        throw new InvalidArgumentException("Missing array item type in array property map. Please provide an array item type for property $prop.");
                    }

                    $arrayItemType = $arrayPropTypeMap[$prop];

                    if (self::isScalarType($arrayItemType)) {
                        $arrayItemSchema = JsonSchema::schemaFromScalarPhpType($arrayItemType, false);
                    } elseif ($arrayItemType === ImmutableRecord::PHP_TYPE_ARRAY) {
                        throw new InvalidArgumentException("Array item type of property $prop must not be 'array', only a scalar type or an existing class can be used as array item type.");
                    } else {
                        $arrayItemSchema = JsonSchema::typeRef(self::getTypeFromClass($arrayItemType));
                    }

                    $props[$prop] = JsonSchema::array($arrayItemSchema);
                } else {
                    $props[$prop] = JsonSchema::typeRef(self::getTypeFromClass($type));
                }

                if ($isNullable) {
                    $props[$prop] = JsonSchema::nullOr($props[$prop]);
                }
            }

            self::$__schema = JsonSchema::object($props);
        }

        return self::$__schema;
    }

    private static function convertClassToTypeName(string $class): string
    {
        return \substr(\strrchr($class, '\\'), 1);
    }

    private static function getTypeFromClass(string $classOrType): string
    {
        if (! \class_exists($classOrType)) {
            return $classOrType;
        }

        $refObj = new \ReflectionClass($classOrType);

        if ($refObj->implementsInterface(ImmutableRecord::class)) {
            return \call_user_func([$classOrType, '__type']);
        }

        return self::convertClassToTypeName($classOrType);
    }

    private static function getArrayPropItemTypeMapFromMethodOrCache(): array
    {
        if (self::$__arrayPropItemTypeMap) {
            return self::$__arrayPropItemTypeMap;
        }

        return self::arrayPropItemTypeMap();
    }

    /**
     * @var array
     */
    private static $__propTypeMap;

    /**
     * @var array
     */
    private static $__arrayPropItemTypeMap;

    /**
     * @var array
     */
    private static $__schema;
}
