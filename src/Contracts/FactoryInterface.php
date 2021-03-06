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
use Limoncello\JsonApi\Contracts\Models\ModelSchemesInterface;
use Limoncello\JsonApi\Contracts\Models\ModelStorageInterface;
use Limoncello\JsonApi\Contracts\Models\PaginatedDataInterface;
use Limoncello\JsonApi\Contracts\Models\RelationshipStorageInterface;
use Limoncello\JsonApi\Contracts\Models\TagStorageInterface;
use Limoncello\JsonApi\Contracts\Schema\JsonSchemesInterface;
use Neomerx\JsonApi\Encoder\EncoderOptions;
use Neomerx\JsonApi\Exceptions\ErrorCollection;

/**
 * @package Limoncello\JsonApi
 */
interface FactoryInterface
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
     * @param ModelSchemesInterface $modelSchemes
     *
     * @return ModelStorageInterface
     */
    public function createModelStorage(ModelSchemesInterface $modelSchemes);

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
     * @param ModelSchemesInterface     $modelSchemes
     * @param FilterOperationsInterface $filterOperations
     * @param TranslatorInterface       $translator
     *
     * @return RepositoryInterface
     */
    public function createRepository(
        Connection $connection,
        ModelSchemesInterface $modelSchemes,
        FilterOperationsInterface $filterOperations,
        TranslatorInterface $translator
    );

    /**
     * @param array                 $schemes
     * @param ModelSchemesInterface $modelSchemes
     *
     * @return JsonSchemesInterface
     */
    public function createJsonSchemes(array $schemes, ModelSchemesInterface $modelSchemes);

    /**
     * @param JsonSchemesInterface $schemes
     * @param EncoderOptions       $encoderOptions
     *
     * @return EncoderInterface
     */
    public function createEncoder(JsonSchemesInterface $schemes, EncoderOptions $encoderOptions = null);
    /**
     * @param mixed $data
     *
     * @return PaginatedDataInterface
     */
    public function createPaginatedData($data);
}
