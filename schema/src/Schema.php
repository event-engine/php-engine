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

interface Schema
{
    public function assertPayload(string $messageName, array $payload, PayloadSchema $payloadSchema, TypeSchemaMap $typeSchemaMap): void;

    public function assertPayloadSchema(string $messageName, PayloadSchema $payloadSchema): void;

    public function buildPayloadSchemaFromArray(array $payloadSchema): PayloadSchema;

    public function emptyPayloadSchema(): PayloadSchema;

    public function assertResponseTypeSchema(string $typeName, ResponseTypeSchema $responseTypeSchema): void;

    public function buildResponseTypeSchemaFromArray(string $typeName, array $typeSchema): ResponseTypeSchema;

    public function assertInputTypeSchema(string $typeName, InputTypeSchema $inputTypeSchema): void;

    public function buildInputTypeSchemaFromArray(string $typeName, array $typeSchema): InputTypeSchema;
}
