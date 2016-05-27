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

use Limoncello\JsonApi\Contracts\Document\ResourceIdentifierInterface;
use Limoncello\JsonApi\Contracts\Document\TransformerInterface;
use Limoncello\JsonApi\Contracts\I18n\TranslatorInterface as T;
use Limoncello\JsonApi\Contracts\Schema\SchemaInterface;
use Limoncello\Models\Contracts\SchemaStorageInterface;
use Limoncello\Models\RelationshipTypes;
use Neomerx\JsonApi\Contracts\Schema\ContainerInterface;
use Neomerx\JsonApi\Exceptions\ErrorCollection;

/**
 * @package Limoncello\JsonApi
 */
abstract class BaseDocumentTransformer extends BaseTransformer implements TransformerInterface
{
    /** JSON API Schema class */
    const SCHEMA_CLASS = null;

    /** @var T */
    private $translator;

    /**
     * @var string
     */
    private $schemaType;

    /**
     * @var array
     */
    private $mappings;

    /**
     * @var ContainerInterface
     */
    private $jsonSchemes;

    /**
     * @param ContainerInterface     $jsonSchemes
     * @param SchemaStorageInterface $modelSchemes
     * @param T                      $translator
     */
    public function __construct(ContainerInterface $jsonSchemes, SchemaStorageInterface $modelSchemes, T $translator)
    {
        parent::__construct($modelSchemes);

        $this->jsonSchemes  = $jsonSchemes;
        $this->translator   = $translator;

        $schemaClassName = static::SCHEMA_CLASS;

        /** @var SchemaInterface $schemaClassName */
        $this->schemaType = $schemaClassName::TYPE;
        $this->mappings   = $schemaClassName::getMappings();
    }

    /**
     * @inheritdoc
     */
    public function isValidType($type)
    {
        return $this->getSchemaType() === $type;
    }

    /**
     * @inheritdoc
     */
    public function transformAttributes(ErrorCollection $errors, array $jsonAttributes)
    {
        $this->setSchema(static::SCHEMA_CLASS);

        $transformed = [];
        foreach ($jsonAttributes as $jsonAttr => $value) {
            if ($this->canMapAttribute($jsonAttr) === true) {
                $modelField = $this->mapAttribute($jsonAttr);
                $transformed[$modelField] = $value;
                continue;
            }
            $errMsg = $this->getTranslator()->get(T::MSG_ERR_INVALID_ELEMENT);
            $errors->addDataAttributeError($jsonAttr, $errMsg);
        }

        return $transformed;
    }

    /**
     * @inheritdoc
     */
    public function transformToOneRelationship(
        ErrorCollection $errors,
        $jsonName,
        ResourceIdentifierInterface $identifier = null
    ) {
        $modelName = $this->mapRelationshipAndCheckItsType($errors, $jsonName, RelationshipTypes::BELONGS_TO);
        if ($modelName === null) {
            return null;
        }

        $index = null;
        if ($identifier !== null) {
            // check received relationship type is valid
            if ($this->getExpectedResourceType($modelName) !== $identifier->getType()) {
                $errors->addRelationshipTypeError($jsonName, $this->getTranslator()->get(T::MSG_ERR_INVALID_ELEMENT));
                return null;
            }
            $index = $identifier->getId();
        }

        return [$modelName, $index];
    }

    /**
     * @inheritdoc
     */
    public function transformToManyRelationship(ErrorCollection $errors, $jsonName, array $identifiers)
    {
        $modelName = $this->mapRelationshipAndCheckItsType($errors, $jsonName, RelationshipTypes::BELONGS_TO_MANY);
        if ($modelName === null) {
            return null;
        }

        $indexes      = [];
        $expectedType = $this->getExpectedResourceType($modelName);
        foreach ($identifiers as $identifier) {
            /** @var ResourceIdentifierInterface $identifier */
            // check received relationship type is valid
            if ($expectedType !== $identifier->getType()) {
                $errors->addRelationshipTypeError($jsonName, $this->getTranslator()->get(T::MSG_ERR_INVALID_ELEMENT));
                return null;
            }
            $indexes[] = $identifier->getId();
        }

        return [$modelName, $indexes];
    }

    /**
     * @return T
     */
    protected function getTranslator()
    {
        return $this->translator;
    }

    /**
     * @return array
     */
    protected function getMappings()
    {
        return $this->mappings;
    }

    /**
     * @return ContainerInterface
     */
    protected function getJsonSchemes()
    {
        return $this->jsonSchemes;
    }

    /**
     * @return string
     */
    protected function getSchemaType()
    {
        return $this->schemaType;
    }

    /**
     * @param ErrorCollection $errors
     * @param string          $jsonName
     * @param int             $expectedRelType
     *
     * @return null|string
     */
    private function mapRelationshipAndCheckItsType(ErrorCollection $errors, $jsonName, $expectedRelType)
    {
        if ($this->canMapRelationship($jsonName) === false) {
            $errors->addRelationshipError($jsonName, $this->getTranslator()->get(T::MSG_ERR_INVALID_ELEMENT));
            return null;
        }

        $modelName = $this->mapRelationship($jsonName);

        $relType = $this->getModelSchemes()->getRelationshipType($this->getModelClass(), $modelName);
        if ($relType !== $expectedRelType) {
            $errors->addRelationshipError($jsonName, $this->getTranslator()->get(T::MSG_ERR_INVALID_ELEMENT));
            return null;
        }

        return $modelName;
    }

    /**
     * @param string $modelRelName
     *
     * @return string
     */
    private function getExpectedResourceType($modelRelName)
    {
        list($reverseClass) = $this->getModelSchemes()->getReverseRelationship($this->getModelClass(), $modelRelName);
        $expectedType = $this->getJsonSchemes()->getSchemaByType($reverseClass)->getResourceType();

        return $expectedType;
    }
}
