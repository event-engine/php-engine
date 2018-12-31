<?php
/**
 * This file is part of event-engine/php-document-store.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\DocumentStore;

final class FieldIndex implements Index
{
    /**
     * @var string
     */
    private $field;

    /**
     * @var bool
     */
    private $unique;

    /**
     * @var int
     */
    private $sort;

    public static function forFieldInMultiFieldIndex(string $field): self
    {
        return self::forField($field);
    }

    public static function forField(string $field, int $sort = self::SORT_ASC, bool $unique = false): self
    {
        return new self($field, $sort, $unique);
    }

    public static function fromArray(array $data): Index
    {
        return new self(
            $data['field'] ?? '',
            $data['sort'] ?? self::SORT_ASC,
            $data['unique'] ?? false
        );
    }

    private function __construct(
        string $field,
        int $sort,
        bool $unique
    ) {
        if (\mb_strlen($field) === 0) {
            throw new \InvalidArgumentException('Field must not be empty');
        }

        if ($sort !== self::SORT_ASC && $sort !== self::SORT_DESC) {
            throw new \InvalidArgumentException('Sort order should be either ' . self::SORT_ASC . ' or ' . self::SORT_DESC);
        }

        $this->field = $field;
        $this->sort = $sort;
        $this->unique = $unique;
    }

    /**
     * @return string
     */
    public function field(): string
    {
        return $this->field;
    }

    /**
     * @return int
     */
    public function sort(): int
    {
        return $this->sort;
    }

    /**
     * @return bool
     */
    public function unique(): bool
    {
        return $this->unique;
    }

    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'sort' => $this->sort,
            'unique' => $this->unique,
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
}
