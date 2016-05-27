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

use Limoncello\Tests\JsonApi\Data\Models\Role;
use Limoncello\Tests\JsonApi\Data\Models\User;
use PDO;

/**
 * @package Limoncello\Tests\JsonApi
 */
class UsersMigration extends Migration
{
    /**
     * @inheritdoc
     */
    public function migrate(PDO $pdo)
    {
        $this->createTable($pdo, User::TABLE_NAME, [
            $this->primaryInt(User::FIELD_ID),
            $this->int(User::FIELD_ID_ROLE),
            $this->text(User::FIELD_TITLE),
            $this->text(User::FIELD_FIRST_NAME),
            $this->text(User::FIELD_LAST_NAME),
            $this->text(User::FIELD_LANGUAGE),
            $this->text(User::FIELD_EMAIL),
            $this->bool(User::FIELD_IS_ACTIVE),
            $this->text(User::FIELD_PASSWORD_HASH),
            $this->text(User::FIELD_API_TOKEN),
            $this->date(User::FIELD_CREATED_AT),
            $this->date(User::FIELD_UPDATED_AT),
            $this->date(User::FIELD_DELETED_AT),
            $this->foreignKey(User::FIELD_ID_ROLE, Role::TABLE_NAME, Role::FIELD_ID),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function rollback(PDO $pdo)
    {
        $this->dropTable($pdo, User::TABLE_NAME);
    }
}
