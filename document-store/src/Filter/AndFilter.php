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

final class AndFilter implements Filter
{
    private $aFilter;

    private $bFilter;

    public function __construct(Filter $aFilter, Filter $bFilter)
    {
        $this->aFilter = $aFilter;
        $this->bFilter = $bFilter;
    }

    /**
     * @return Filter
     */
    public function aFilter(): Filter
    {
        return $this->aFilter;
    }

    /**
     * @return Filter
     */
    public function bFilter(): Filter
    {
        return $this->bFilter;
    }

    public function match(array $doc, string $docId): bool
    {
        return $this->aFilter->match($doc, $docId) && $this->bFilter->match($doc, $docId);
    }
}
