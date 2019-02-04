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

final class CommandMap
{
    private $commands = [];

    public static function fromEventEngineMap(array $map): self
    {
        $commands = [];

        foreach ($map as $name => $payloadSchema) {
            $commands[] = new Command($name, $payloadSchema);
        }

        return new self(...$commands);
    }

    private function __construct(Command ...$commands)
    {
        foreach ($commands as $command) $this->commands[$command->name()] = $command;
    }

    /**
     * @return Command[]
     */
    public function commands(): array
    {
        return $this->commands;
    }
}
