<?php namespace Limoncello\JsonApi\Schema;

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

use Limoncello\JsonApi\Contracts\Adapters\PaginationStrategyInterface;
use Limoncello\JsonApi\Contracts\Models\ModelSchemesInterface;
use Limoncello\JsonApi\Contracts\Models\PaginatedDataInterface;
use Limoncello\JsonApi\Contracts\Schema\JsonSchemesInterface;
use Limoncello\JsonApi\Contracts\Schema\SchemaInterface;
use Limoncello\JsonApi\Models\RelationshipTypes;
use Neomerx\JsonApi\Contracts\Document\DocumentInterface;
use Neomerx\JsonApi\Contracts\Document\LinkInterface;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;
use Neomerx\JsonApi\Schema\SchemaProvider;

/**
 * @package Limoncello\JsonApi
 */
abstract class Schema extends SchemaProvider implements SchemaInterface
{
    /** @var JsonSchemesInterface  */
    private $jsonSchemes;

    /**
     * @var ModelSchemesInterface
     */
    private $modelSchemes;

    /**
     * @param FactoryInterface      $factory
     * @param JsonSchemesInterface  $jsonSchemes
     * @param ModelSchemesInterface $modelSchemes
     */
    public function __construct(
        FactoryInterface $factory,
        JsonSchemesInterface $jsonSchemes,
        ModelSchemesInterface $modelSchemes
    ) {
        $this->resourceType = static::TYPE;

        parent::__construct($factory);

        $this->jsonSchemes  = $jsonSchemes;
        $this->modelSchemes = $modelSchemes;
    }

    /**
     * @inheritdoc
     */
    public static function getAttributeMapping($jsonName)
    {
        return static::getMappings()[static::SCHEMA_ATTRIBUTES][$jsonName];
    }

    /**
     * @inheritdoc
     */
    public static function getRelationshipMapping($jsonName)
    {
        return static::getMappings()[static::SCHEMA_RELATIONSHIPS][$jsonName];
    }

    /**
     * @inheritdoc
     */
    public function getAttributes($model)
    {
        $attributes = [];
        $mappings   = static::getMappings();
        if (array_key_exists(static::SCHEMA_ATTRIBUTES, $mappings) === true) {
            $attrMappings = $mappings[static::SCHEMA_ATTRIBUTES];
            foreach ($attrMappings as $jsonAttrName => $modelAttrName) {
                $attributes[$jsonAttrName] = isset($model->{$modelAttrName}) === true ? $model->{$modelAttrName} : null;
            }
        }

        return $attributes;
    }

    /** @noinspection PhpMissingParentCallCommonInspection
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getRelationships($model, $isPrimary, array $includeRelationships)
    {
        $modelClass    = get_class($model);
        $relationships = [];
        $mappings      = static::getMappings();
        if (array_key_exists(static::SCHEMA_RELATIONSHIPS, $mappings) === true) {
            $relMappings = $mappings[static::SCHEMA_RELATIONSHIPS];
            foreach ($relMappings as $jsonRelName => $modelRelName) {
                $isRelToBeIncluded = array_key_exists($jsonRelName, $includeRelationships) === true;

                $hasRelData = $this->hasRelationship($model, $modelRelName);
                $relType    = $this->getModelSchemes()->getRelationshipType($modelClass, $modelRelName);

                // there is a case for `to-1` relationship when we can return identity resource (type + id)
                if ($relType === RelationshipTypes::BELONGS_TO &&
                    $isRelToBeIncluded === false &&
                    $hasRelData === false
                ) {
                    $identity = $this->getIdentity($model, $modelRelName);
                    $relationships[$jsonRelName] = [static::DATA => $identity];
                    continue;
                }

                // if our storage do not have any data for this relationship or relationship would not
                // be included we return it as link
                $isShowAsLink = false;
                if ($hasRelData === false ||
                    ($isRelToBeIncluded === false && $relType !== RelationshipTypes::BELONGS_TO)
                ) {
                    $isShowAsLink = true;
                }

                if ($isShowAsLink === true) {
                    $relationships[$jsonRelName] = $this->getRelationshipLink($model, $jsonRelName);
                    continue;
                }

                $relData = $this->getJsonSchemes()->getRelationshipStorage()->getRelationship($model, $modelRelName);
                $relUri  = $this->getRelationshipSelfUrl($model, $jsonRelName);
                $relationships[$jsonRelName] = $this->getRelationshipDescription($relData, $relUri);
            }
        }

        return $relationships;
    }

    /**
     * @return JsonSchemesInterface
     */
    protected function getJsonSchemes()
    {
        return $this->jsonSchemes;
    }

    /**
     * @return ModelSchemesInterface
     */
    protected function getModelSchemes()
    {
        return $this->modelSchemes;
    }

    /**
     * @param PaginatedDataInterface $data
     * @param string                 $uri
     *
     * @return array
     */
    protected function getRelationshipDescription(PaginatedDataInterface $data, $uri)
    {
        if ($data->hasMoreItems() === false) {
            return [static::DATA => $data->getData()];
        }

        $buildUrl = function ($offset) use ($data, $uri) {
            $paramsWithPaging = [
                PaginationStrategyInterface::PARAM_PAGING_SKIP => $offset,
                PaginationStrategyInterface::PARAM_PAGING_SIZE => $data->getLimit(),
            ];
            $fullUrl = $uri . '?' . http_build_query($paramsWithPaging);

            return $fullUrl;
        };

        $links = [];

        // It looks like relationship can only hold first data rows so we might need `next` link but never `prev`

        if ($data->hasMoreItems() === true) {
            $offset = $data->getOffset() + $data->getLimit();
            $links[DocumentInterface::KEYWORD_NEXT] = $this->createLink($buildUrl($offset));
        }

        return [
            static::DATA  => $data->getData(),
            static::LINKS => $links,
        ];
    }

    /**
     * @param mixed  $model
     * @param string $jsonRelationship
     *
     * @return array
     */
    protected function getRelationshipLink($model, $jsonRelationship)
    {
        $relSelfLink = $this->getRelationshipSelfLink($model, $jsonRelationship);
        return [
            static::LINKS     => [LinkInterface::SELF => $relSelfLink],
            static::SHOW_DATA => false,
        ];
    }

    /**
     * @param mixed  $model
     * @param string $modelRelName
     *
     * @return mixed
     */
    protected function getIdentity($model, $modelRelName)
    {
        $schema = $this->getModelSchemes();

        $class          = get_class($model);
        $fkName         = $schema->getForeignKey($class, $modelRelName);
        $reversePkValue = $model->{$fkName};
        if ($reversePkValue === null) {
            return null;
        }

        $reverseClass  = $schema->getReverseModelClass($class, $modelRelName);
        $reversePkName = $schema->getPrimaryKey($reverseClass);

        $model = new $reverseClass;
        $model->{$reversePkName} = $reversePkValue;

        return $model;
    }

    /**
     * @param mixed  $model
     * @param string $name
     *
     * @return bool
     */
    private function hasRelationship($model, $name)
    {
        $relationships   = $this->getJsonSchemes()->getRelationshipStorage();
        $hasRelationship = ($relationships !== null && $relationships->hasRelationship($model, $name) === true);

        return $hasRelationship;
    }
}
