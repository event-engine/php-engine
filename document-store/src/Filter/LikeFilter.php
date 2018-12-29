<?php
/**
 * This file is part of event-engine/php-document-sore.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\DocumentStore\Filter;

use Codeliner\ArrayReader\ArrayReader;

final class LikeFilter implements Filter
{
    /**
     * Nested props are accessed using dot notation
     *
     * @var string
     */
    private $prop;

    /**
     * Search string with placeholder % matching any char
     *
     * Search is case insensitive
     *
     * %word% will match any string containing "word"
     * %word will match any string ending with "word"
     * word% will match any string starting with "word"
     * wo%rd will only match "wo%rd" because like operation is only considered at beginning and/or end of string
     * % will match all strings (incl. empty strings)
     *
     * @var string
     */
    private $val;

    public function __construct(string $prop, string $val)
    {
        if (\strlen($val) === 0) {
            throw new \InvalidArgumentException('Like filter must not be empty');
        }

        $this->prop = $prop;
        $this->val = $val;
    }

    /**
     * @return string
     */
    public function prop(): string
    {
        return $this->prop;
    }

    /**
     * @return mixed
     */
    public function val()
    {
        return $this->val;
    }

    public function match(array $doc): bool
    {
        $reader = new ArrayReader($doc);

        $prop = $reader->mixedValue($this->prop, self::NOT_SET_PROPERTY);

        if ($prop === self::NOT_SET_PROPERTY || ! \is_string($prop)) {
            return false;
        }

        if ($this->val === '%') {
            return true;
        }

        $likeStart = $this->val[0] === '%';
        $likeEnd = $this->val[\mb_strlen($this->val) - 1] === '%';

        $prop = \mb_strtolower($prop);
        $val = \mb_strtolower($this->val);

        if ($likeStart) {
            $val = \mb_substr($val, 1);
        }

        if ($likeEnd) {
            $val = \mb_substr($val, 0, \mb_strlen($val) - 2);
        }

        $pos = \mb_strpos($prop, $val);

        if ($pos === false) {
            return false;
        }

        if (! $likeStart && $pos !== 0) {
            return false;
        }

        if (! $likeEnd) {
            $posRev = \mb_strpos(\strrev($prop), \strrev($val));

            if ($posRev !== 0) {
                return false;
            }
        }

        return true;
    }
}
