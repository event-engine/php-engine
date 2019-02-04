<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Schema\MessageBox;

use EventEngine\Schema\Exception\RuntimeException;

final class QueryMap
{
    private $queries = [];

    public static function fromEventEngineMapAndQueryDescriptions(array $map, array $descriptions): self
    {
        $queries = [];

        foreach ($map as $name => $payloadSchema) {
            $queryDesc = $descriptions[$name] ?? null;

            if(!$queryDesc) {
                throw new RuntimeException("Missing query description for query $name");
            }

            $queries[] = new Query($name, $payloadSchema, $queryDesc['returnType']);
        }

        return new self(...$queries);
    }

    private function __construct(Query ...$queries)
    {
        foreach ($queries as $query) $this->queries[$query->name()] = $query;
    }

    /**
     * @return Query[]
     */
    public function queries(): array
    {
        return $this->queries;
    }
}
