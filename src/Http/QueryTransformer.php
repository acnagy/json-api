<?php namespace Limoncello\JsonApi\Http;

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

use Limoncello\JsonApi\Contracts\Http\Query\FilterParameterInterface;
use Limoncello\JsonApi\Contracts\Http\Query\IncludeParameterInterface;
use Limoncello\JsonApi\Contracts\Http\Query\SortParameterInterface;
use Limoncello\JsonApi\Contracts\I18n\TranslatorInterface as T;
use Limoncello\JsonApi\Contracts\Schema\JsonSchemesInterface;
use Limoncello\JsonApi\Contracts\Schema\SchemaInterface;
use Limoncello\JsonApi\Http\Query\FilterParameter;
use Limoncello\JsonApi\Http\Query\FilterParameterCollection;
use Limoncello\JsonApi\Http\Query\IncludeParameter;
use Limoncello\JsonApi\Http\Query\SortParameter;
use Limoncello\Models\Contracts\ModelSchemesInterface;
use Limoncello\Models\RelationshipTypes;
use Neomerx\JsonApi\Contracts\Document\DocumentInterface;
use Neomerx\JsonApi\Contracts\Encoder\Parameters\EncodingParametersInterface;
use Neomerx\JsonApi\Contracts\Encoder\Parameters\SortParameterInterface as JsonLibrarySortParameterInterface;
use Neomerx\JsonApi\Exceptions\ErrorCollection;

/**
 * @package Limoncello\JsonApi
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class QueryTransformer
{
    /**
     * @var string
     */
    private $currentModelClass;

    /**
     * @var string
     */
    private $currentSchemaClass;

    /**
     * @var array
     */
    private $attrMappings;

    /**
     * @var array
     */
    private $relMappings;

    /**
     * @var ModelSchemesInterface
     */
    private $modelSchemes;

    /**
     * @var bool
     */
    private $isRootSchemaSet = false;

    /**
     * @var JsonSchemesInterface
     */
    private $jsonSchemes;

    /**
     * @var T
     */
    private $translator;

    /**
     * @var string
     */
    private $schemaClass;

    /**
     * @param ModelSchemesInterface $modelSchemes
     * @param JsonSchemesInterface  $jsonSchemes
     * @param T                     $translator
     * @param string                $schemaClass
     */
    public function __construct(
        ModelSchemesInterface $modelSchemes,
        JsonSchemesInterface $jsonSchemes,
        T $translator,
        $schemaClass
    ) {
        $this->modelSchemes = $modelSchemes;
        $this->jsonSchemes  = $jsonSchemes;
        $this->schemaClass  = $schemaClass;
        $this->translator   = $translator;
    }

    /**
     * @param ErrorCollection             $errors
     * @param EncodingParametersInterface $parameters
     *
     * @return array
     */
    public function mapParameters(ErrorCollection $errors, EncodingParametersInterface $parameters)
    {
        $filters  = $this->mapFilterParameters($errors, $parameters->getFilteringParameters());
        $sorts    = $this->mapSortParameters($errors, $parameters->getSortParameters());
        $includes = $this->mapIncludeParameters($errors, $parameters->getIncludePaths());
        $paging   = $parameters->getPaginationParameters();

        return [$filters, $sorts, $includes, $paging];
    }

    /**
     * @param ErrorCollection $errors
     * @param array|null      $parameters
     *
     * @return null|FilterParameterCollection[]
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    protected function mapFilterParameters(ErrorCollection $errors, array $parameters = null)
    {
        if ($parameters === null) {
            return null;
        }

        $filters = $this->createFilterParameterCollection();

        // check of top level element is `AND` or `OR`
        reset($parameters);
        $firstKey = strtolower(key($parameters));
        if ($firstKey === 'or' || $firstKey === 'and') {
            if (count($parameters) > 1) {
                next($parameters);
                $field = key($parameters);
                $errMsg = $this->getTranslator()->get(T::MSG_ERR_INVALID_PARAMETER);
                $errors->addQueryParameterError($field, $errMsg);

                return null;
            } else {
                $parameters = $parameters[$firstKey];
                $firstKey === 'and' ? $filters->withAnd() : $filters->withOr();
            }
        }

        foreach ($parameters as $jsonName => $desc) {
            if (($mapped = $this->mapFilterField($jsonName, $desc)) !== null) {
                $filters->add($mapped);
                continue;
            }

            $this->addQueryParamError($errors, $jsonName);
        }

        return $filters;
    }

    /**
     * @param ErrorCollection $errors
     * @param array|null      $parameters
     *
     * @return null|SortParameterInterface[]
     */
    protected function mapSortParameters(ErrorCollection $errors, array $parameters = null)
    {
        $sorts = null;

        if ($parameters !== null) {
            foreach ($parameters as $parameter) {
                /** @var JsonLibrarySortParameterInterface $parameter */
                if (($mapped = $this->mapSortField($parameter)) !== null) {
                    $sorts[] = $mapped;
                    continue;
                }

                $this->addQueryParamError($errors, $parameter->getField());
            }
        }

        return $sorts;
    }

    /**
     * @param ErrorCollection $errors
     * @param array|null      $parameters
     *
     * @return null|IncludeParameterInterface[]
     */
    protected function mapIncludeParameters(ErrorCollection $errors, array $parameters = null)
    {
        $includes = null;

        if ($parameters !== null) {
            foreach ($parameters as $includePath) {
                if (($mapped = $this->mapRelationshipsPath($includePath)) !== null) {
                    $includes[] = $mapped;
                    continue;
                }

                $this->addQueryParamError($errors, $includePath);
            }
        }

        return $includes;
    }

    /**
     * @param string       $jsonName
     * @param string|array $value
     *
     * @return FilterParameterInterface|null
     */
    protected function mapFilterField($jsonName, $value)
    {
        $this->resetSchema();

        $mappedParam = null;
        if ($this->isId($jsonName) === true) {
            $column      = $this->getModelSchemes()->getPrimaryKey($this->getCurrentModelClass());
            $mappedParam = $this->createFilterAttributeParameter(DocumentInterface::KEYWORD_ID, $column, $value);
        } elseif ($this->canMapAttribute($jsonName) === true) {
            $column      = $this->getAttributeMappings()[$jsonName];
            $mappedParam = $this->createFilterAttributeParameter($jsonName, $column, $value);
        } elseif ($this->canMapRelationship($jsonName) === true) {
            $modelName   = $this->getRelationshipMappings()[$jsonName];
            $type        =  $this->getModelSchemes()->getRelationshipType($this->getCurrentModelClass(), $modelName);
            $mappedParam = $this->createFilterRelationshipParameter($jsonName, $modelName, $value, $type);
        }

        return $mappedParam;
    }

    /**
     * @param JsonLibrarySortParameterInterface $sortParameter
     *
     * @return SortParameterInterface|null
     */
    protected function mapSortField(JsonLibrarySortParameterInterface $sortParameter)
    {
        $this->resetSchema();

        $mappedParam = null;
        $jsonName    = $sortParameter->getField();
        if ($this->isId($jsonName) === true) {
            $column      = $this->getModelSchemes()->getPrimaryKey($this->getCurrentModelClass());
            $mappedParam = $this->createSortAttributeParameter($sortParameter, $column);
        } elseif ($this->canMapAttribute($jsonName) === true) {
            $column      = $this->getAttributeMappings()[$jsonName];
            $mappedParam = $this->createSortAttributeParameter($sortParameter, $column);
        } elseif ($this->canMapRelationship($jsonName) === true) {
            $modelName = $this->getRelationshipMappings()[$jsonName];
            $type      = $this->getModelSchemes()->getRelationshipType($this->getCurrentModelClass(), $modelName);
            if ($type === RelationshipTypes::BELONGS_TO) {
                $mappedParam = $this->createSortRelationshipParameter($sortParameter, $modelName, $type);
            }
        }

        return $mappedParam;
    }

    /**
     * @param string $path
     *
     * @return IncludeParameterInterface|null
     */
    protected function mapRelationshipsPath($path)
    {
        $this->resetSchema();

        $pathItems = explode(DocumentInterface::PATH_SEPARATOR, $path);

        $modelRelationships = [];
        foreach ($pathItems as $jsonName) {
            if ($this->canMapRelationship($jsonName) === true) {
                $modelRelationships[] = $this->getRelationshipMappings()[$jsonName];
                $nextSchema = $this->getJsonSchemes()->getRelationshipSchema($this->getCurrentSchemaClass(), $jsonName);
                $this->setCurrentSchema(get_class($nextSchema));

                continue;
            }

            return null;
        }

        $result = $this->createIncludeParameter($path, $modelRelationships);

        return $result;
    }

    /**
     * @return FilterParameterCollection
     */
    protected function createFilterParameterCollection()
    {
        return new FilterParameterCollection();
    }

    /**
     * @param string       $originalName
     * @param string       $name
     * @param string|array $value
     *
     * @return FilterParameterInterface
     */
    protected function createFilterAttributeParameter($originalName, $name, $value)
    {
        return new FilterParameter($originalName, $name, $value, false, null);
    }

    /**
     * @param string       $originalName
     * @param string       $name
     * @param string|array $value
     * @param int          $relationshipType
     *
     * @return FilterParameterInterface
     */
    protected function createFilterRelationshipParameter($originalName, $name, $value, $relationshipType)
    {
        return new FilterParameter($originalName, $name, $value, true, $relationshipType);
    }

    /**
     * @param JsonLibrarySortParameterInterface $sortParam
     * @param string                            $name
     *
     * @return SortParameterInterface
     */
    protected function createSortAttributeParameter(JsonLibrarySortParameterInterface $sortParam, $name)
    {
        return new SortParameter($sortParam, $name, false, null);
    }

    /**
     * @param JsonLibrarySortParameterInterface $sortParam
     * @param string                            $name
     * @param int                               $relationshipType
     *
     * @return SortParameterInterface
     */
    protected function createSortRelationshipParameter(
        JsonLibrarySortParameterInterface $sortParam,
        $name,
        $relationshipType
    ) {
        return new SortParameter($sortParam, $name, true, $relationshipType);
    }

    /**
     * @param string $originalPath
     * @param array  $path
     *
     * @return IncludeParameterInterface
     */
    protected function createIncludeParameter($originalPath, array $path)
    {
        return new IncludeParameter($originalPath, $path);
    }

    /**
     * @param string $jsonName
     *
     * @return bool
     */
    protected function isId($jsonName)
    {
        return $jsonName === DocumentInterface::KEYWORD_ID;
    }

    /**
     * @param string $jsonName
     *
     * @return bool
     */
    protected function canMapAttribute($jsonName)
    {
        $result = array_key_exists($jsonName, $this->getAttributeMappings()) === true;

        return $result;
    }

    /**
     * @param string $jsonName
     *
     * @return bool
     */
    protected function canMapRelationship($jsonName)
    {
        $result = array_key_exists($jsonName, $this->getRelationshipMappings()) === true;

        return $result;
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
    protected function getCurrentModelClass()
    {
        return $this->currentModelClass;
    }

    /**
     * @return string
     */
    protected function getCurrentSchemaClass()
    {
        return $this->currentSchemaClass;
    }

    /**
     * @return ModelSchemesInterface
     */
    protected function getModelSchemes()
    {
        return $this->modelSchemes;
    }

    /**
     * @return JsonSchemesInterface
     */
    protected function getJsonSchemes()
    {
        return $this->jsonSchemes;
    }

    /**
     * @return string
     */
    protected function getSchemaClass()
    {
        return $this->schemaClass;
    }

    /**
     * @return T
     */
    protected function getTranslator()
    {
        return $this->translator;
    }

    /**
     * @inheritdoc
     */
    protected function setCurrentSchema($currentSchemaClass)
    {
        $this->currentSchemaClass = $currentSchemaClass;

        /** @var SchemaInterface $currentSchemaClass */

        $this->currentModelClass = $currentSchemaClass::MODEL;

        $mappings           = $currentSchemaClass::getMappings();
        $this->attrMappings = array_key_exists(SchemaInterface::SCHEMA_ATTRIBUTES, $mappings) === true ?
            $mappings[SchemaInterface::SCHEMA_ATTRIBUTES] : [];
        $this->relMappings  = array_key_exists(SchemaInterface::SCHEMA_RELATIONSHIPS, $mappings) === true ?
            $mappings[SchemaInterface::SCHEMA_RELATIONSHIPS] : [];

        $this->isRootSchemaSet = false;
    }

    /**
     * @return void
     */
    private function resetSchema()
    {
        if ($this->isRootSchemaSet === false) {
            $this->setCurrentSchema($this->getSchemaClass());
            $this->isRootSchemaSet = true;
        }
    }

    /**
     * @param ErrorCollection $errors
     * @param string          $name
     *
     * @return void
     */
    private function addQueryParamError(ErrorCollection $errors, $name)
    {
        $title = $this->getTranslator()->get(T::MSG_ERR_INVALID_ELEMENT);
        $errors->addQueryParameterError($name, $title, null, JsonApiResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
