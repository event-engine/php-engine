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

final class NotFilter implements Filter
{
    /**
     * @var Filter
     */
    private $innerFilter;

    public function __construct(Filter $innerFilter)
    {
        $this->innerFilter = $innerFilter;
    }

    public function innerFilter(): Filter
    {
        return $this->innerFilter;
    }

    public function match(array $doc): bool
    {
        return $this->innerFilter()->match($doc) !== true;
    }
}
