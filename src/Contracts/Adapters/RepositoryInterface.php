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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Limoncello\JsonApi\Contracts\Http\Query\SortParameterInterface;
use Limoncello\JsonApi\Http\Query\FilterParameterCollection;
use Neomerx\JsonApi\Exceptions\ErrorCollection;

/**
 * @package Limoncello\JsonApi
 */
interface RepositoryInterface
{
    /**
     * @param string $modelClass
     *
     * @return QueryBuilder
     */
    public function index($modelClass);

    /**
     * @param string $modelClass
     * @param array  $attributes
     *
     * @return QueryBuilder
     */
    public function create($modelClass, array $attributes);

    /**
     * @param string $modelClass
     * @param string $indexBind
     *
     * @return QueryBuilder
     */
    public function read($modelClass, $indexBind);

    /**
     * @param string $modelClass
     * @param string $indexBind
     * @param string $relationshipName
     *
     * @return array [$builder, $resultClass, $relationshipType]
     */
    public function readRelationship($modelClass, $indexBind, $relationshipName);

    /**
     * @param string     $modelClass
     * @param int|string $index
     * @param array      $attributes
     *
     * @return QueryBuilder
     */
    public function update($modelClass, $index, array $attributes);

    /**
     * @param string $modelClass
     * @param string $indexBind
     *
     * @return QueryBuilder
     */
    public function delete($modelClass, $indexBind);

    /**
     * @param string $modelClass
     * @param string $indexBind
     * @param string $name
     * @param string $otherIndexBind
     *
     * @return QueryBuilder
     */
    public function createToManyRelationship($modelClass, $indexBind, $name, $otherIndexBind);

    /**
     * @param string $modelClass
     * @param string $indexBind
     * @param string $name
     *
     * @return QueryBuilder
     */
    public function cleanToManyRelationship($modelClass, $indexBind, $name);

    /**
     * @param ErrorCollection           $errors
     * @param QueryBuilder              $builder
     * @param string                    $modelClass
     * @param FilterParameterCollection $filterParams
     *
     * @return void
     */
    public function applyFilters(
        ErrorCollection $errors,
        QueryBuilder $builder,
        $modelClass,
        FilterParameterCollection $filterParams
    );

    /**
     * @param QueryBuilder             $builder
     * @param string                   $modelClass
     * @param SortParameterInterface[] $sortParams
     *
     * @return void
     */
    public function applySorting(QueryBuilder $builder, $modelClass, array $sortParams);

    /**
     * @param string $modelClass
     * @param string $relationshipName
     *
     * @return array [$builder, $resultClass, $relationshipType, $table, $column]
     */
    public function createRelationshipBuilder($modelClass, $relationshipName);

    /**
     * @param string $modelClass
     *
     * @return QueryBuilder
     */
    public function count($modelClass);

    /**
     * @return Connection
     */
    public function getConnection();
}
