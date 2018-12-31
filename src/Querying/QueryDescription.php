<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Querying;

use EventEngine\EventEngine;
use EventEngine\Exception\InvalidArgumentException;
use EventEngine\Schema\ResponseTypeSchema;
use EventEngine\Util\VariableType;

final class QueryDescription
{
    /**
     * @var EventEngine
     */
    private $eventEngine;

    /**
     * @var string
     */
    private $queryName;

    /**
     * @var string
     */
    private $resolver;

    /**
     * @var ResponseTypeSchema
     */
    private $returnType;

    public function __construct(string $queryName, EventEngine $eventEngine)
    {
        $this->eventEngine = $eventEngine;
        $this->queryName = $queryName;
    }

    public function __invoke(): array
    {
        $this->assertResolverAndReturnTypeAreSet();

        return [
            'name' => $this->queryName,
            'resolver' => $this->resolver,
            'returnType' => $this->returnType->toArray(),
        ];
    }

    public function resolveWith(string $resolver): self
    {
        $this->resolver = $resolver;

        return $this;
    }

    public function setReturnType(ResponseTypeSchema $typeSchema): self
    {
        $this->eventEngine->schema()->assertResponseTypeSchema("Query return type {$this->queryName}", $typeSchema);
        $this->returnType = $typeSchema;

        return $this;
    }

    public function returnType(): ?ResponseTypeSchema
    {
        return $this->returnType;
    }

    private function assertResolverAndReturnTypeAreSet(): void
    {
        if (! $this->resolver) {
            throw new \RuntimeException("Missing resolver for query {$this->queryName}");
        }

        if (! $this->returnType) {
            throw new \RuntimeException("Missing return type for query {$this->queryName}");
        }
    }
}
