<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\DocumentStore\Filter;

use Codeliner\ArrayReader\ArrayReader;

final class AnyOfFilter implements Filter
{
    /**
     * Nested props are accessed using dot notation
     *
     * @var string
     */
    private $prop;

    /**
     * @var mixed[]
     */
    private $valList;

    public function __construct(string $prop, array $valList)
    {
        $this->prop = $prop;
        $this->valList = $valList;
    }

    public function match(array $doc, string $docId): bool
    {
        $reader = new ArrayReader($doc);

        $prop = $reader->mixedValue($this->prop, self::NOT_SET_PROPERTY);

        if ($prop === self::NOT_SET_PROPERTY) {
            return false;
        }

        return \in_array($prop, $this->valList);
    }

    /**
     * @return string
     */
    public function prop(): string
    {
        return $this->prop;
    }

    /**
     * @return mixed[]
     */
    public function valList(): array
    {
        return $this->valList;
    }
}
