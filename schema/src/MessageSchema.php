<?php
/**
 * This file is part of event-engine/schema.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Schema;

final class MessageSchema
{
    /**
     * @var string
     */
    private $messageName;

    /**
     * @var PayloadSchema|null
     */
    private $payloadSchema;

    public function __construct(string $messageName, PayloadSchema $payloadSchema = null)
    {
        $this->messageName = $messageName;
        $this->payloadSchema = $payloadSchema;
    }

    /**
     * @return string
     */
    public function messageName(): string
    {
        return $this->messageName;
    }

    /**
     * @return PayloadSchema|null
     */
    public function payloadSchema(): ?PayloadSchema
    {
        return $this->payloadSchema;
    }
}
