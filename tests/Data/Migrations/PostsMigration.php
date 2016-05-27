<?php namespace Limoncello\Tests\JsonApi\Data\Migrations;

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

use Limoncello\Tests\JsonApi\Data\Models\Board;
use Limoncello\Tests\JsonApi\Data\Models\Post;
use Limoncello\Tests\JsonApi\Data\Models\User;
use PDO;

/**
 * @package Limoncello\Tests\JsonApi
 */
class PostsMigration extends Migration
{
    /**
     * @inheritdoc
     */
    public function migrate(PDO $pdo)
    {
        $this->createTable($pdo, Post::TABLE_NAME, [
            $this->primaryInt(Post::FIELD_ID),
            $this->int(Post::FIELD_ID_USER),
            $this->int(Post::FIELD_ID_BOARD),
            $this->text(Post::FIELD_TITLE),
            $this->text(Post::FIELD_TEXT),
            $this->date(Post::FIELD_CREATED_AT),
            $this->date(Post::FIELD_UPDATED_AT),
            $this->date(Post::FIELD_DELETED_AT),
            $this->foreignKey(Post::FIELD_ID_BOARD, Board::TABLE_NAME, Board::FIELD_ID),
            $this->foreignKey(Post::FIELD_ID_USER, User::TABLE_NAME, User::FIELD_ID),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function rollback(PDO $pdo)
    {
        $this->dropTable($pdo, Post::TABLE_NAME);
    }
}
