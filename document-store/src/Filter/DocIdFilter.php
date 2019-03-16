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

final class DocIdFilter implements Filter
{
    private $val;

    public function __construct(string $val)
    {
        $this->val = $val;
    }


    /**
     * @return string
     */
    public function val()
    {
        return $this->val;
    }

    public function match(array $doc, string $docId): bool
    {
        return $this->val === $docId;
    }
}
