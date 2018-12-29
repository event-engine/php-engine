<?php
/**
 * This file is part of event-engine/php-document-store.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\DocumentStore;

use Codeliner\ArrayReader\ArrayReader;
use EventEngine\Persistence\InMemoryConnection;

final class InMemoryDocumentStore implements DocumentStore
{
    /**
     * @var InMemoryConnection
     */
    private $inMemoryConnection;

    public function __construct(InMemoryConnection $inMemoryConnection)
    {
        $this->inMemoryConnection = $inMemoryConnection;
    }

    /**
     * @return string[] list of all available collections
     */
    public function listCollections(): array
    {
        return \array_keys($this->inMemoryConnection['documents']);
    }

    /**
     * @param string $prefix
     * @return string[] of collection names
     */
    public function filterCollectionsByPrefix(string $prefix): array
    {
        return \array_filter(\array_keys($this->inMemoryConnection['documents']), function (string $colName) use ($prefix): bool {
            return \mb_strpos($colName, $prefix) === 0;
        });
    }

    /**
     * @param string $collectionName
     * @return bool
     */
    public function hasCollection(string $collectionName): bool
    {
        return \array_key_exists($collectionName, $this->inMemoryConnection['documents']);
    }

    /**
     * @param string $collectionName
     * @param Index[] ...$indices
     */
    public function addCollection(string $collectionName, Index ...$indices): void
    {
        $this->inMemoryConnection['documents'][$collectionName] = [];
    }

    /**
     * @param string $collectionName
     * @throws \Throwable if dropping did not succeed
     */
    public function dropCollection(string $collectionName): void
    {
        if ($this->hasCollection($collectionName)) {
            unset($this->inMemoryConnection['documents'][$collectionName]);
        }
    }

    /**
     * @param string $collectionName
     * @param string $docId
     * @param array $doc
     * @throws \Throwable if adding did not succeed
     */
    public function addDoc(string $collectionName, string $docId, array $doc): void
    {
        $this->assertHasCollection($collectionName);

        if ($this->hasDoc($collectionName, $docId)) {
            throw new \RuntimeException("Cannot add doc with id $docId. The doc already exists in collection $collectionName");
        }

        $this->inMemoryConnection['documents'][$collectionName][$docId] = $doc;
    }

    /**
     * @param string $collectionName
     * @param string $docId
     * @param array $docOrSubset
     * @throws \Throwable if updating did not succeed
     */
    public function updateDoc(string $collectionName, string $docId, array $docOrSubset): void
    {
        $this->assertDocExists($collectionName, $docId);

        $this->inMemoryConnection['documents'][$collectionName][$docId] = \array_merge(
            $this->inMemoryConnection['documents'][$collectionName][$docId],
            $docOrSubset
        );
    }

    /**
     * @param string $collectionName
     * @param DocumentStore\Filter\Filter $filter
     * @param array $set
     * @throws \Throwable in case of connection error or other issues
     */
    public function updateMany(string $collectionName, DocumentStore\Filter\Filter $filter, array $set): void
    {
        $docs = $this->filterDocs($collectionName, $filter);

        foreach ($docs as $docId => $doc) {
            $this->updateDoc($collectionName, $docId, $set);
        }
    }

    /**
     * Same as updateDoc except that doc is added to collection if it does not exist.
     *
     * @param string $collectionName
     * @param string $docId
     * @param array $docOrSubset
     * @throws \Throwable if insert/update did not succeed
     */
    public function upsertDoc(string $collectionName, string $docId, array $docOrSubset): void
    {
        if ($this->hasDoc($collectionName, $docId)) {
            $this->updateDoc($collectionName, $docId, $docOrSubset);
        } else {
            $this->addDoc($collectionName, $docId, $docOrSubset);
        }
    }

    /**
     * @param string $collectionName
     * @param string $docId
     * @throws \Throwable if deleting did not succeed
     */
    public function deleteDoc(string $collectionName, string $docId): void
    {
        if ($this->hasDoc($collectionName, $docId)) {
            unset($this->inMemoryConnection['documents'][$collectionName][$docId]);
        }
    }

    /**
     * @param string $collectionName
     * @param DocumentStore\Filter\Filter $filter
     * @throws \Throwable in case of connection error or other issues
     */
    public function deleteMany(string $collectionName, DocumentStore\Filter\Filter $filter): void
    {
        $docs = $this->filterDocs($collectionName, $filter);

        foreach ($docs as $docId => $doc) {
            $this->deleteDoc($collectionName, $docId);
        }
    }

    /**
     * @param string $collectionName
     * @param string $docId
     * @return array|null
     */
    public function getDoc(string $collectionName, string $docId): ?array
    {
        return $this->inMemoryConnection['documents'][$collectionName][$docId] ?? null;
    }

    /**
     * @param string $collectionName
     * @param DocumentStore\Filter\Filter $filter
     * @param int|null $skip
     * @param int|null $limit
     * @param DocumentStore\OrderBy\OrderBy|null $orderBy
     * @return \Traversable list of docs
     */
    public function filterDocs(
        string $collectionName,
        DocumentStore\Filter\Filter $filter,
        int $skip = null,
        int $limit = null,
        DocumentStore\OrderBy\OrderBy $orderBy = null): \Traversable
    {
        $this->assertHasCollection($collectionName);

        $filteredDocs = [];

        foreach ($this->inMemoryConnection['documents'][$collectionName] as $docId => $doc) {
            if ($filter->match($doc)) {
                $filteredDocs[$docId] = $doc;
            }
        }

        if ($orderBy !== null) {
            $this->sort($filteredDocs, $orderBy);
        }

        if ($skip !== null) {
            $filteredDocs = \array_slice($filteredDocs, $skip, $limit);
        } elseif ($limit !== null) {
            $filteredDocs = \array_slice($filteredDocs, 0, $limit);
        }

        return new \ArrayIterator($filteredDocs);
    }

    private function hasDoc(string $collectionName, string $docId): bool
    {
        if (! $this->hasCollection($collectionName)) {
            return false;
        }

        return \array_key_exists($docId, $this->inMemoryConnection['documents'][$collectionName]);
    }

    private function assertHasCollection(string $collectionName): void
    {
        if (! $this->hasCollection($collectionName)) {
            throw new \RuntimeException("Unknown collection $collectionName");
        }
    }

    private function assertDocExists(string $collectionName, string $docId): void
    {
        $this->assertHasCollection($collectionName);

        if (! $this->hasDoc($collectionName, $docId)) {
            throw new \RuntimeException("Doc with id $docId does not exist in collection $collectionName");
        }
    }

    private function sort(&$docs, DocumentStore\OrderBy\OrderBy $orderBy)
    {
        $defaultCmp = function ($a, $b) {
            return ($a < $b) ? -1 : (($a > $b) ? 1 : 0);
        };

        $getField = function (array $doc, DocumentStore\OrderBy\OrderBy $orderBy) {
            if ($orderBy instanceof DocumentStore\OrderBy\Asc || $orderBy instanceof DocumentStore\OrderBy\Desc) {
                $field = $orderBy->prop();

                return (new ArrayReader($doc))->mixedValue($field);
            }

            throw new \RuntimeException(\sprintf(
                'Unable to get field from doc: %s. Given OrderBy is neither an instance of %s nor %s',
                \json_encode($doc),
                DocumentStore\OrderBy\Asc::class,
                DocumentStore\OrderBy\Desc::class
            ));
        };

        $docCmp = null;
        $docCmp = function (array $docA, array $docB, DocumentStore\OrderBy\OrderBy $orderBy) use (&$docCmp, $defaultCmp, $getField) {
            $orderByB = null;

            if ($orderBy instanceof DocumentStore\OrderBy\AndOrder) {
                $orderByB = $orderBy->b();
                $orderBy = $orderBy->a();
            }

            $valA = $getField($docA, $orderBy);
            $valB = $getField($docB, $orderBy);

            if (\is_string($valA) && \is_string($valB)) {
                $orderResult = \strcasecmp($valA, $valB);
            } else {
                $orderResult = $defaultCmp($valA, $valB);
            }

            if ($orderResult === 0 && $orderByB) {
                $orderResult = $docCmp($docA, $docB, $orderByB);
            }

            if ($orderResult === 0) {
                return 0;
            }

            if ($orderBy instanceof DocumentStore\OrderBy\Desc) {
                return $orderResult * -1;
            }

            return $orderResult;
        };

        \usort($docs, function (array $docA, array $docB) use ($orderBy, $docCmp) {
            return $docCmp($docA, $docB, $orderBy);
        });
    }
}
