<?php namespace Limoncello\Tests\JsonApi\Data\Models;

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

use Doctrine\DBAL\Types\Type;
use Limoncello\JsonApi\Models\RelationshipTypes;
use Limoncello\Tests\JsonApi\Data\Types\SystemDateTimeType;

/**
 * @package Limoncello\Tests\JsonApi
 */
class Comment extends Model
{
    /** @inheritdoc */
    const TABLE_NAME = 'comments';

    /** @inheritdoc */
    const FIELD_ID = 'id_comment';

    /** Field name */
    const FIELD_ID_POST = 'id_post_fk';

    /** Field name */
    const FIELD_ID_USER = 'id_user_fk';

    /** Relationship name */
    const REL_POST = 'post';

    /** Relationship name */
    const REL_USER = 'user';

    /** Relationship name */
    const REL_EMOTIONS = 'emotions';

    /** Field name */
    const FIELD_TEXT = 'text';

    /** Length constant */
    const LENGTH_TEXT = 255;

    /**
     * @inheritdoc
     */
    public static function getAttributeTypes()
    {
        return [
            self::FIELD_ID         => Type::INTEGER,
            self::FIELD_ID_POST    => Type::INTEGER,
            self::FIELD_ID_USER    => Type::INTEGER,
            self::FIELD_TEXT       => Type::STRING,
            self::FIELD_CREATED_AT => SystemDateTimeType::NAME,
            self::FIELD_UPDATED_AT => SystemDateTimeType::NAME,
            self::FIELD_DELETED_AT => SystemDateTimeType::NAME,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function getAttributeLengths()
    {
        return [
            self::FIELD_TEXT => self::LENGTH_TEXT,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function getRelationships()
    {
        return [
            RelationshipTypes::BELONGS_TO => [
                self::REL_POST => [Post::class, self::FIELD_ID_POST, Post::REL_COMMENTS],
                self::REL_USER => [User::class, self::FIELD_ID_USER, User::REL_COMMENTS],
            ],
            RelationshipTypes::BELONGS_TO_MANY => [
                self::REL_EMOTIONS => [
                    Emotion::class,
                    CommentEmotion::TABLE_NAME,
                    CommentEmotion::FIELD_ID_COMMENT,
                    CommentEmotion::FIELD_ID_EMOTION,
                    Emotion::REL_COMMENTS,
                ],
            ],
        ];
    }
}
