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
use Limoncello\JsonApi\Api\ModelsData;
use Limoncello\JsonApi\Contracts\Adapters\FilterOperationsInterface;
use Limoncello\JsonApi\Contracts\FactoryInterface;
use Limoncello\JsonApi\Contracts\I18n\TranslatorInterface as T;
use Limoncello\JsonApi\Contracts\Models\ModelSchemesInterface;
use Limoncello\JsonApi\Contracts\Models\PaginatedDataInterface;
use Limoncello\JsonApi\Contracts\Models\RelationshipStorageInterface;
use Limoncello\JsonApi\Contracts\Schema\JsonSchemesInterface;
use Limoncello\JsonApi\Encoder\Encoder;
use Limoncello\JsonApi\I18n\Translator;
use Limoncello\JsonApi\Models\ModelStorage;
use Limoncello\JsonApi\Models\PaginatedData;
use Limoncello\JsonApi\Models\RelationshipStorage;
use Limoncello\JsonApi\Models\TagStorage;
use Limoncello\JsonApi\Schema\JsonSchemes;
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
     * @var JsonApiFactoryInterface
     */
    private $jsonApiFactory = null;

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
     * @param mixed $data
     *
     * @return PaginatedDataInterface
     */
    public function createPaginatedData($data)
    {
        return new PaginatedData($data);
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
        return new RelationshipStorage($this);
    }

    /**
     * @inheritdoc
     */
    public function createModelStorage(ModelSchemesInterface $modelSchemes)
    {
        return new ModelStorage($modelSchemes);
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
    public function createTranslator()
    {
        return new Translator();
    }

    /**
     * @inheritdoc
     */
    public function createRepository(
        Connection $connection,
        ModelSchemesInterface $modelSchemes,
        FilterOperationsInterface $filterOperations,
        T $translator
    ) {
        return new Repository($connection, $modelSchemes, $filterOperations, $translator);
    }

    /**
     * @inheritdoc
     */
    public function createJsonSchemes(array $schemes, ModelSchemesInterface $modelSchemes)
    {
        return new JsonSchemes($this->getJsonApiFactory(), $schemes, $modelSchemes);
    }

    /**
     * @inheritdoc
     */
    public function createEncoder(JsonSchemesInterface $schemes, EncoderOptions $encoderOptions = null)
    {
        return new Encoder($this->getJsonApiFactory(), $schemes, $encoderOptions);
    }
}
