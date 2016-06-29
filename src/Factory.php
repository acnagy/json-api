<?php namespace Limoncello\JsonApi;

/**
 * Copyright 2015-2016 info@neomerx.com (www.neomerx.com)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

use Doctrine\DBAL\Connection;
use Limoncello\JsonApi\Adapters\Repository;
use Limoncello\JsonApi\Api\Crud;
use Limoncello\JsonApi\Api\ModelsData;
use Limoncello\JsonApi\Contracts\Adapters\FilterOperationsInterface;
use Limoncello\JsonApi\Contracts\Adapters\PaginationStrategyInterface;
use Limoncello\JsonApi\Contracts\Adapters\RepositoryInterface;
use Limoncello\JsonApi\Contracts\Document\TransformerInterface;
use Limoncello\JsonApi\Contracts\FactoryInterface;
use Limoncello\JsonApi\Contracts\I18n\TranslatorInterface as T;
use Limoncello\JsonApi\Contracts\Schema\ContainerInterface;
use Limoncello\JsonApi\Document\Parser;
use Limoncello\JsonApi\Document\Resource;
use Limoncello\JsonApi\Document\ResourceIdentifier;
use Limoncello\JsonApi\Encoder\Encoder;
use Limoncello\JsonApi\I18n\Translator;
use Limoncello\JsonApi\Schema\Container;
use Limoncello\Models\Contracts\FactoryInterface as ModelsFactoryInterface;
use Limoncello\Models\Contracts\PaginatedDataInterface;
use Limoncello\Models\Contracts\RelationshipStorageInterface;
use Limoncello\Models\Contracts\SchemaStorageInterface;
use Limoncello\Models\Factory as ModelsFactory;
use Limoncello\Models\ModelStorage;
use Limoncello\Models\RelationshipStorage;
use Limoncello\Models\TagStorage;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface as JsonApiFactoryInterface;
use Neomerx\JsonApi\Encoder\EncoderOptions;
use Neomerx\JsonApi\Exceptions\ErrorCollection;
use Neomerx\JsonApi\Factories\Factory as JsonApiFactory;

/**
 * @package Limoncello\JsonApi
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Factory implements FactoryInterface
{
    /**
     * @var ModelsFactoryInterface
     */
    private $modelsFactory = null;

    /**
     * @var JsonApiFactoryInterface
     */
    private $jsonApiFactory = null;

    /**
     * @return ModelsFactoryInterface
     */
    public function getModelsFactory()
    {
        if ($this->modelsFactory === null) {
            $this->modelsFactory = new ModelsFactory();
        }

        return $this->modelsFactory;
    }

    /**
     * @return JsonApiFactoryInterface
     */
    public function getJsonApiFactory()
    {
        if ($this->jsonApiFactory === null) {
            $this->jsonApiFactory = new JsonApiFactory();
        }

        return $this->jsonApiFactory;
    }

    /**
     * @param mixed    $data
     * @param bool     $isCollection
     * @param bool     $hasMoreItems
     * @param int|null $offset
     * @param int|null $size
     *
     * @return PaginatedDataInterface
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function createPaginatedData(
        $data,
        $isCollection = false,
        $hasMoreItems = false,
        $offset = null,
        $size = null
    ) {
        return $this->getModelsFactory()->createPaginatedData($data, $isCollection, $hasMoreItems, $offset, $size);
    }

    /**
     * @inheritdoc
     */
    public function createErrorCollection()
    {
        return new ErrorCollection();
    }

    /**
     * @inheritdoc
     */
    public function createRelationshipStorage()
    {
        return new RelationshipStorage($this->getModelsFactory());
    }

    /**
     * @inheritdoc
     */
    public function createModelStorage(SchemaStorageInterface $schemaStorage)
    {
        return new ModelStorage($schemaStorage);
    }

    /**
     * @inheritdoc
     */
    public function createTagStorage()
    {
        return new TagStorage();
    }

    /**
     * @inheritdoc
     */
    public function createModelsData(
        PaginatedDataInterface $paginatedData,
        RelationshipStorageInterface $relationshipStorage = null
    ) {
        return new ModelsData($paginatedData, $relationshipStorage);
    }

    /**
     * @inheritdoc
     */
    public function createResourceIdentifier($type, $index)
    {
        return new ResourceIdentifier($type, $index);
    }

    /**
     * @inheritdoc
     */
    public function createResource($type, $index, array $attributes, array $toOne, array $toMany)
    {
        return new Resource($type, $index, $attributes, $toOne, $toMany);
    }

    /**
     * @inheritdoc
     */
    public function createParser(TransformerInterface $transformer, T $translator)
    {
        return new Parser($this, $transformer, $translator);
    }

    /**
     * @inheritdoc
     */
    public function createTranslator()
    {
        return new Translator();
    }

    /** @noinspection PhpTooManyParametersInspection
     * @inheritdoc
     */
    public function createRepository(
        Connection $connection,
        SchemaStorageInterface $schemaStorage,
        FilterOperationsInterface $filterOperations,
        T $translator
    ) {
        return new Repository($connection, $schemaStorage, $filterOperations, $translator);
    }

    /**
     * @inheritdoc
     */
    public function createCrud(
        $modelClass,
        RepositoryInterface $repository,
        SchemaStorageInterface $modelSchemes,
        PaginationStrategyInterface $paginationStrategy,
        T $translator
    ) {
        return new Crud($this, $modelClass, $repository, $modelSchemes, $paginationStrategy, $translator);
    }

    /**
     * @inheritdoc
     */
    public function createContainer(array $schemes, SchemaStorageInterface $modelSchemes)
    {
        return new Container($this->getJsonApiFactory(), $schemes, $modelSchemes);
    }

    /**
     * @inheritdoc
     */
    public function createEncoder(ContainerInterface $schemes, EncoderOptions $encoderOptions = null)
    {
        return new Encoder($this->getJsonApiFactory(), $schemes, $encoderOptions);
    }
}
