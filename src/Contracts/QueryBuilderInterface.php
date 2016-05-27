<?php namespace Limoncello\JsonApi\Contracts;

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

use Generator;

/**
 * @package Limoncello\JsonApi
 */
interface QueryBuilderInterface
{
    /** Operator name */
    const SQL_IS = 'IS';

    /** Operator name */
    const SQL_IS_NOT = 'IS NOT';

    /** Operator name */
    const SQL_IN = 'IN';

    /** SQL NULL */
    const SQL_NULL = 'NULL';

    /**
     * @param string $name
     *
     * @return $this
     */
    public function forTable($name);

    /**
     * @param Generator $columns
     * @param Generator $values
     *
     * @return $this
     */
    public function insert(Generator $columns, Generator $values);

    /**
     * @param Generator $columns
     *
     * @return $this
     */
    public function select(Generator $columns);

    /**
     * @param Generator $columns
     *
     * @return $this
     */
    public function update(Generator $columns);

    /**
     * @return $this
     */
    public function delete();

    /**
     * @param Generator $joins
     *
     * @return $this
     */
    public function join(Generator $joins);

    /**
     * @param Generator $wheres
     *
     * @return $this
     */
    public function where(Generator $wheres);

    /**
     * @param Generator $sorts
     *
     * @return $this
     */
    public function sort(Generator $sorts);

    /**
     * @param int $limit
     * @param int $offset
     *
     * @return $this
     */
    public function limit($limit, $offset = 0);

    /**
     * @return array
     */
    public function get();

    /**
     * @return void
     */
    public function reset();

    /**
     * @param string[] $queries
     *
     * @return string
     */
    public function implode(array $queries);
}
