<?php
/**
 * This file is part of event-engine/php-engine-persistence.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Persistence\Exception;

class TransactionNotStarted extends \RuntimeException implements TransactionException
{
    public static function commit()
    {
        return new self('Transaction is not started. Can not commit anything.');
    }

    public static function rollback()
    {
        return new self('Transaction is not started. Can not rollback anything.');
    }
}
