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

use EventEngine\Schema\PayloadSchema;

final class Event
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var PayloadSchema
     */
    private $payloadSchema;

    public function __construct(string $name, PayloadSchema $payloadSchema)
    {
        $this->name = $name;
        $this->payloadSchema = $payloadSchema;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return PayloadSchema
     */
    public function payloadSchema(): PayloadSchema
    {
        return $this->payloadSchema;
    }
}
