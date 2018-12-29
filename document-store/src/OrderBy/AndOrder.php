<?php
/**
 * This file is part of event-engine/php-document-sore.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\DocumentStore\OrderBy;

final class AndOrder implements OrderBy
{
    const TYPE_DIRECTION_ASC = OrderBy::ASC;
    const TYPE_DIRECTION_DESC = OrderBy::DESC;
    const TYPE_AND = 'and';

    private $orderByA;

    private $orderByB;

    public static function by(OrderBy $a, OrderBy $b): AndOrder
    {
        return new self($a, $b);
    }

    public static function fromArray(array $data): OrderBy
    {
        return new self(
            self::arrayToOrderBy($data['a'] ?? []),
            self::arrayToOrderBy($data['b'] ?? [])
        );
    }

    private function __construct(OrderBy $a, OrderBy $b)
    {
        $this->orderByA = $a;
        $this->orderByB = $b;

        if ($this->orderByA instanceof AndOrder) {
            throw new \InvalidArgumentException(
                \sprintf(
                    'First element of %s must not be again an AndOrderBy. This is only allowed for the alternative element.',
                    __CLASS__
                )
            );
        }
    }

    public function a(): OrderBy
    {
        return $this->orderByA;
    }

    public function b(): OrderBy
    {
        return $this->orderByB;
    }

    public function toArray(): array
    {
        return [
            'a' => $this->orderByToArray($this->orderByA),
            'b' => $this->orderByToArray($this->orderByB),
        ];
    }

    public function equals($other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->toArray() === $other->toArray();
    }

    public function __toString(): string
    {
        return \json_encode($this->toArray());
    }

    private function orderByToArray(OrderBy $orderBy): array
    {
        switch (\get_class($orderBy)) {
            case Asc::class:
                return [
                    'type' => self::TYPE_DIRECTION_ASC,
                    'data' => $orderBy->toArray(),
                ];
            case Desc::class:
                return [
                    'type' => self::TYPE_DIRECTION_DESC,
                    'data' => $orderBy->toArray(),
                ];
            case AndOrder::class:
                return [
                    'type' => self::TYPE_AND,
                    'data' => $orderBy->toArray(),
                ];
            default:
                throw new \RuntimeException('Unknown OrderBy class. Got ' . \get_class($orderBy));
        }
    }

    private static function arrayToOrderBy(array $data): OrderBy
    {
        switch ($data['type'] ?? '') {
            case self::TYPE_DIRECTION_ASC:
                return Asc::fromArray($data['data'] ?? []);
            case self::TYPE_DIRECTION_DESC:
                return Desc::fromArray($data['data'] ?? []);
            case self::TYPE_AND:
                return AndOrder::fromArray($data['data'] ?? []);
            default:
                throw new \RuntimeException('Unknown OrderBy type. Got ' . $data['type'] ?? 'empty type');
        }
    }
}
