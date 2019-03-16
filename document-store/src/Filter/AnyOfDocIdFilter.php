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

final class AnyOfDocIdFilter implements Filter
{
    /**
     * @var mixed[]
     */
    private $valList;

    public function __construct(array $valList)
    {
        $this->valList = $valList;
    }

    public function match(array $doc, string $docId): bool
    {
        return \in_array($docId, $this->valList);
    }

    /**
     * @return mixed[]
     */
    public function valList(): array
    {
        return $this->valList;
    }
}
