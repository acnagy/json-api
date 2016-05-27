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

use PDO;

/**
 * @package Limoncello\Tests\JsonApi
 */
abstract class Migration
{
    /**
     * @param PDO $pdo
     *
     * @return void
     */
    abstract public function migrate(PDO $pdo);

    /**
     * @param PDO $pdo
     *
     * @return void
     */
    abstract public function rollback(PDO $pdo);

    /**
     * @param PDO    $pdo
     * @param string $tableName
     * @param array  $fields
     *
     * @return $this
     */
    protected function createTable(PDO $pdo, $tableName, array $fields)
    {
        $statement = $this->getCreateTableStatement($tableName, $fields);
        $result    = $pdo->exec($statement);
        assert($result !== false, 'Statement execution failed');

        return $this;
    }

    /**
     * @param PDO    $pdo
     * @param string $tableName
     *
     * @return $this
     */
    protected function dropTable(PDO $pdo, $tableName)
    {
        $statement = "DROP TABLE IF EXISTS $tableName";
        $result    = $pdo->exec($statement);
        assert($result !== false, 'Statement execution failed');

        return $this;
    }

    /**
     * @param string $tableName
     * @param array  $fields
     *
     * @return string
     */
    protected function getCreateTableStatement($tableName, array $fields)
    {
        $columns   = implode(", ", $fields);
        $statement = "CREATE TABLE IF NOT EXISTS $tableName ($columns)";

        return $statement;
    }

    /**
     * @param string $column
     * @param string $foreignTable
     * @param string $foreignColumn
     *
     * @return string
     */
    protected function foreignKey($column, $foreignTable, $foreignColumn)
    {
        return "FOREIGN KEY($column) REFERENCES $foreignTable($foreignColumn)";
    }

    /**
     * @param string $column
     * @param string $asc
     *
     * @return string
     */
    protected function primaryInt($column, $asc = 'ASC')
    {
        return $column . " INTEGER PRIMARY KEY $asc NOT NULL";
    }

    /**
     * @param string $column
     *
     * @return string
     */
    protected function int($column)
    {
        return $column . " INTEGER NOT NULL";
    }

    /**
     * @param string $column
     *
     * @return string
     */
    protected function date($column)
    {
        return $column . " DATETIME";
    }

    /**
     * @param string $column
     *
     * @return string
     */
    protected function text($column)
    {
        return $column . " TEXT NOT NULL";
    }

    /**
     * @param string $column
     *
     * @return string
     */
    protected function bool($column)
    {
        return $column . " BOOL NOT NULL";
    }

    /**
     * @param string $column
     *
     * @return string
     */
    protected function textUnique($column)
    {
        return $column . " TEXT UNIQUE NOT NULL";
    }
}
