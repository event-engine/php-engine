<?php
/**
 * This file is part of event-engine/php-engine.
 * (c) 2018-2019 prooph software GmbH <contact@prooph.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace EventEngine\Persistence;

use EventEngine\DocumentStore\DocumentStore;
use EventEngine\DocumentStore\Filter\Filter;
use EventEngine\DocumentStore\Index;
use EventEngine\DocumentStore\OrderBy\OrderBy;

trait InnerDocumentStore
{
    /**
     * @var DocumentStore
     */
    private $documentStore;

    /**
     * @return string[] list of all available collections
     */
    public function listCollections(): array
    {
        return $this->documentStore->listCollections();
    }

    /**
     * @param string $prefix
     * @return string[] of collection names
     */
    public function filterCollectionsByPrefix(string $prefix): array
    {
        return $this->documentStore->filterCollectionsByPrefix($prefix);
    }

    /**
     * @param string $collectionName
     * @return bool
     */
    public function hasCollection(string $collectionName): bool
    {
        return $this->documentStore->hasCollection($collectionName);
    }

    /**
     * @param string $collectionName
     * @param Index[] ...$indices
     */
    public function addCollection(string $collectionName, Index ...$indices): void
    {
        $this->documentStore->addCollection($collectionName, ...$indices);
    }


    /**
     * @param string $collectionName
     * @throws \Throwable if dropping did not succeed
     */
    public function dropCollection(string $collectionName): void
    {
        $this->documentStore->dropCollection($collectionName);
    }

    /**
     * @param string $collectionName
     * @param string $docId
     * @param array $doc
     * @throws \Throwable if adding did not succeed
     */
    public function addDoc(string $collectionName, string $docId, array $doc): void
    {
        $this->documentStore->addDoc($collectionName, $docId, $doc);
    }

    /**
     * @param string $collectionName
     * @param string $docId
     * @param array $docOrSubset
     * @throws \Throwable if updating did not succeed
     */
    public function updateDoc(string $collectionName, string $docId, array $docOrSubset): void
    {
        $this->documentStore->updateDoc($collectionName, $docId, $docOrSubset);
    }

    /**
     * @param string $collectionName
     * @param Filter $filter
     * @param array $set
     * @throws \Throwable in case of connection error or other issues
     */
    public function updateMany(string $collectionName, Filter $filter, array $set): void
    {
        $this->documentStore->updateMany($collectionName, $filter, $set);
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
        $this->documentStore->upsertDoc($collectionName, $docId, $docOrSubset);
    }

    /**
     * @param string $collectionName
     * @param string $docId
     * @throws \Throwable if deleting did not succeed
     */
    public function deleteDoc(string $collectionName, string $docId): void
    {
        $this->documentStore->deleteDoc($collectionName, $docId);
    }

    /**
     * @param string $collectionName
     * @param Filter $filter
     * @throws \Throwable in case of connection error or other issues
     */
    public function deleteMany(string $collectionName, Filter $filter): void
    {
        $this->documentStore->deleteMany($collectionName, $filter);
    }

    /**
     * @param string $collectionName
     * @param string $docId
     * @return array|null
     */
    public function getDoc(string $collectionName, string $docId): ?array
    {
        return $this->documentStore->getDoc($collectionName, $docId);
    }

    /**
     * @param string $collectionName
     * @param Filter $filter
     * @param int|null $skip
     * @param int|null $limit
     * @param OrderBy|null $orderBy
     * @return \Traversable list of docs
     */
    public function filterDocs(string $collectionName, Filter $filter, int $skip = null, int $limit = null, OrderBy $orderBy = null): \Traversable
    {
        return $this->documentStore->filterDocs($collectionName, $filter, $skip, $limit, $orderBy);
    }
}
