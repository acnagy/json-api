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

use Limoncello\JsonApi\Contracts\I18n\TranslatorInterface as T;
use Limoncello\JsonApi\Contracts\Schema\ContainerInterface;
use Limoncello\Models\Contracts\SchemaStorageInterface;
use Neomerx\JsonApi\Contracts\Document\DocumentInterface;
use Neomerx\JsonApi\Contracts\Encoder\Parameters\EncodingParametersInterface;
use Neomerx\JsonApi\Encoder\Parameters\SortParameter;
use Neomerx\JsonApi\Exceptions\ErrorCollection;

/**
 * @package Limoncello\JsonApi
 */
class BaseQueryTransformer extends BaseTransformer
{
    /**
     * @var bool
     */
    private $isRootSchemaSet = false;

    /**
     * @var ContainerInterface
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
     * @param SchemaStorageInterface $modelSchemes
     * @param ContainerInterface     $jsonSchemes
     * @param T                      $translator
     * @param string                 $schemaClass
     */
    public function __construct(
        SchemaStorageInterface $modelSchemes,
        ContainerInterface $jsonSchemes,
        T $translator,
        $schemaClass
    ) {
        parent::__construct($modelSchemes);

        $this->jsonSchemes = $jsonSchemes;
        $this->schemaClass = $schemaClass;
        $this->translator  = $translator;
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
     * @param string $jsonName
     *
     * @return null|string
     */
    public function mapField($jsonName)
    {
        $this->resetSchema();

        $modelName = null;
        if ($this->isId($jsonName) === true) {
            $modelName = $this->mapId();
        } elseif ($this->canMapAttribute($jsonName) === true) {
            $modelName = $this->mapAttribute($jsonName);
        } elseif ($this->canMapToOneRelationship($jsonName) === true) {
            $modelName = $this->mapToOneRelationship($jsonName);
        }

        return $modelName;
    }

    /**
     * @param string[] $pathItems
     *
     * @return string[]|null
     */
    public function mapRelationshipsPath(array $pathItems)
    {
        $this->resetSchema();
        $currentModelClass = $this->getModelClass();

        $modelRelationships = [];
        foreach ($pathItems as $jsonName) {
            if ($this->canMapRelationship($jsonName) === true) {
                $modelName            = $this->mapRelationship($jsonName);
                $modelRelationships[] = $modelName;

                list($currentModelClass) = $this->getModelSchemes()
                    ->getReverseRelationship($currentModelClass, $modelName);
                $nextSchema = $this->getJsonSchemes()->getSchemaByType($currentModelClass);
                $this->setSchema(get_class($nextSchema));

                continue;
            }

            return null;
        }

        return $modelRelationships;
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
    protected function setSchema($schemaClassName)
    {
        parent::setSchema($schemaClassName);
        $this->isRootSchemaSet = false;
    }

    /**
     * @param ErrorCollection $errors
     * @param array|null      $parameters
     *
     * @return null|array
     */
    protected function mapFilterParameters(ErrorCollection $errors, array $parameters = null)
    {
        $filters = null;

        if ($parameters !== null) {
            foreach ($parameters as $jsonName => $desc) {
                if (($modelName = $this->mapField($jsonName)) !== null) {
                    $filters[$modelName] = $desc;
                    continue;
                }
                $errors->addQueryParameterError($jsonName, $this->messageInvalidItem());
            }
        }

        return $filters;
    }

    /**
     * @param ErrorCollection $errors
     * @param array|null      $parameters
     *
     * @return null|array
     */
    protected function mapSortParameters(ErrorCollection $errors, array $parameters = null)
    {
        $sorts = null;

        if ($parameters !== null) {
            foreach ($parameters as $parameter) {
                /** @var SortParameter $parameter */
                if (($modelName = $this->mapField($parameter->getField())) !== null) {
                    $sorts[$modelName] = $parameter->isAscending();
                    continue;
                }
                $errors->addQueryParameterError($parameter->getField(), $this->messageInvalidItem());
            }
        }

        return $sorts;
    }

    /**
     * @param ErrorCollection $errors
     * @param array|null      $parameters
     *
     * @return null|array
     */
    protected function mapIncludeParameters(ErrorCollection $errors, array $parameters = null)
    {
        $includes = null;

        if ($parameters !== null) {
            foreach ($parameters as $includePath) {
                $pathItems   = explode(DocumentInterface::PATH_SEPARATOR, $includePath);
                $mappedItems = $this->mapRelationshipsPath($pathItems);
                if ($mappedItems !== null) {
                    $mappedPath = implode(DocumentInterface::PATH_SEPARATOR, $mappedItems);
                    $includes[] = $mappedPath;
                    continue;
                }

                $errors->addQueryParameterError($includePath, $this->messageInvalidItem());
            }
        }

        return $includes;
    }

    /**
     * @return void
     */
    private function resetSchema()
    {
        if ($this->isRootSchemaSet === false) {
            parent::setSchema($this->getSchemaClass());
            $this->isRootSchemaSet = true;
        }
    }

    /**
     * @return string
     */
    private function messageInvalidItem()
    {
        return $this->getTranslator()->get(T::MSG_ERR_INVALID_ELEMENT);
    }
}
