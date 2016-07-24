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

use Doctrine\DBAL\Connection;
use Limoncello\JsonApi\Contracts\Adapters\FilterOperationsInterface;
use Limoncello\JsonApi\Contracts\Adapters\RepositoryInterface;
use Limoncello\JsonApi\Contracts\Api\ModelsDataInterface;
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
     * @return TranslatorInterface
     */
    public function createTranslator();

    /**
     * @param Connection                $connection
     * @param SchemaStorageInterface    $schemaStorage
     * @param FilterOperationsInterface $filterOperations
     * @param TranslatorInterface       $translator
     *
     * @return RepositoryInterface
     */
    public function createRepository(
        Connection $connection,
        SchemaStorageInterface $schemaStorage,
        FilterOperationsInterface $filterOperations,
        TranslatorInterface $translator
    );

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
