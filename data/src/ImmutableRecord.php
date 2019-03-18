<?php
/**
 * This file is part of event-engine/php-engine-data.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Data;

use EventEngine\Schema\TypeSchema;

interface ImmutableRecord
{
    const PHP_TYPE_STRING = 'string';
    const PHP_TYPE_INT = 'int';
    const PHP_TYPE_FLOAT = 'float';
    const PHP_TYPE_BOOL = 'bool';
    const PHP_TYPE_ARRAY = 'array';

    /**
     * @param array $recordData
     * @return static
     */
    public static function fromRecordData(array $recordData);

    /**
     * @param array $nativeData
     * @return static
     */
    public static function fromArray(array $nativeData);

    /**
     * @param array $recordData
     * @return static
     */
    public function with(array $recordData);

    public function toArray(): array;
}
