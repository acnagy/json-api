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
class Board extends Model
{
    /** @inheritdoc */
    const TABLE_NAME = 'boards';

    /** @inheritdoc */
    const FIELD_ID = 'id_board';

    /** Relationship name */
    const REL_POSTS = 'posts';

    /** Field name */
    const FIELD_TITLE = 'title';

    /**
     * @inheritdoc
     */
    public static function getAttributeTypes()
    {
        return [
            self::FIELD_ID         => Type::INTEGER,
            self::FIELD_TITLE      => Type::STRING,
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
            self::FIELD_TITLE => 255,
        ];
    }

    /**
     * @inheritdoc
     */
    public static function getRelationships()
    {
        return [
            RelationshipTypes::HAS_MANY => [
                self::REL_POSTS => [Post::class, Post::FIELD_ID_BOARD, Post::REL_BOARD],
            ],
        ];
    }
}
