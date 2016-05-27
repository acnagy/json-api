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

use Limoncello\Models\FieldTypes;
use Limoncello\Models\RelationshipTypes;

/**
 * @package Limoncello\Tests\JsonApi
 */
class Post extends Model
{
    /** @inheritdoc */
    const TABLE_NAME = 'posts';

    /** @inheritdoc */
    const FIELD_ID = 'id_post';

    /** Field name */
    const FIELD_ID_BOARD = 'id_board_fk';

    /** Field name */
    const FIELD_ID_USER = 'id_user_fk';

    /** Relationship name */
    const REL_BOARD = 'board';

    /** Relationship name */
    const REL_USER = 'user';

    /** Relationship name */
    const REL_COMMENTS = 'comments';

    /** Field name */
    const FIELD_TITLE = 'title';

    /** Field name */
    const FIELD_TEXT = 'text';

    /**
     * @inheritdoc
     */
    public static function getAttributeTypes()
    {
        return [
            self::FIELD_ID         => FieldTypes::INT,
            self::FIELD_ID_BOARD   => FieldTypes::INT,
            self::FIELD_ID_USER    => FieldTypes::INT,
            self::FIELD_TITLE      => FieldTypes::STRING,
            self::FIELD_TEXT       => FieldTypes::TEXT,
            self::FIELD_CREATED_AT => FieldTypes::DATE,
            self::FIELD_UPDATED_AT => FieldTypes::DATE,
            self::FIELD_DELETED_AT => FieldTypes::DATE,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function getAttributeLengths()
    {
        return [
            self::FIELD_TITLE => 255,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function getRelationships()
    {
        return [
            RelationshipTypes::BELONGS_TO => [
                self::REL_BOARD => [Board::class, self::FIELD_ID_BOARD, Board::REL_POSTS],
                self::REL_USER  => [User::class, self::FIELD_ID_USER, User::REL_POSTS],
            ],
            RelationshipTypes::HAS_MANY   => [
                self::REL_COMMENTS => [Comment::class, Comment::FIELD_ID_POST, Comment::REL_POST],
            ],
        ];
    }
}
