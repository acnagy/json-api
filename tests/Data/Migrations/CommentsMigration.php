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

use Limoncello\Tests\JsonApi\Data\Models\Comment;
use Limoncello\Tests\JsonApi\Data\Models\Post;
use Limoncello\Tests\JsonApi\Data\Models\User;
use PDO;

/**
 * @package Limoncello\Tests\JsonApi
 */
class CommentsMigration extends Migration
{
    /**
     * @inheritdoc
     */
    public function migrate(PDO $pdo)
    {
        $this->createTable($pdo, Comment::TABLE_NAME, [
            $this->primaryInt(Comment::FIELD_ID),
            $this->int(Comment::FIELD_ID_USER),
            $this->int(Comment::FIELD_ID_POST),
            $this->text(Comment::FIELD_TEXT),
            $this->date(Comment::FIELD_CREATED_AT),
            $this->date(Comment::FIELD_UPDATED_AT),
            $this->date(Comment::FIELD_DELETED_AT),
            $this->foreignKey(Comment::FIELD_ID_USER, User::TABLE_NAME, User::FIELD_ID),
            $this->foreignKey(Comment::FIELD_ID_POST, Post::TABLE_NAME, Post::FIELD_ID),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function rollback(PDO $pdo)
    {
        $this->dropTable($pdo, Comment::TABLE_NAME);
    }
}
