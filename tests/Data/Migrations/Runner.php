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

use Doctrine\DBAL\Schema\AbstractSchemaManager;

/**
 * @package Limoncello\Tests\JsonApi
 */
class Runner
{
    /**
     * @var string[]
     */
    private $migrations = [
        BoardsMigration::class,
        RolesMigration::class,
        EmotionsMigration::class,
        UsersMigration::class,
        PostsMigration::class,
        CommentsMigration::class,
        CommentEmotionsMigration::class,
    ];

    /**
     * @param AbstractSchemaManager $schemaManager
     *
     * @return void
     */
    public function migrate(AbstractSchemaManager $schemaManager)
    {
        foreach ($this->migrations as $class) {
            /** @var Migration $migration */
            $migration = new $class($schemaManager);
            $migration->migrate();
        }
    }

    /**
     * @param AbstractSchemaManager $schemaManager
     *
     * @return void
     */
    public function rollback(AbstractSchemaManager $schemaManager)
    {
        foreach (array_reverse($this->migrations, false) as $class) {
            /** @var Migration $migration */
            $migration = new $class($schemaManager);
            $migration->rollback();
        }
    }
}
