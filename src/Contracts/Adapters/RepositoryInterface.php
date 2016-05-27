<?php namespace Limoncello\JsonApi\Contracts\Adapters;

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

use Closure;
use Limoncello\Models\Contracts\PaginatedDataInterface;
use Limoncello\Models\Contracts\RelationshipStorageInterface;
use Neomerx\JsonApi\Exceptions\ErrorCollection;

/**
 * @package Limoncello\JsonApi
 */
interface RepositoryInterface
{
    /**
     * @param Closure $closure
     *
     * @return void
     */
    public function inTransaction(Closure $closure);

    /**
     * @param array|null $filterParams
     * @param array|null $sortParams
     * @param array|null $pagingParams
     *
     * @return PaginatedDataInterface
     */
    public function index(array $filterParams = null, array $sortParams = null, array $pagingParams = null);

    /**
     * @param int|string $index
     *
     * @return mixed
     */
    public function read($index);

    /**
     * @param array $models
     * @param array $paths
     *
     * @return RelationshipStorageInterface
     */
    public function readRelationships(array $models, array $paths);

    /**
     * @param int|string $index
     * @param string     $relationshipName
     * @param array|null $filterParams
     * @param array|null $sortParams
     * @param array|null $pagingParams
     *
     * @return PaginatedDataInterface
     */
    public function readRelationship(
        $index,
        $relationshipName,
        array $filterParams = null,
        array $sortParams = null,
        array $pagingParams = null
    );

    /**
     * @param null|string|int $identity
     *
     * @return mixed
     */
    public function instance($identity = null);

    /**
     * @param int|string $index
     *
     * @return void
     */
    public function delete($index);

    /**
     * @param mixed $model
     *
     * @return int|string
     */
    public function create($model);

    /**
     * @param mixed $model
     * @param mixed $original
     *
     * @return void
     */
    public function update($model, $original);

    /**
     * @param mixed  $model
     * @param string $name
     * @param string $value
     *
     * @return void
     */
    public function setAttribute($model, $name, $value);

    /**
     * @param mixed      $model
     * @param string     $name
     * @param string|int $value
     *
     * @return void
     */
    public function setToOneRelationship($model, $name, $value);

    /**
     * @param int|string $index
     * @param string     $name
     * @param array      $values
     *
     * @return void
     */
    public function saveToManyRelationship($index, $name, array $values);

    /**
     * @param int|string $index
     * @param string     $name
     *
     * @return void
     */
    public function cleanToManyRelationship($index, $name);

    /**
     * @return ErrorCollection
     */
    public function getErrors();
}
