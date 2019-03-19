<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Persistence;

final class StreamCollection
{
    /**
     * @var Stream[]
     */
    private $items;

    public static function fromArray(array $items): self
    {
        return new self(...array_map(function (array $item) {
            return Stream::fromArray($item);
        }, $items));
    }

    public static function fromItems(Stream ...$items): self
    {
        return new self(...$items);
    }

    public static function emptyList(): self
    {
        return new self([]);
    }

    private function __construct(Stream ...$items)
    {
        $this->items = $items;
    }

    public function push(Stream $item): self
    {
        $copy = clone $this;
        $copy->items[] = $item;
        return $copy;
    }

    public function pop(): self
    {
        $copy = clone $this;
        \array_pop($copy->items);
        return $copy;
    }

    public function first(): ?Stream
    {
        return $this->items[0] ?? null;
    }

    public function last(): ?Stream
    {
        if (count($this->items) === 0) {
            return null;
        }

        return $this->items[count($this->items) - 1];
    }

    public function contains(Stream $item): bool
    {
        foreach ($this->items as $existingItem) {
            if ($existingItem->equals($item)) {
                return true;
            }
        }

        return false;
    }

    public function containsSourceStreamName(string $streamName): bool
    {
        foreach ($this->items as $existingItem) {
            if ($existingItem->streamName() === $streamName) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Stream[]
     */
    public function items(): array
    {
        return $this->items;
    }

    public function toArray(): array
    {
        return \array_map(function (Stream $item) {
            return $item->toArray();
        }, $this->items);
    }

    public function equals($other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        return $this->toArray() === $other->toArray();
    }

    public function __toString(): string
    {
        return \json_encode($this->toArray());
    }
}
