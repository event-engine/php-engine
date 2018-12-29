<?php
/**
 * This file is part of event-engine/php-json-schema.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\JsonSchema\Type;

use EventEngine\JsonSchema\AnnotatedType;
use EventEngine\JsonSchema\JsonSchema;

class IntType implements AnnotatedType
{
    use NullableType,
        HasAnnotations;

    /**
     * @var string|array
     */
    private $type = JsonSchema::TYPE_INT;

    /**
     * @var null|array
     */
    private $validation;

    public function __construct(array $validation = null)
    {
        $this->validation = $validation;
    }

    public function toArray(): array
    {
        return \array_merge(['type' => $this->type], (array) $this->validation, $this->annotations());
    }

    public function withMinimum(int $min): self
    {
        $cp = clone $this;

        $validation = (array) $this->validation;

        $validation['minimum'] = $min;

        $cp->validation = $validation;

        return $cp;
    }

    public function withMaximum(int $max): self
    {
        $cp = clone $this;

        $validation = (array) $this->validation;

        $validation['maximum'] = $max;

        $cp->validation = $validation;

        return $cp;
    }

    public function withRange(int $min, int $max): self
    {
        $cp = clone $this;

        $validation = (array) $this->validation;

        $validation['minimum'] = $min;
        $validation['maximum'] = $max;

        $cp->validation = $validation;

        return $cp;
    }
}
