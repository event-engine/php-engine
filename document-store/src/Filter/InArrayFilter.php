<?php
/**
 * This file is part of event-engine/php-document-sore.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\DocumentStore\Filter;

use Codeliner\ArrayReader\ArrayReader;

final class InArrayFilter implements Filter
{
    /**
     * Nested props are accessed using dot notation
     *
     * @var string
     */
    private $prop;

    /**
     * @var mixed
     */
    private $val;

    public function __construct(string $prop, $val)
    {
        $this->prop = $prop;
        $this->val = $val;
    }

    /**
     * @return string
     */
    public function prop(): string
    {
        return $this->prop;
    }

    /**
     * @return mixed
     */
    public function val()
    {
        return $this->val;
    }

    public function match(array $doc): bool
    {
        $reader = new ArrayReader($doc);

        $prop = $reader->mixedValue($this->prop, self::NOT_SET_PROPERTY);

        if (! \is_array($prop)) {
            return false;
        }

        return \in_array($this->val, $prop);
    }
}
