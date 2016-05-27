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

use Limoncello\Tests\JsonApi\Data\Models\Emotion;
use PDO;

/**
 * @package Limoncello\Tests\JsonApi
 */
class EmotionsMigration extends Migration
{
    /**
     * @inheritdoc
     */
    public function migrate(PDO $pdo)
    {
        $this->createTable($pdo, Emotion::TABLE_NAME, [
            $this->primaryInt(Emotion::FIELD_ID),
            $this->textUnique(Emotion::FIELD_NAME),
            $this->date(Emotion::FIELD_CREATED_AT),
            $this->date(Emotion::FIELD_UPDATED_AT),
            $this->date(Emotion::FIELD_DELETED_AT),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function rollback(PDO $pdo)
    {
        $this->dropTable($pdo, Emotion::TABLE_NAME);
    }
}
