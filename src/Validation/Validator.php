<?php namespace Limoncello\JsonApi\Validation;

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

use Generator;
use Limoncello\JsonApi\Contracts\I18n\TranslatorInterface as T;
use Limoncello\JsonApi\Contracts\Models\ModelSchemesInterface;
use Limoncello\JsonApi\Contracts\Schema\JsonSchemesInterface;
use Limoncello\JsonApi\Contracts\Schema\SchemaInterface;
use Limoncello\JsonApi\Contracts\Validation\ValidatorInterface;
use Limoncello\JsonApi\Http\JsonApiResponse;
use Limoncello\Validation\Captures\CaptureAggregator;
use Limoncello\Validation\Contracts\CaptureAggregatorInterface;
use Limoncello\Validation\Contracts\RuleInterface;
use Limoncello\Validation\Contracts\TranslatorInterface as ValidationTranslatorInterface;
use Limoncello\Validation\Errors\Error;
use Limoncello\Validation\Errors\ErrorAggregator;
use Limoncello\Validation\Validator\Captures;
use Limoncello\Validation\Validator\Compares;
use Limoncello\Validation\Validator\ExpressionsX;
use Limoncello\Validation\Validator\Generics;
use Limoncello\Validation\Validator\Types;
use Limoncello\Validation\Validator\ValidatorTrait;
use Limoncello\Validation\Validator\Values;
use Neomerx\JsonApi\Contracts\Document\DocumentInterface;
use Neomerx\JsonApi\Exceptions\ErrorCollection;
use Neomerx\JsonApi\Exceptions\JsonApiException;

/**
 * @package Limoncello\JsonApi
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Validator implements ValidatorInterface
{
    use Captures, Compares, ExpressionsX, Generics, Types, Values, ValidatorTrait;

    /**
     * @var T
     */
    private $jsonApiTranslator;

    /**
     * @var ValidationTranslatorInterface
     */
    private $validationTranslator;

    /**
     * @var JsonSchemesInterface
     */
    private $jsonSchemes;

    /**
     * @var ModelSchemesInterface
     */
    private $modelSchemes;

    /**
     * @var int
     */
    private $errorStatus;

    /**
     * @var RuleInterface
     */
    private $unlistedAttributeRule;

    /**
     * @var RuleInterface
     */
    private $unlistedRelationshipRule;

    /**
     * @param T                             $jsonApiTranslator
     * @param ValidationTranslatorInterface $validationTranslator
     * @param JsonSchemesInterface          $jsonSchemes
     * @param ModelSchemesInterface         $modelSchemes
     * @param int                           $errorStatus
     * @param RuleInterface                 $unlistedAttrRule
     * @param RuleInterface                 $unlistedRelationRule
     */
    public function __construct(
        T $jsonApiTranslator,
        ValidationTranslatorInterface $validationTranslator,
        JsonSchemesInterface $jsonSchemes,
        ModelSchemesInterface $modelSchemes,
        $errorStatus = JsonApiResponse::HTTP_UNPROCESSABLE_ENTITY,
        RuleInterface $unlistedAttrRule = null,
        RuleInterface $unlistedRelationRule = null
    ) {
        $this->jsonApiTranslator        = $jsonApiTranslator;
        $this->validationTranslator     = $validationTranslator;
        $this->jsonSchemes              = $jsonSchemes;
        $this->modelSchemes             = $modelSchemes;
        $this->errorStatus              = $errorStatus;
        $this->unlistedAttributeRule    = $unlistedAttrRule;
        $this->unlistedRelationshipRule = $unlistedRelationRule;
    }

    /**
     * @inheritdoc
     */
    public function assert(
        SchemaInterface $schema,
        array $jsonData,
        RuleInterface $idRule,
        array $attributeRules,
        array $toOneRules = [],
        array $toManyRules = []
    ) {
        $errors = $this->createErrorCollection();
        /** @var CaptureAggregatorInterface $idAggregator */
        /** @var CaptureAggregatorInterface $attrTo1Aggregator */
        /** @var CaptureAggregatorInterface $toManyAggregator */
        list ($idAggregator, $attrTo1Aggregator, $toManyAggregator) =
            $this->check($errors, $schema, $jsonData, $idRule, $attributeRules, $toOneRules, $toManyRules);

        if ($errors->count() > 0) {
            throw new JsonApiException($errors, $this->getErrorStatus());
        }

        return [$idAggregator->getCaptures(), $attrTo1Aggregator->getCaptures(), $toManyAggregator->getCaptures()];
    }

    /**
     * @inheritdoc
     */
    public function check(
        ErrorCollection $errors,
        SchemaInterface $schema,
        array $jsonData,
        RuleInterface $idRule,
        array $attributeRules,
        array $toOneRules = [],
        array $toManyRules = []
    ) {
        $idAggregator      = $this->createCaptureAggregator();
        $attrTo1Aggregator = $this->createCaptureAggregator();
        $toManyAggregator  = $this->createCaptureAggregator();

        $this->validateType($errors, $jsonData, $schema::TYPE);
        $this->validateId($errors, $schema, $jsonData, $idRule, $idAggregator);
        $this->validateAttributes($errors, $schema, $jsonData, $attributeRules, $attrTo1Aggregator);
        $relationshipCaptures = $this
            ->createRelationshipCaptures($schema, $toOneRules, $attrTo1Aggregator, $toManyRules, $toManyAggregator);
        $this->validateCaptures($errors, $jsonData, $relationshipCaptures);

        return [$idAggregator, $attrTo1Aggregator, $toManyAggregator];
    }

    /**
     * @return T
     */
    protected function getJsonApiTranslator()
    {
        return $this->jsonApiTranslator;
    }

    /**
     * @return ValidationTranslatorInterface
     */
    protected function getValidationTranslator()
    {
        return $this->validationTranslator;
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
     * @return int
     */
    protected function getErrorStatus()
    {
        return $this->errorStatus;
    }

    /**
     * @return RuleInterface
     */
    protected function getUnlistedRelationshipRule()
    {
        return $this->unlistedRelationshipRule;
    }

    /**
     * @return RuleInterface
     */
    protected function getUnlistedAttributeRule()
    {
        return $this->unlistedAttributeRule;
    }

    /**
     * @return CaptureAggregatorInterface
     */
    protected function createCaptureAggregator()
    {
        return new CaptureAggregator();
    }

    /**
     * @return ErrorCollection
     */
    protected function createErrorCollection()
    {
        return new ErrorCollection();
    }

    /**
     * @param ErrorCollection $errors
     * @param array           $jsonData
     * @param string          $expectedType
     *
     * @return void
     */
    private function validateType(ErrorCollection $errors, array $jsonData, $expectedType)
    {
        $ignoreOthers = static::success();
        $rule         = static::arrayX([
            DocumentInterface::KEYWORD_DATA => static::arrayX([
                DocumentInterface::KEYWORD_TYPE => static::andX(static::required(), static::equals($expectedType)),
            ], $ignoreOthers),
        ], $ignoreOthers);
        foreach ($this->validateRule($rule, $jsonData) as $error) {
            /** @var Error $error */
            $title  = $this->getJsonApiTranslator()->get(T::MSG_ERR_INVALID_ELEMENT);
            $detail = $this->getValidationTranslator()->translate($error);
            $errors->addDataTypeError($title, $detail, $this->getErrorStatus());
        }
    }

    /**
     * @param ErrorCollection            $errors
     * @param SchemaInterface            $schema
     * @param array                      $jsonData
     * @param RuleInterface              $idRule
     * @param CaptureAggregatorInterface $aggregator
     *
     * @return void
     */
    private function validateId(
        ErrorCollection $errors,
        SchemaInterface $schema,
        array $jsonData,
        RuleInterface $idRule,
        CaptureAggregatorInterface $aggregator
    ) {
        // will use primary column name as a capture name for `id`
        $captureName  = $this->getModelSchemes()->getPrimaryKey($schema::MODEL);
        $idRule       = static::singleCapture($captureName, static::andX(static::required(), $idRule), $aggregator);
        $ignoreOthers = static::success();
        $rule         = static::arrayX([
            DocumentInterface::KEYWORD_DATA => static::arrayX([
                DocumentInterface::KEYWORD_ID => $idRule,
            ], $ignoreOthers)
        ], $ignoreOthers);
        foreach ($this->validateRule($rule, $jsonData) as $error) {
            /** @var Error $error */
            $title  = $this->getJsonApiTranslator()->get(T::MSG_ERR_INVALID_ELEMENT);
            $detail = $this->getValidationTranslator()->translate($error);
            $errors->addDataIdError($title, $detail, $this->getErrorStatus());
        }
    }

    /**
     * @param ErrorCollection            $errors
     * @param SchemaInterface            $schema
     * @param array                      $jsonData
     * @param RuleInterface[]            $attributeRules
     * @param CaptureAggregatorInterface $aggregator
     *
     * @return void
     */
    private function validateAttributes(
        ErrorCollection $errors,
        SchemaInterface $schema,
        array $jsonData,
        array $attributeRules,
        CaptureAggregatorInterface $aggregator
    ) {
        $attributes        =
            isset($jsonData[DocumentInterface::KEYWORD_DATA][DocumentInterface::KEYWORD_ATTRIBUTES]) === true ?
                $jsonData[DocumentInterface::KEYWORD_DATA][DocumentInterface::KEYWORD_ATTRIBUTES] : [];
        $attributeCaptures = [];
        foreach ($attributeRules as $name => $rule) {
            $captureName              = $schema->getAttributeMapping($name);
            $attributeCaptures[$name] = static::singleCapture($captureName, $rule, $aggregator);
        }
        $dataErrors = $this
            ->validateRule(static::arrayX($attributeCaptures, $this->getUnlistedAttributeRule()), $attributes);
        foreach ($dataErrors as $error) {
            /** @var Error $error */
            $title  = $this->getJsonApiTranslator()->get(T::MSG_ERR_INVALID_ELEMENT);
            $detail = $this->getValidationTranslator()->translate($error);
            $errors->addDataAttributeError($error->getParameterName(), $title, $detail, $this->getErrorStatus());
        }
    }

    /**
     * @param SchemaInterface            $schema
     * @param RuleInterface[]            $toOneRules
     * @param CaptureAggregatorInterface $toOneAggregator
     * @param RuleInterface[]            $toManyRules
     * @param CaptureAggregatorInterface $toManyAggregator
     *
     * @return array
     */
    private function createRelationshipCaptures(
        SchemaInterface $schema,
        array $toOneRules,
        CaptureAggregatorInterface $toOneAggregator,
        array $toManyRules,
        CaptureAggregatorInterface $toManyAggregator
    ) {
        $modelClass           = $schema::MODEL;
        $relationshipCaptures = [];
        foreach ($toOneRules as $name => $rule) {
            $modelRelName   = $schema->getRelationshipMapping($name);
            $captureName    = $this->getModelSchemes()->getForeignKey($modelClass, $modelRelName);
            $expectedSchema = $this->getJsonSchemes()->getModelRelationshipSchema($modelClass, $modelRelName);
            $relationshipCaptures[$name] = $this->createSingleData($name, $this->createOptionalIdentity(
                static::equals($expectedSchema::TYPE),
                static::singleCapture($captureName, $rule, $toOneAggregator)
            ));
        }
        foreach ($toManyRules as $name => $rule) {
            $modelRelName   = $schema->getRelationshipMapping($name);
            $expectedSchema = $this->getJsonSchemes()->getModelRelationshipSchema($modelClass, $modelRelName);
            $captureName    = $modelRelName;
            $relationshipCaptures[$name] = $this->createMultiData($name, $this->createOptionalIdentity(
                static::equals($expectedSchema::TYPE),
                static::multiCapture($captureName, $rule, $toManyAggregator)
            ));
        }

        return $relationshipCaptures;
    }

    /**
     * @param ErrorCollection $errors
     * @param array           $jsonData
     * @param array           $relationshipCaptures
     *
     * @return void
     */
    private function validateCaptures(ErrorCollection $errors, array $jsonData, array $relationshipCaptures)
    {
        $relationships =
            isset($jsonData[DocumentInterface::KEYWORD_DATA][DocumentInterface::KEYWORD_RELATIONSHIPS]) === true ?
                $jsonData[DocumentInterface::KEYWORD_DATA][DocumentInterface::KEYWORD_RELATIONSHIPS] : [];
        $dataErrors    = $this->validateRule(
            static::arrayX($relationshipCaptures, $this->getUnlistedRelationshipRule()),
            $relationships
        );
        foreach ($dataErrors as $error) {
            /** @var Error $error */
            $title  = $this->getJsonApiTranslator()->get(T::MSG_ERR_INVALID_ELEMENT);
            $detail = $this->getValidationTranslator()->translate($error);
            $errors->addRelationshipError($error->getParameterName(), $title, $detail, $this->getErrorStatus());
        }
    }

    /**
     * @param RuleInterface $typeRule
     * @param RuleInterface $idRule
     *
     * @return RuleInterface
     */
    private function createOptionalIdentity(RuleInterface $typeRule, RuleInterface $idRule)
    {
        return self::andX(self::isArray(), self::arrayX([
            DocumentInterface::KEYWORD_TYPE => $typeRule,
            DocumentInterface::KEYWORD_ID   => $idRule,
        ])->disableAutoParameterNames());
    }

    /**
     * @param string        $name
     * @param RuleInterface $identityRule
     *
     * @return RuleInterface
     */
    private function createSingleData($name, RuleInterface $identityRule)
    {
        return static::andX(static::isArray(), static::arrayX([
            DocumentInterface::KEYWORD_DATA => $identityRule,
        ])->disableAutoParameterNames()->setParameterName($name));
    }

    /**
     * @param string        $name
     * @param RuleInterface $identityRule
     *
     * @return RuleInterface
     */
    private function createMultiData($name, RuleInterface $identityRule)
    {
        return static::andX(static::isArray(), static::arrayX([
            DocumentInterface::KEYWORD_DATA => static::andX(static::isArray(), static::eachX($identityRule)),
        ])->disableAutoParameterNames()->setParameterName($name));
    }

    /**
     * @param RuleInterface $rule
     * @param mixed         $input
     *
     * @return Generator
     */
    private function validateRule(RuleInterface $rule, $input)
    {
        return static::validateData($rule, $input, new ErrorAggregator());
    }
}
