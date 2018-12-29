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

trait HasAnnotations
{
    /**
     * @var string|null
     */
    protected $title;

    /**
     * @var string|null
     */
    protected $description;

    public function entitled(string $title): self
    {
        $cp = clone $this;

        $cp->title = $title;

        return $cp;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function describedAs(string $description): self
    {
        $cp = clone $this;

        $cp->description = $description;

        return $cp;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function annotations(): array
    {
        $annotations = [];

        if (null !== $this->title) {
            $annotations['title'] = $this->title;
        }

        if (null !== $this->description) {
            $annotations['description'] = $this->description;
        }

        return $annotations;
    }
}
