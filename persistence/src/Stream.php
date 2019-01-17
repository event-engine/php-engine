<?php
/**
 * This file is part of the event-engine/php-engine-persistence.
 * (c) 2017-2018 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Persistence;

final class Stream
{
    public const LOCAL_SERVICE = '__local__';
    public const WRITE_MODEL_STREAM = '$em_write_model_stream$';

    /**
     * @var string
     */
    private $serviceName;

    /**
     * @var string
     */
    private $streamName;

    public static function ofWriteModel(): self
    {
        return new self(self::LOCAL_SERVICE, self::WRITE_MODEL_STREAM);
    }

    public static function ofLocalProjection(string $streamName): self
    {
        return new self(self::LOCAL_SERVICE, $streamName);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['service_name'] ?? '',
            $data['stream_name'] ?? ''
        );
    }

    private function __construct(string $serviceName, string $streamName)
    {
        if (\mb_strlen($serviceName) === 0) {
            throw new \InvalidArgumentException('Service name must not be empty');
        }

        if (\mb_strlen($streamName) === 0) {
            throw new \InvalidArgumentException('Stream name must not be empty');
        }

        $this->serviceName = $serviceName;
        $this->streamName = $streamName;
    }

    /**
     * @return string
     */
    public function serviceName(): string
    {
        return $this->serviceName;
    }

    /**
     * @return string
     */
    public function streamName(): string
    {
        return $this->streamName;
    }

    public function withStreamName(string $streamName): self
    {
        $cp = clone $this;
        $cp->streamName = $streamName;

        return $cp;
    }

    public function isLocalService(): bool
    {
        return $this->serviceName === self::LOCAL_SERVICE;
    }

    public function toArray(): array
    {
        return [
            'service_name' => $this->serviceName,
            'stream_name' => $this->streamName,
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
