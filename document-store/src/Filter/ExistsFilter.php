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

final class ExistsFilter implements Filter
{
    /**
     * Nested props are accessed using dot notation
     *
     * @var string
     */
    private $prop;

    public function __construct(string $prop)
    {
        $this->prop = $prop;
    }

    /**
     * @return string
     */
    public function prop(): string
    {
        return $this->prop;
    }

    public function match(array $doc, string $docId): bool
    {
        $reader = new ArrayReader($doc);

        return $reader->pathExists($this->prop);
    }
}
