<?php
/**
 * This file is part of event-engine/php-json-schema.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\JsonSchema;

use EventEngine\JsonSchema\Exception\InvalidArgumentException;
use Opis\JsonSchema\Schema as OpisSchema;
use Opis\JsonSchema\Validator;

class OpisJsonSchema extends AbstractJsonSchema
{
    /**
     * @var Validator
     */
    private static $jsonValidator;

    public function assert(string $objectName, array $data, array $jsonSchema)
    {
        if ($data === [] && JsonSchema::isObjectType($jsonSchema)) {
            $data = new \stdClass();
        }

        if (empty($jsonSchema['properties'])) {
            // properties must be an object
            unset($jsonSchema['properties']);
        }

        $enforcedObjectData = \json_decode(\json_encode($data));

        $result = $this->jsonValidator()->schemaValidation($enforcedObjectData, OpisSchema::fromJsonString(\json_encode($jsonSchema)));

        if (! $result->isValid()) {
            $errors = [];

            foreach ($result->getErrors() as $error) {
                $errors[] = \sprintf('[%s] %s', $error->keyword(), \json_encode($error->keywordArgs(), JSON_PRETTY_PRINT));

                if ($error->subErrorsCount()) {
                    foreach ($error->subErrors() as $subError) {
                        $errors[] = \sprintf("[%s] %s\n", $subError->keyword(), \json_encode($subError->keywordArgs(), JSON_PRETTY_PRINT));
                    }
                }
            }

            throw new InvalidArgumentException(
                "Validation of $objectName failed: " . \implode("\n", $errors)
            );
        }
    }

    private function jsonValidator(): Validator
    {
        if (null === self::$jsonValidator) {
            self::$jsonValidator = new Validator();
        }

        return self::$jsonValidator;
    }
}