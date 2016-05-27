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
use Limoncello\Tests\JsonApi\Data\Models\CommentEmotion;
use Limoncello\Tests\JsonApi\Data\Models\Emotion;
use PDO;

/**
 * @package Limoncello\Tests\JsonApi
 */
class CommentEmotionsMigration extends Migration
{
    /**
     * @inheritdoc
     */
    public function migrate(PDO $pdo)
    {
        $this->createTable($pdo, CommentEmotion::TABLE_NAME, [
            $this->primaryInt(CommentEmotion::FIELD_ID),
            $this->int(CommentEmotion::FIELD_ID_COMMENT),
            $this->int(CommentEmotion::FIELD_ID_EMOTION),
            $this->date(CommentEmotion::FIELD_CREATED_AT),
            $this->date(CommentEmotion::FIELD_UPDATED_AT),
            $this->date(CommentEmotion::FIELD_DELETED_AT),
            $this->foreignKey(CommentEmotion::FIELD_ID_COMMENT, Comment::TABLE_NAME, Comment::FIELD_ID),
            $this->foreignKey(CommentEmotion::FIELD_ID_EMOTION, Emotion::TABLE_NAME, Emotion::FIELD_ID),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function rollback(PDO $pdo)
    {
        $this->dropTable($pdo, CommentEmotion::TABLE_NAME);
    }
}
