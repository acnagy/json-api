<?php namespace Limoncello\JsonApi\Document;

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

use Limoncello\JsonApi\Contracts\Document\ParserInterface;
use Limoncello\JsonApi\Contracts\Document\ResourceIdentifierInterface;
use Limoncello\JsonApi\Contracts\Document\ResourceInterface;
use Limoncello\JsonApi\Contracts\Document\TransformerInterface;
use Limoncello\JsonApi\Contracts\FactoryInterface;
use Limoncello\JsonApi\Contracts\I18n\TranslatorInterface as T;
use Neomerx\JsonApi\Contracts\Document\DocumentInterface;
use Neomerx\JsonApi\Exceptions\ErrorCollection;

/**
 * @package Limoncello\JsonApi
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Parser implements ParserInterface
{
    /**
     * @var ErrorCollection
     */
    private $errors;

    /**
     * @var FactoryInterface
     */
    private $factory;

    /**
     * @var TransformerInterface
     */
    private $transformer;

    /**
     * @var T
     */
    private $translator;

    /**
     * @param FactoryInterface     $factory
     * @param TransformerInterface $transformer
     * @param T                    $translator
     */
    public function __construct(FactoryInterface $factory, TransformerInterface $transformer, T $translator)
    {
        $this->factory     = $factory;
        $this->transformer = $transformer;
        $this->translator  = $translator;

        $this->reset();
    }

    /**
     * @return ErrorCollection
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param string $json
     *
     * @return ResourceInterface|null
     */
    public function parse($json)
    {
        $this->reset();

        $result      = null;
        $jsonAsArray = json_decode($json, true);
        if ($jsonAsArray !== null) {
            return $this->parseDocument($jsonAsArray);
        }

        $this->getErrors()->addDataError($this->translator->get(T::MSG_ERR_INVALID_ELEMENT));

        return $result;
    }

    /**
     * @return TransformerInterface
     */
    protected function getTransformer()
    {
        return $this->transformer;
    }

    /**
     * Reset internal objects.
     */
    private function reset()
    {
        if ($this->getErrors() === null || $this->getErrors()->count() > 0) {
            $this->errors = $this->factory->createErrorCollection();
        }
    }

    /**
     * @param array $data
     *
     * @return ResourceInterface|null
     */
    private function parseDocument(array $data)
    {
        $result = null;

        $this->getTransformer()->transformationsToStart();
        try {
            $dataSegment = $this->getArrayValue($data, DocumentInterface::KEYWORD_DATA, null);

            if (empty($dataSegment) === true || is_array($dataSegment) === false) {
                $this->getErrors()->addDataError($this->translator->get(T::MSG_ERR_INVALID_ELEMENT));

                return $result;
            }

            $hasError = false;
            if ($this->hasType($dataSegment) === false) {
                $this->getErrors()->addDataTypeError($this->translator->get(T::MSG_ERR_INVALID_ELEMENT));
                $hasError = true;
            }
            if ($this->hasId($dataSegment) === false) {
                $this->getErrors()->addDataIdError($this->translator->get(T::MSG_ERR_INVALID_ELEMENT));
                $hasError = true;
            }

            if ($hasError === false) {
                $result = $this->parseSinglePrimaryData($dataSegment);
            }
        } finally {
            $this->getTransformer()->transformationsFinished($this->getErrors());
        }

        return $result;
    }

    /**
     * @param array      $array
     * @param string|int $key
     * @param mixed      $default
     *
     * @return mixed
     */
    private function getArrayValue(array $array, $key, $default)
    {
        return array_key_exists($key, $array) === true ? $array[$key] : $default;
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    private function hasType(array $data)
    {
        $result = array_key_exists(DocumentInterface::KEYWORD_TYPE, $data);

        return $result;
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    private function hasId(array $data)
    {
        $result = array_key_exists(DocumentInterface::KEYWORD_ID, $data);

        return $result;
    }

    /**
     * @param array $data
     *
     * @return ResourceInterface|null
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function parseSinglePrimaryData(array $data)
    {
        $type = $data[DocumentInterface::KEYWORD_TYPE];
        $idx  = $data[DocumentInterface::KEYWORD_ID];

        // type must be non-empty string
        if (empty($type) === true || is_string($type) === false) {
            $this->getErrors()->addDataTypeError($this->translator->get(T::MSG_ERR_CANNOT_BE_EMPTY));
        } elseif ($this->getTransformer()->isValidType($type) === false) {
            $this->getErrors()->addDataTypeError($this->translator->get(T::MSG_ERR_INVALID_ELEMENT));
        }

        // idx could be either null or string or int
        if (($idx !== null && is_string($idx) === false && is_int($idx) === false) ||
            $this->getTransformer()->isValidId($idx) === false
        ) {
            $this->getErrors()->addDataIdError($this->translator->get(T::MSG_ERR_INVALID_ELEMENT));
        }

        $result = null;

        $relationships = $this->getArrayValue($data, DocumentInterface::KEYWORD_RELATIONSHIPS, []);
        if (is_array($relationships) === false) {
            $this->getErrors()->addRelationshipsError($this->translator->get(T::MSG_ERR_INVALID_ELEMENT));

            return $result;
        }

        $attributes = $this->getArrayValue($data, DocumentInterface::KEYWORD_ATTRIBUTES, []);
        $attributes = $this->getTransformer()->transformAttributes($this->getErrors(), $attributes);
        list($toOne, $toMany) = $this->parseRelationships($relationships);

        if ($this->getErrors()->count() <= 0) {
            $result = $this->factory->createResource($type, $idx, $attributes, $toOne, $toMany);
        }

        return $result;
    }

    /**
     * @param array $data
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function parseRelationships(array $data)
    {
        $toOne  = [];
        $toMany = [];

        foreach ($data as $jsonName => $relationshipData) {
            if (is_array($relationshipData) === false || $this->hasData($relationshipData) === false) {
                $this->getErrors()->addRelationshipError($jsonName, $this->translator->get(T::MSG_ERR_INVALID_ELEMENT));
                continue;
            }

            $dataSegment = $relationshipData[DocumentInterface::KEYWORD_DATA];

            if ($dataSegment === null) {
                $relAndId = $this->createToOneRelationship($jsonName, null);
                if ($relAndId !== null) {
                    list ($modelName)  = $relAndId;
                    $toOne[$modelName] = null;
                }
                continue;
            } elseif (is_array($dataSegment) === false) {
                $this->getErrors()->addRelationshipError($jsonName, $this->translator->get(T::MSG_ERR_INVALID_ELEMENT));
                continue;
            } elseif (empty($dataSegment) === true) {
                $relAndIds = $this->createToManyRelationship($jsonName, []);
                if ($relAndIds !== null) {
                    list($modelName)    = $relAndIds;
                    $toMany[$modelName] = [];
                }
                continue;
            }

            // If we are here `data` is array and it's not empty. What could it be?
            // Does it look like relationship to single item?
            $looksToOne = $this->hasType($dataSegment) === true || $this->hasId($dataSegment) === true;

            if ($looksToOne === true) {
                if (($parsed = $this->parseSingleIdentifierInRelationship($jsonName, $dataSegment)) !== null &&
                    ($relAndId = $this->createToOneRelationship($jsonName, $parsed)) !== null
                ) {
                    list ($modelName, $index) = $relAndId;
                    $toOne[$modelName]        = $index;
                }
            } else {
                if (($parsed = $this->parseArrayOfIdentifiersInRelationship($jsonName, $dataSegment)) !== null &&
                    ($relAndIds = $this->createToManyRelationship($jsonName, $parsed)) !== null) {
                    list ($modelName, $indexes) = $relAndIds;
                    $toMany[$modelName] = $indexes;
                }
            }
        }

        return [$toOne, $toMany];
    }

    /**
     * @param array $array
     *
     * @return bool
     */
    private function hasData(array $array)
    {
        $result = array_key_exists(DocumentInterface::KEYWORD_DATA, $array);

        return $result;
    }

    /**
     * @param string $name
     * @param array  $data
     *
     * @return ResourceIdentifierInterface|null
     */
    private function parseSingleIdentifierInRelationship($name, array $data)
    {
        $type = $this->getArrayValue($data, DocumentInterface::KEYWORD_TYPE, null);
        $idx  = $this->getArrayValue($data, DocumentInterface::KEYWORD_ID, null);

        $gotError = false;

        // type must be non-empty string
        if (empty($type) === true || is_string($type) === false) {
            $this->getErrors()->addRelationshipTypeError($name, $this->translator->get(T::MSG_ERR_CANNOT_BE_EMPTY));
            $gotError = true;
        }

        // idx must be non-empty string or int
        if ((empty($idx) === true && is_string($idx) === false) && is_int($idx) === false) {
            $this->getErrors()->addRelationshipIdError($name, $this->translator->get(T::MSG_ERR_INVALID_ELEMENT));
            $gotError = true;
        }

        $result = $gotError === true ? null : $this->factory->createResourceIdentifier($type, $idx);

        return $result;
    }

    /**
     * @param string $name
     * @param array  $data
     *
     * @return ResourceIdentifierInterface[]|null
     */
    private function parseArrayOfIdentifiersInRelationship($name, array $data)
    {
        $result = [];

        foreach ($data as $typeAndIdPair) {
            if (is_array($typeAndIdPair) === false) {
                $this->getErrors()->addRelationshipError($name, $this->translator->get(T::MSG_ERR_INVALID_ELEMENT));
                return null;
            }

            $parsed = $this->parseSingleIdentifierInRelationship($name, $typeAndIdPair);
            if ($parsed === null) {
                return null;
            }
            $result[] = $parsed;
        }

        return $result;
    }

    /**
     * @param string                           $jsonName
     * @param ResourceIdentifierInterface|null $identifier
     *
     * @return array|null
     */
    private function createToOneRelationship($jsonName, ResourceIdentifierInterface $identifier = null)
    {
        $result = $this->getTransformer()->transformToOneRelationship($this->getErrors(), $jsonName, $identifier);
        if ($this->getErrors()->count() <= 0) {
            return $result;
        }

        return null;
    }

    /**
     * @param string                        $jsonName
     * @param ResourceIdentifierInterface[] $identifiers
     *
     * @return array|null
     */
    private function createToManyRelationship($jsonName, array $identifiers)
    {
        $result = $this->getTransformer()->transformToManyRelationship($this->getErrors(), $jsonName, $identifiers);
        if ($this->getErrors()->count() <= 0) {
            return $result;
        }

        return null;
    }
}
