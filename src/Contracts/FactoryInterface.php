<?php namespace Limoncello\JsonApi\Contracts;

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

use Limoncello\JsonApi\Contracts\Adapters\FilterOperationsInterface;
use Limoncello\JsonApi\Contracts\Adapters\PaginationStrategyInterface;
use Limoncello\JsonApi\Contracts\Adapters\RepositoryInterface;
use Limoncello\JsonApi\Contracts\Document\ParserInterface;
use Limoncello\JsonApi\Contracts\Document\ResourceIdentifierInterface;
use Limoncello\JsonApi\Contracts\Document\ResourceInterface;
use Limoncello\JsonApi\Contracts\Document\TransformerInterface;
use Limoncello\JsonApi\Contracts\Encoder\EncoderInterface;
use Limoncello\JsonApi\Contracts\I18n\TranslatorInterface;
use Limoncello\JsonApi\Contracts\Schema\ContainerInterface;
use Limoncello\Models\Contracts\FactoryInterface as ModelsFactoryInterface;
use Limoncello\Models\Contracts\ModelStorageInterface;
use Limoncello\Models\Contracts\PaginatedDataInterface;
use Limoncello\Models\Contracts\RelationshipStorageInterface;
use Limoncello\Models\Contracts\SchemaStorageInterface;
use Limoncello\Models\Contracts\TagStorageInterface;
use Neomerx\JsonApi\Encoder\EncoderOptions;
use Neomerx\JsonApi\Exceptions\ErrorCollection;
use PDO;

/**
 * @package Limoncello\JsonApi
 */
interface FactoryInterface extends ModelsFactoryInterface
{
    /**
     * @return ErrorCollection
     */
    public function createErrorCollection();

    /**
     * @return RelationshipStorageInterface
     */
    public function createRelationshipStorage();

    /**
     * @param SchemaStorageInterface $schemaStorage
     *
     * @return ModelStorageInterface
     */
    public function createModelStorage(SchemaStorageInterface $schemaStorage);

    /**
     * @return TagStorageInterface
     */
    public function createTagStorage();

    /**
     * @param PaginatedDataInterface       $paginatedData
     * @param RelationshipStorageInterface $relationshipStorage
     *
     * @return ModelsDataInterface
     */
    public function createModelsData(
        PaginatedDataInterface $paginatedData,
        RelationshipStorageInterface $relationshipStorage = null
    );

    /**
     * @param string          $type
     * @param int|null|string $index
     *
     * @return ResourceIdentifierInterface
     */
    public function createResourceIdentifier($type, $index);

    /**
     * @param string          $type
     * @param int|null|string $index
     * @param array           $attributes
     * @param array           $toOne
     * @param array           $toMany
     *
     * @return ResourceInterface
     */
    public function createResource($type, $index, array $attributes, array $toOne, array $toMany);

    /**
     * @param TransformerInterface $transformer
     * @param TranslatorInterface  $translator
     *
     * @return ParserInterface
     */
    public function createParser(TransformerInterface $transformer, TranslatorInterface $translator);

    /**
     * @return TranslatorInterface
     */
    public function createTranslator();

    /** @noinspection PhpTooManyParametersInspection
     * @param string                      $class
     * @param PDO                         $pdo
     * @param SchemaStorageInterface      $schemaStorage
     * @param QueryBuilderInterface       $builder
     * @param FilterOperationsInterface   $filterOperations
     * @param PaginationStrategyInterface $relationshipPaging
     * @param TranslatorInterface         $translator
     * @param bool                        $isExecuteOnByOne
     *
     * @return RepositoryInterface
     */
    public function createRepository(
        $class,
        PDO $pdo,
        SchemaStorageInterface $schemaStorage,
        QueryBuilderInterface $builder,
        FilterOperationsInterface $filterOperations,
        PaginationStrategyInterface $relationshipPaging,
        TranslatorInterface $translator,
        $isExecuteOnByOne = true
    );

    /**
     * @param RepositoryInterface $repository
     *
     * @return CrudInterface
     */
    public function createCrud(RepositoryInterface $repository);

    /**
     * @param array                  $schemes
     * @param SchemaStorageInterface $modelSchemes
     *
     * @return ContainerInterface
     */
    public function createContainer(array $schemes, SchemaStorageInterface $modelSchemes);

    /**
     * @param ContainerInterface $schemes
     * @param EncoderOptions     $encoderOptions
     *
     * @return EncoderInterface
     */
    public function createEncoder(ContainerInterface $schemes, EncoderOptions $encoderOptions = null);
}
