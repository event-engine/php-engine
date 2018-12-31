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
use EventEngine\Schema\ResponseTypeSchema;

final class Query
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var PayloadSchema
     */
    private $payloadSchema;

    /**
     * @var ResponseTypeSchema
     */
    private $returnType;

    public function __construct(string $name, PayloadSchema $payloadSchema, ResponseTypeSchema $response)
    {
        $this->name = $name;
        $this->payloadSchema = $payloadSchema;
        $this->returnType = $response;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function payloadSchema(): PayloadSchema
    {
        return $this->payloadSchema;
    }

    public function returnType(): ResponseTypeSchema
    {
        return $this->returnType;
    }
}
