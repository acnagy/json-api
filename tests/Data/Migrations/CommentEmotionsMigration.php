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
use Limoncello\Tests\JsonApi\Data\Models\CommentEmotion as Model;
use Limoncello\Tests\JsonApi\Data\Models\Emotion;

/**
 * @package Limoncello\Tests\JsonApi
 */
class CommentEmotionsMigration extends Migration
{
    /** @inheritdoc */
    const MODEL_CLASS = Model::class;

    /**
     * @inheritdoc
     */
    public function migrate()
    {
        $this->createTable(Model::TABLE_NAME, [
            $this->primaryInt(Model::FIELD_ID),
            $this->foreignInt(Model::FIELD_ID_COMMENT, Comment::class),
            $this->foreignInt(Model::FIELD_ID_EMOTION, Emotion::class),
            $this->datetime(Model::FIELD_CREATED_AT),
            $this->nullableDatetime(Model::FIELD_UPDATED_AT),
            $this->nullableDatetime(Model::FIELD_DELETED_AT),

            $this->unique([Model::FIELD_ID_COMMENT, Model::FIELD_ID_EMOTION]),
        ]);
    }
}
