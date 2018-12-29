<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Data;

final class ImmutableRecordDataConverter implements DataConverter
{
    private $typeMap = [];

    public function __construct(array $typeToClassMap = [])
    {
        $this->typeMap = $typeToClassMap;
    }

    public function convertDataToArray(string $type, $data): array
    {
        if (\is_array($data)) {
            return $data;
        }

        if ($data instanceof ImmutableRecord || is_callable([$data, 'toArray'])) {
            return $data->toArray();
        }

        return (array) \json_decode(\json_encode($data), true);
    }

    public function canConvertTypeToData(string $type): bool
    {
        $class = $this->getClassOfType($type);

        if(!class_exists($class)) {
            return false;
        }

        return is_callable([$class, 'fromArray']);
    }

    public function convertArrayToData(string $type, array $data)
    {
        $class = $this->getClassOfType($type);

        return $type::fromArray($data);
    }

    private function getClassOfType(string $type): string
    {
        return $this->typeMap[$type] ?? $type;
    }
}
