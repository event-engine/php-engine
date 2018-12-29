<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Logger;

use EventEngine\Messaging\Message;
use Psr\Log\LoggerInterface;

final class LogMsgTemplateEngine implements LoggerInterface
{
    public const CTX_MSG = 'msg';

    /**
     * @var LoggerInterface
     */
    private $innerLogger;

    /**
     * @var callable
     */
    private $onLogPayload;

    /**
     * @var callable
     */
    private $onLogMetadata;

    /**
     * @var string
     */
    private $prefix;

    public function __construct(LoggerInterface $innerLogger, callable $onLogPayload = null, callable $onLogMetadata = null, string $prefix = '[EventEngine]')
    {
        $this->innerLogger = $innerLogger;
        $this->onLogPayload = $onLogPayload ?? function(array $payload): array {return $payload;};
        $this->onLogMetadata = $onLogMetadata ?? function(array $metadata): array {return $metadata;};
        $this->prefix = $prefix;
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function emergency($message, array $context = array())
    {
        $this->innerLogger->emergency($this->prepareMsg($message, $context));
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function alert($message, array $context = array())
    {
        $this->innerLogger->alert($this->prepareMsg($message, $context));
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function critical($message, array $context = array())
    {
        $this->innerLogger->critical($this->prepareMsg($message, $context));
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function error($message, array $context = array())
    {
        $this->innerLogger->error($this->prepareMsg($message, $context));
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function warning($message, array $context = array())
    {
        $this->innerLogger->warning($this->prepareMsg($message, $context));
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function notice($message, array $context = array())
    {
        $this->innerLogger->notice($this->prepareMsg($message, $context));
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function info($message, array $context = array())
    {
        $this->innerLogger->info($this->prepareMsg($message, $context));
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function debug($message, array $context = array())
    {
        $this->innerLogger->debug($this->prepareMsg($message, $context));
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        $this->innerLogger->log($level, $this->prepareMsg($message, $context));
    }

    private function prepareMsg(string $message, array $context): string
    {
        if(\array_key_exists(self::CTX_MSG, $context)) {
            /** @var Message $msg */
            $msg = $context[self::CTX_MSG];
            $msgId = $msg->uuid()->toString();
            $payload = call_user_func($this->onLogPayload, $msg->payload());
            $metadata = call_user_func($this->onLogMetadata, $msg->metadata());
            $message = \str_replace(
                ['{MessageId}', '{MessageName}', '{MessageType}', '{Payload}', '{Metadata}', '{Message}'],
                [ $msgId, $msg->messageName(), $msg->messageType(), json_encode($payload), json_encode($metadata), json_encode([
                    'name' => $msg->messageName(),
                    'type' => $msg->messageType(),
                    'uuid' => $msg->uuid()->toString(),
                    'payload' => $payload,
                    'metadata' => $metadata
                ])],
                $message
            );
        }

        return $this->prefix . ' ' . $message;
    }
}
