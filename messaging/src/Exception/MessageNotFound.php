<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Messaging\Exception;

use Throwable;

class MessageNotFound extends \RuntimeException implements MessagingException
{
    public static function withMessageName(string $messageName): self
    {
        return new self("Unknown message $messageName");
    }

    public function __construct(string $message = "", Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}
