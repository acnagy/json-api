<?php namespace Limoncello\JsonApi\Transformer;

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

use Limoncello\JsonApi\Contracts\Schema\SchemaInterface;
use Limoncello\Models\Contracts\SchemaStorageInterface;
use Limoncello\Models\RelationshipTypes;
use Neomerx\JsonApi\Contracts\Document\DocumentInterface;

/**
 * @package Limoncello\JsonApi
 */
abstract class BaseTransformer
{
    /** JSON API Error status code */
    const ERROR_STATUS_CODE = 422;

    /**
     * @var string
     */
    private $modelClass;

    /**
     * @var array
     */
    private $attrMappings;

    /**
     * @var array
     */
    private $relMappings;

    /**
     * @var SchemaStorageInterface
     */
    private $modelSchemes;

    /**
     * @param SchemaStorageInterface $modelSchemes
     */
    public function __construct(SchemaStorageInterface $modelSchemes)
    {
        $this->modelSchemes = $modelSchemes;
    }

    /**
     * @param string $jsonName
     *
     * @return bool
     */
    public function isId($jsonName)
    {
        return $jsonName === DocumentInterface::KEYWORD_ID;
    }

    /**
     * @return string
     */
    public function mapId()
    {
        $result = $this->getModelSchemes()->getPrimaryKey($this->getModelClass());

        return $result;
    }

    /**
     * @param string $jsonName
     *
     * @return bool
     */
    public function canMapAttribute($jsonName)
    {
        $result = array_key_exists($jsonName, $this->getAttributeMappings()) === true;

        return $result;
    }

    /**
     * @param string $jsonName
     *
     * @return string
     */
    public function mapAttribute($jsonName)
    {
        $modelName = $this->getAttributeMappings()[$jsonName];

        return $modelName;
    }

    /**
     * @param string $jsonName
     *
     * @return bool
     */
    public function canMapToOneRelationship($jsonName)
    {
        if ($this->canMapRelationship($jsonName) === true) {
            $relType = $this->getModelSchemes()
                ->getRelationshipType($this->getModelClass(), $this->mapRelationship($jsonName));

            return $relType === RelationshipTypes::BELONGS_TO;
        }

        return false;
    }

    /**
     * @param string $jsonName
     *
     * @return string
     */
    public function mapToOneRelationship($jsonName)
    {
        $modelName = $this->getRelationshipMappings()[$jsonName];
        $result    = $this->getModelSchemes()->getForeignKey($this->getModelClass(), $modelName);

        return $result;
    }

    /**
     * @param string $jsonName
     *
     * @return bool
     */
    public function canMapRelationship($jsonName)
    {
        $result = array_key_exists($jsonName, $this->getRelationshipMappings()) === true;

        return $result;
    }

    /**
     * @param string $jsonName
     *
     * @return string
     */
    public function mapRelationship($jsonName)
    {
        $result = $this->getRelationshipMappings()[$jsonName];

        return $result;
    }

    /**
     * @param string $schemaClassName
     */
    protected function setSchema($schemaClassName)
    {
        /** @var SchemaInterface $schemaClassName */

        $this->modelClass = $schemaClassName::MODEL;

        $mappings           = $schemaClassName::getMappings();
        $this->attrMappings = array_key_exists(SchemaInterface::SCHEMA_ATTRIBUTES, $mappings) === true ?
            $mappings[SchemaInterface::SCHEMA_ATTRIBUTES] : [];
        $this->relMappings  = array_key_exists(SchemaInterface::SCHEMA_RELATIONSHIPS, $mappings) === true ?
            $mappings[SchemaInterface::SCHEMA_RELATIONSHIPS] : [];
    }

    /**
     * @return array
     */
    protected function getAttributeMappings()
    {
        return $this->attrMappings;
    }

    /**
     * @return array
     */
    protected function getRelationshipMappings()
    {
        return $this->relMappings;
    }

    /**
     * @return string
     */
    protected function getModelClass()
    {
        return $this->modelClass;
    }

    /**
     * @return SchemaStorageInterface
     */
    protected function getModelSchemes()
    {
        return $this->modelSchemes;
    }
}
