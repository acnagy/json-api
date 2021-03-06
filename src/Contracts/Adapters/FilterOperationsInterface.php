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
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;
use Neomerx\JsonApi\Exceptions\ErrorCollection;

/**
 * @package Limoncello\JsonApi
 */
interface FilterOperationsInterface
{
    /**
     * @param QueryBuilder        $builder
     * @param CompositeExpression $link
     * @param ErrorCollection     $errors
     * @param string              $table
     * @param string              $column
     * @param string|array        $params
     *
     * @return void
     */
    public function applyEquals(
        QueryBuilder $builder,
        CompositeExpression $link,
        ErrorCollection $errors,
        $table,
        $column,
        $params
    );

    /**
     * @param QueryBuilder        $builder
     * @param CompositeExpression $link
     * @param ErrorCollection     $errors
     * @param string              $table
     * @param string              $column
     * @param string|array        $params
     *
     * @return void
     */
    public function applyNotEquals(
        QueryBuilder $builder,
        CompositeExpression $link,
        ErrorCollection $errors,
        $table,
        $column,
        $params
    );

    /**
     * @param QueryBuilder        $builder
     * @param CompositeExpression $link
     * @param ErrorCollection     $errors
     * @param string              $table
     * @param string              $column
     * @param string|array        $params
     *
     * @return void
     */
    public function applyGreaterThan(
        QueryBuilder $builder,
        CompositeExpression $link,
        ErrorCollection $errors,
        $table,
        $column,
        $params
    );

    /**
     * @param QueryBuilder        $builder
     * @param CompositeExpression $link
     * @param ErrorCollection     $errors
     * @param string              $table
     * @param string              $column
     * @param string|array        $params
     *
     * @return void
     */
    public function applyGreaterOrEquals(
        QueryBuilder $builder,
        CompositeExpression $link,
        ErrorCollection $errors,
        $table,
        $column,
        $params
    );

    /**
     * @param QueryBuilder        $builder
     * @param CompositeExpression $link
     * @param ErrorCollection     $errors
     * @param string              $table
     * @param string              $column
     * @param string|array        $params
     *
     * @return void
     */
    public function applyLessThan(
        QueryBuilder $builder,
        CompositeExpression $link,
        ErrorCollection $errors,
        $table,
        $column,
        $params
    );

    /**
     * @param QueryBuilder        $builder
     * @param CompositeExpression $link
     * @param ErrorCollection     $errors
     * @param string              $table
     * @param string              $column
     * @param string|array        $params
     *
     * @return void
     */
    public function applyLessOrEquals(
        QueryBuilder $builder,
        CompositeExpression $link,
        ErrorCollection $errors,
        $table,
        $column,
        $params
    );

    /**
     * @param QueryBuilder        $builder
     * @param CompositeExpression $link
     * @param ErrorCollection     $errors
     * @param string              $table
     * @param string              $column
     * @param string|array        $params
     *
     * @return void
     */
    public function applyLike(
        QueryBuilder $builder,
        CompositeExpression $link,
        ErrorCollection $errors,
        $table,
        $column,
        $params
    );

    /**
     * @param QueryBuilder        $builder
     * @param CompositeExpression $link
     * @param ErrorCollection     $errors
     * @param string              $table
     * @param string              $column
     * @param string|array        $params
     *
     * @return void
     */
    public function applyNotLike(
        QueryBuilder $builder,
        CompositeExpression $link,
        ErrorCollection $errors,
        $table,
        $column,
        $params
    );

    /**
     * @param QueryBuilder        $builder
     * @param CompositeExpression $link
     * @param ErrorCollection     $errors
     * @param string              $table
     * @param string              $column
     * @param array               $values
     *
     * @return void
     */
    public function applyIn(
        QueryBuilder $builder,
        CompositeExpression $link,
        ErrorCollection $errors,
        $table,
        $column,
        array $values
    );

    /**
     * @param QueryBuilder        $builder
     * @param CompositeExpression $link
     * @param ErrorCollection     $errors
     * @param string              $table
     * @param string              $column
     * @param array               $values
     *
     * @return void
     */
    public function applyNotIn(
        QueryBuilder $builder,
        CompositeExpression $link,
        ErrorCollection $errors,
        $table,
        $column,
        array $values
    );

    /**
     * @param QueryBuilder        $builder
     * @param CompositeExpression $link
     * @param string              $table
     * @param string              $column
     *
     * @return void
     */
    public function applyIsNull(QueryBuilder $builder, CompositeExpression $link, $table, $column);

    /**
     * @param QueryBuilder        $builder
     * @param CompositeExpression $link
     * @param string              $table
     * @param string              $column
     *
     * @return void
     */
    public function applyIsNotNull(QueryBuilder $builder, CompositeExpression $link, $table, $column);
}
