<?php namespace Limoncello\Tests\JsonApi\Data\Validation;

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
use Limoncello\JsonApi\Contracts\I18n\TranslatorInterface as JsonApiTranslatorInterface;
use Limoncello\JsonApi\Contracts\Models\ModelSchemesInterface;
use Limoncello\JsonApi\Contracts\Schema\JsonSchemesInterface;
use Limoncello\JsonApi\Validation\Validator;
use Limoncello\Tests\JsonApi\Data\Models\Category;
use Limoncello\Tests\JsonApi\Data\Models\Emotion;
use Limoncello\Tests\JsonApi\Data\Models\Post;
use Limoncello\Tests\JsonApi\Data\Models\Role;
use Limoncello\Validation\Captures\CaptureAggregator;
use Limoncello\Validation\Contracts\MessageCodes;
use Limoncello\Validation\Contracts\RuleInterface;
use Limoncello\Validation\Contracts\TranslatorInterface as ValidationTranslatorInterface;

/**
 * @package Limoncello\Tests\JsonApi
 */
class AppValidator extends Validator
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param JsonApiTranslatorInterface    $jsonApiTranslator
     * @param ValidationTranslatorInterface $validationTranslator
     * @param JsonSchemesInterface          $jsonSchemes
     * @param ModelSchemesInterface         $modelSchemes
     * @param Connection                    $connection
     */
    public function __construct(
        JsonApiTranslatorInterface $jsonApiTranslator,
        ValidationTranslatorInterface $validationTranslator,
        JsonSchemesInterface $jsonSchemes,
        ModelSchemesInterface $modelSchemes,
        Connection $connection
    ) {
        $this->connection      = $connection;
        $errorStatus           = 422;
        $unlistedAttributeRule = $unlistedRelationshipRule = static::fail();

        parent::__construct(
            $jsonApiTranslator,
            $validationTranslator,
            $jsonSchemes,
            $modelSchemes,
            $errorStatus,
            $unlistedAttributeRule,
            $unlistedRelationshipRule
        );
    }

    /**
     * @param int|string $index
     *
     * @return RuleInterface
     */
    public function idEquals($index)
    {
        return static::equals($index);
    }

    /**
     * @return RuleInterface
     */
    public function absentOrNull()
    {
        return static::isNull();
    }

    /**
     * @param int|null $maxLength
     *
     * @return RuleInterface
     */
    public function requiredText($maxLength = null)
    {
        return static::required($this->optionalText($maxLength));
    }

    /**
     * @param int|null $maxLength
     *
     * @return RuleInterface
     */
    public function optionalText($maxLength = null)
    {
        return static::andX(static::isString(), static::stringLength(1, $maxLength));
    }

    /**
     * @param int $messageCode
     *
     * @return RuleInterface
     */
    public function requiredPostId($messageCode = MessageCodes::INVALID_VALUE)
    {
        return static::required($this->optionalPostId($messageCode));
    }

    /**
     * @param int $messageCode
     *
     * @return RuleInterface
     */
    public function requiredRoleId($messageCode = MessageCodes::INVALID_VALUE)
    {
        return static::required($this->optionalRoleId($messageCode));
    }

    /**
     * @param int $messageCode
     *
     * @return RuleInterface
     */
    public function optionalPostId($messageCode = MessageCodes::INVALID_VALUE)
    {
        $exists = function ($index) {
            return static::exists($this->getConnection(), Post::TABLE_NAME, Post::FIELD_ID, $index);
        };

        return static::andX(static::isNumeric(), static::callableX($exists, $messageCode));
    }

    /**
     * @param int $messageCode
     *
     * @return RuleInterface
     */
    public function optionalCategoryId($messageCode = MessageCodes::INVALID_VALUE)
    {
        $exists = function ($index) {
            return static::exists($this->getConnection(), Category::TABLE_NAME, Category::FIELD_ID, $index);
        };

        return static::andX(static::isNumeric(), static::callableX($exists, $messageCode));
    }

    /**
     * @param int $messageCode
     *
     * @return RuleInterface
     */
    public function optionalEmotionId($messageCode = MessageCodes::INVALID_VALUE)
    {
        $exists = function ($index) {
            return static::exists($this->getConnection(), Emotion::TABLE_NAME, Emotion::FIELD_ID, $index);
        };

        return static::callableX($exists, $messageCode);
    }

    /**
     * @param int $messageCode
     *
     * @return RuleInterface
     */
    public function optionalRoleId($messageCode = MessageCodes::INVALID_VALUE)
    {
        $exists = function ($index) {
            return static::exists($this->getConnection(), Role::TABLE_NAME, Role::FIELD_ID, $index);
        };

        return static::callableX($exists, $messageCode);
    }

    /**
     * @param Connection $connection
     * @param string     $tableName
     * @param string     $columnName
     * @param string     $value
     *
     * @return bool
     */
    protected static function exists(Connection $connection, $tableName, $columnName, $value)
    {
        $fetched = $connection
            ->executeQuery("SELECT $columnName FROM $tableName WHERE $columnName = ? LIMIT 1", [$value])
            ->fetch();
        $result = $fetched !== false;

        return $result;
    }

    /**
     * @return Connection
     */
    protected function getConnection()
    {
        return $this->connection;
    }

    /**
     * @inheritdoc
     */
    protected function createIdCaptureAggregator()
    {
        return new CaptureAggregator();
    }

    /**
     * @inheritdoc
     */
    protected function createAttributesAndToOneCaptureAggregator()
    {
        return new CaptureAggregator();
    }

    /**
     * @inheritdoc
     */
    protected function createToManyCaptureAggregator()
    {
        return new CaptureAggregator();
    }
}
