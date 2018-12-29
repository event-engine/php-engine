<?php
/**
 * This file is part of event-engine/schema.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Schema\Exception;

use Throwable;

class InvalidPayload extends \InvalidArgumentException implements SchemaException
{
    /**
     * InvalidPayload constructor.
     *
     * Provide named constructors and don't override the default especially the exception code!
     *
     * @param string $message
     * @param Throwable|null $previous
     */
    protected function __construct(string $message = "", Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
