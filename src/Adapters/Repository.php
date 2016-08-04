<?php namespace Limoncello\JsonApi\Adapters;

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
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\QueryBuilder;
use Limoncello\JsonApi\Contracts\Adapters\FilterOperationsInterface;
use Limoncello\JsonApi\Contracts\Adapters\RepositoryInterface;
use Limoncello\JsonApi\Contracts\Http\Query\FilterParameterInterface;
use Limoncello\JsonApi\Contracts\Http\Query\SortParameterInterface;
use Limoncello\JsonApi\Contracts\I18n\TranslatorInterface as T;
use Limoncello\JsonApi\Http\Query\FilterParameterCollection;
use Limoncello\Models\Contracts\ModelSchemesInterface;
use Limoncello\Models\RelationshipTypes;
use Neomerx\JsonApi\Exceptions\ErrorCollection;

/**
 * @package Limoncello\JsonApi
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Repository implements RepositoryInterface
{
    /** Filer constant */
    const FILTER_OP_IS_NULL = 'is-null';

    /** Filer constant */
    const FILTER_OP_IS_NOT_NULL = 'not-null';

    /** Default filtering operation */
    const DEFAULT_FILTER_OPERATION = 'in';

    /** Default filtering operation */
    const DEFAULT_FILTER_OPERATION_SINGLE = 'eq';

    /** Default filtering operation */
    const DEFAULT_FILTER_OPERATION_EMPTY = self::FILTER_OP_IS_NULL;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ModelSchemesInterface
     */
    private $modelSchemes;

    /**
     * @var FilterOperationsInterface
     */
    private $filterOperations;

    /**
     * @var T
     */
    private $translator;

    /**
     * @param Connection                $connection
     * @param ModelSchemesInterface     $modelSchemes
     * @param FilterOperationsInterface $filterOperations
     * @param T                         $translator
     */
    public function __construct(
        Connection $connection,
        ModelSchemesInterface $modelSchemes,
        FilterOperationsInterface $filterOperations,
        T $translator
    ) {
        $this->connection       = $connection;
        $this->modelSchemes     = $modelSchemes;
        $this->filterOperations = $filterOperations;
        $this->translator       = $translator;
    }

    /**
     * @inheritdoc
     */
    public function index($modelClass)
    {
        $builder = $this->getConnection()->createQueryBuilder();
        $builder->select($this->getColumns($modelClass))->from($this->getTableName($modelClass));

        return $builder;
    }

    /**
     * @inheritdoc
     */
    public function create($modelClass, array $attributes)
    {
        $builder = $this->getConnection()->createQueryBuilder();

        $valuesAsParams = [];
        foreach ($attributes as $column => $value) {
            $valuesAsParams[$column] = $builder->createNamedParameter((string)$value);
        }

        $builder
            ->insert($this->getTableName($modelClass))
            ->values($valuesAsParams);

        return $builder;
    }

    /**
     * @inheritdoc
     */
    public function read($modelClass, $indexBind)
    {
        $builder = $this->getConnection()->createQueryBuilder();
        $table   = $this->getTableName($modelClass);
        $builder->select($this->getColumns($modelClass))->from($table);
        $this->addWhereBind($builder, $table, $this->getPrimaryKeyName($modelClass), $indexBind);

        return $builder;
    }

    /**
     * @inheritdoc
     */
    public function readRelationship($modelClass, $indexBind, $relationshipName)
    {
        list($builder, $resultClass, $relationshipType, $table, $column) =
            $this->createRelationshipBuilder($modelClass, $relationshipName);

        $this->addWhereBind($builder, $table, $column, $indexBind);

        return [$builder, $resultClass, $relationshipType];
    }

    /**
     * @inheritdoc
     */
    public function update($modelClass, $index, array $attributes)
    {
        $builder = $this->getConnection()->createQueryBuilder();

        $table = $this->getTableName($modelClass);
        $builder->update($table);

        foreach ($attributes as $name => $value) {
            $builder->set($name, $builder->createNamedParameter((string)$value));
        }

        $pkColumn = $this->buildTableColumn($table, $this->getPrimaryKeyName($modelClass));
        $builder->where($pkColumn . '=' . $builder->createNamedParameter($index));

        return $builder;
    }

    /**
     * @inheritdoc
     */
    public function delete($modelClass, $indexBind)
    {
        $builder = $this->getConnection()->createQueryBuilder();

        $table = $this->getTableName($modelClass);
        $builder->delete($table);
        $this->addWhereBind($builder, $table, $this->getPrimaryKeyName($modelClass), $indexBind);

        return $builder;
    }

    /**
     * @inheritdoc
     */
    public function createToManyRelationship($modelClass, $indexBind, $name, $otherIndexBind)
    {
        list ($intermediateTable, $foreignKey, $reverseForeignKey) =
            $this->getModelSchemes()->getBelongsToManyRelationship($modelClass, $name);

        $builder = $this->getConnection()->createQueryBuilder();
        $builder
            ->insert($intermediateTable)
            ->values([
                $foreignKey        => $indexBind,
                $reverseForeignKey => $otherIndexBind,
            ]);

        return $builder;
    }

    /**
     * @inheritdoc
     */
    public function cleanToManyRelationship($modelClass, $indexBind, $name)
    {
        list ($intermediateTable, $foreignKey) =
            $this->getModelSchemes()->getBelongsToManyRelationship($modelClass, $name);

        $builder = $this->getConnection()->createQueryBuilder();
        $builder
            ->delete($intermediateTable);
        $this->addWhereBind($builder, $intermediateTable, $foreignKey, $indexBind);

        return $builder;
    }

    /**
     * @inheritdoc
     */
    public function applySorting(QueryBuilder $builder, $modelClass, array $sortParams)
    {
        $table = $this->getTableName($modelClass);
        foreach ($sortParams as $sortParam) {
            /** @var SortParameterInterface $sortParam */
            $column    = null;
            if ($sortParam->isIsRelationship() === false) {
                $column = $sortParam->getName();
            } elseif ($sortParam->getRelationshipType() === RelationshipTypes::BELONGS_TO) {
                $column = $this->getModelSchemes()->getForeignKey($modelClass, $sortParam->getName());
            }

            if ($column !== null) {
                $builder->addOrderBy(
                    $this->buildTableColumn($table, $column),
                    $sortParam->isAscending() === true ? 'ASC' : 'DESC'
                );
            }
        }
    }

    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function applyFilters(
        ErrorCollection $errors,
        QueryBuilder $builder,
        $modelClass,
        FilterParameterCollection $filterParams
    ) {
        if ($filterParams->count() <= 0) {
            return;
        }

        $whereLink = $filterParams->isWithAnd() === true ? $builder->expr()->andX() : $builder->expr()->orX();

        // join tables need unique aliases. this index is used for making them.
        $aliasId            = 0;
        // while joining tables we select distinct rows. this flag used to apply `distinct` no more than once.
        $hasAppliedDistinct = false;
        $table              = $this->getTableName($modelClass);
        $modelSchemes       = $this->getModelSchemes();
        foreach ($filterParams as $filterParam) {
            /** @var FilterParameterInterface $filterParam */
            $filterValue = $filterParam->getValue();

            // if filter value is not array of 'operation' => parameters (string/array) but
            // just parameters we will apply default operation
            // for example instead of `filter[id][in]=1,2,3,8,9,10` we got `filter[id]=1,2,3,8,9,10`
            if (is_array($filterValue) === false) {
                if (empty($filterValue) === true) {
                    $operation     = static::DEFAULT_FILTER_OPERATION_EMPTY;
                    $filterIndexes = null;
                } else {
                    $filterIndexes = explode(',', $filterValue);
                    $numIndexes    = count($filterIndexes);
                    $operation     = $numIndexes === 1 ?
                        static::DEFAULT_FILTER_OPERATION_SINGLE : static::DEFAULT_FILTER_OPERATION;
                }
                $filterValue = [$operation => $filterIndexes];
            }

            foreach ($filterValue as $operation => $params) {
                $filterTable  = null;
                $filterColumn = null;
                $lcOp = strtolower((string)$operation);

                $aliasId++;

                if ($filterParam->isIsRelationship() === true) {
                    // param for relationship
                    switch ($filterParam->getRelationshipType()) {
                        case RelationshipTypes::BELONGS_TO:
                            $filterTable  = $table;
                            $filterColumn = $modelSchemes->getForeignKey($modelClass, $filterParam->getName());
                            break;
                        case RelationshipTypes::HAS_MANY:
                            // here we join hasMany table and apply filter on its primary key
                            $primaryKey    = $modelSchemes->getPrimaryKey($modelClass);
                            list ($reverseClass, $reverseName) = $modelSchemes
                                ->getReverseRelationship($modelClass, $filterParam->getName());
                            $filterTable   = $modelSchemes->getTable($reverseClass);
                            $reverseFk     = $modelSchemes->getForeignKey($reverseClass, $reverseName);
                            $filterColumn  = $modelSchemes->getPrimaryKey($reverseClass);
                            $aliased       = $filterTable . $aliasId;
                            $joinCondition = $this->buildTableColumn($table, $primaryKey) . '=' .
                                $this->buildTableColumn($aliased, $reverseFk);
                            $builder->innerJoin($table, $filterTable, $aliased, $joinCondition);
                            if ($hasAppliedDistinct === false) {
                                $this->distinct($builder, $modelClass);
                                $hasAppliedDistinct = true;
                            }
                            $filterTable = $aliased;
                            break;
                        case RelationshipTypes::BELONGS_TO_MANY:
                            // here we join intermediate belongsToMany table and apply filter on its 2nd foreign key
                            list ($filterTable, $reversePk , $filterColumn) = $modelSchemes
                                ->getBelongsToManyRelationship($modelClass, $filterParam->getName());
                            $primaryKey    = $modelSchemes->getPrimaryKey($modelClass);
                            $aliased       = $filterTable . $aliasId;
                            $joinCondition = $this->buildTableColumn($table, $primaryKey) . '=' .
                                $this->buildTableColumn($aliased, $reversePk);
                            $builder->innerJoin($table, $filterTable, $aliased, $joinCondition);
                            if ($hasAppliedDistinct === false) {
                                $this->distinct($builder, $modelClass);
                                $hasAppliedDistinct = true;
                            }
                            $filterTable = $aliased;
                            break;
                    }
                } else {
                    // param for attribute
                    $filterTable  = $table;
                    $filterColumn = $filterParam->getName();
                }

                // here $filterTable and $filterColumn should always be not null (if not it's a bug in logic)

                $this->applyFilterToQuery(
                    $errors,
                    $builder,
                    $whereLink,
                    $filterParam,
                    $filterTable,
                    $filterColumn,
                    $lcOp,
                    $params
                );
            }
        }

        $builder->andWhere($whereLink);
    }

    /**
     * @inheritdoc
     */
    public function createRelationshipBuilder($modelClass, $relationshipName)
    {
        $builder          = null;
        $resultClass      = null;
        $relationshipType = null;
        $table            = null;
        $column           = null;

        $relationshipType = $this->getModelSchemes()->getRelationshipType($modelClass, $relationshipName);
        switch ($relationshipType) {
            case RelationshipTypes::BELONGS_TO:
                list($builder, $resultClass, $table, $column) =
                    $this->createBelongsToBuilder($modelClass, $relationshipName);
                break;
            case RelationshipTypes::HAS_MANY:
                list($builder, $resultClass, $table, $column) =
                    $this->createHasManyBuilder($modelClass, $relationshipName);
                break;
            case RelationshipTypes::BELONGS_TO_MANY:
                list($builder, $resultClass, $table, $column) =
                    $this->createBelongsToManyBuilder($modelClass, $relationshipName);
                break;
        }

        return [$builder, $resultClass, $relationshipType, $table, $column];
    }

    /**
     * @inheritdoc
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * @return ModelSchemesInterface
     */
    protected function getModelSchemes()
    {
        return $this->modelSchemes;
    }

    /**
     * @return T
     */
    protected function getTranslator()
    {
        return $this->translator;
    }

    /**
     * @return FilterOperationsInterface
     */
    protected function getFilterOperations()
    {
        return $this->filterOperations;
    }

    /**
     * @param string $class
     *
     * @return string
     */
    protected function getTableName($class)
    {
        $tableName = $this->getModelSchemes()->getTable($class);

        return $tableName;
    }

    /**
     * @param string $class
     *
     * @return string
     */
    protected function getPrimaryKeyName($class)
    {
        $primaryKey = $this->getModelSchemes()->getPrimaryKey($class);

        return $primaryKey;
    }

    /**
     * @param string $class
     *
     * @return array
     */
    protected function getColumns($class)
    {
        $table   = $this->getModelSchemes()->getTable($class);
        $columns = $this->getModelSchemes()->getAttributes($class);
        $result  = [];
        foreach ($columns as $column) {
            $result[] = $this->getColumn($class, $table, $column);
        }

        return $result;
    }

    /**
     * @param string $class
     * @param string $table
     * @param string $column
     *
     * @return string
     */
    protected function getColumn($class, $table, $column)
    {
        $class ?: null; // suppress unused

        return "`$table`.`$column`";
    }

    /** @noinspection PhpTooManyParametersInspection
     * @param ErrorCollection          $errors
     * @param QueryBuilder             $builder
     * @param CompositeExpression      $link
     * @param FilterParameterInterface $filterParam
     * @param string                   $table
     * @param string                   $field
     * @param string                   $operation
     * @param array|string|null        $params
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function applyFilterToQuery(
        ErrorCollection $errors,
        QueryBuilder $builder,
        CompositeExpression $link,
        FilterParameterInterface $filterParam,
        $table,
        $field,
        $operation,
        $params = null
    ) {
        switch ($operation) {
            case '=':
            case 'eq':
            case 'equals':
                $this->getFilterOperations()
                    ->applyEquals($builder, $link, $errors, $table, $field, $params);
                break;
            case '!=':
            case 'neq':
            case 'not-equals':
                $this->getFilterOperations()
                    ->applyNotEquals($builder, $link, $errors, $table, $field, $params);
                break;
            case '<':
            case 'lt':
            case 'less-than':
                $this->getFilterOperations()
                    ->applyLessThan($builder, $link, $errors, $table, $field, $params);
                break;
            case '<=':
            case 'lte':
            case 'less-or-equals':
                $this->getFilterOperations()
                    ->applyLessOrEquals($builder, $link, $errors, $table, $field, $params);
                break;
            case '>':
            case 'gt':
            case 'greater-than':
                $this->getFilterOperations()
                    ->applyGreaterThan($builder, $link, $errors, $table, $field, $params);
                break;
            case '>=':
            case 'gte':
            case 'greater-or-equals':
                $this->getFilterOperations()
                    ->applyGreaterOrEquals($builder, $link, $errors, $table, $field, $params);
                break;
            case 'like':
                $this->getFilterOperations()
                    ->applyLike($builder, $link, $errors, $table, $field, $params);
                break;
            case 'not-like':
                $this->getFilterOperations()
                    ->applyNotLike($builder, $link, $errors, $table, $field, $params);
                break;
            case 'in':
                $this->getFilterOperations()
                    ->applyIn($builder, $link, $errors, $table, $field, (array)$params);
                break;
            case 'not-in':
                $this->getFilterOperations()
                    ->applyNotIn($builder, $link, $errors, $table, $field, (array)$params);
                break;
            case self::FILTER_OP_IS_NULL:
                $this->getFilterOperations()->applyIsNull($builder, $link, $table, $field);
                break;
            case self::FILTER_OP_IS_NOT_NULL:
                $this->getFilterOperations()->applyIsNotNull($builder, $link, $table, $field);
                break;
            default:
                $errMsg = $this->getTranslator()->get(T::MSG_ERR_INVALID_OPERATION);
                $errors->addQueryParameterError($filterParam->getOriginalName(), $errMsg, $operation);
                break;
        }
    }

    /**
     * @param QueryBuilder $builder
     * @param string       $modelClass
     *
     * @return QueryBuilder
     */
    protected function distinct(QueryBuilder $builder, $modelClass)
    {
        // emulate SELECT DISTINCT (group by primary key)
        $primaryColumn     = $this->getModelSchemes()->getPrimaryKey($modelClass);
        $fullPrimaryColumn = $this->getColumn($modelClass, $this->getTableName($modelClass), $primaryColumn);
        $builder->addGroupBy($fullPrimaryColumn);

        return $builder;
    }

    /**
     * @param QueryBuilder $builder
     * @param string       $table
     * @param string       $column
     * @param string       $bindName
     */
    private function addWhereBind(QueryBuilder $builder, $table, $column, $bindName)
    {
        $builder
            ->andWhere($this->buildTableColumn($table, $column) . '=' . $bindName);
    }

    /**
     * @param string $table
     * @param string $column
     *
     * @return string
     */
    private function buildTableColumn($table, $column)
    {
        return "`$table`.`$column`";
    }

    /**
     * @param string $modelClass
     * @param string $relationshipName
     *
     * @return array
     */
    private function createBelongsToBuilder($modelClass, $relationshipName)
    {
        $oneClass      = $this->getModelSchemes()->getReverseModelClass($modelClass, $relationshipName);
        $oneTable      = $this->getTableName($oneClass);
        $onePrimaryKey = $this->getPrimaryKeyName($oneClass);
        $table         = $this->getTableName($modelClass);
        $foreignKey    = $this->getModelSchemes()->getForeignKey($modelClass, $relationshipName);

        $builder = $this->getConnection()->createQueryBuilder();

        $joinCondition = $this->buildTableColumn($table, $foreignKey) . '=' .
            $this->buildTableColumn($oneTable, $onePrimaryKey);
        $builder
            ->select($this->getColumns($oneClass))
            ->from($oneTable)
            ->innerJoin($oneTable, $table, null, $joinCondition);

        return [$builder, $oneClass, $this->getTableName($modelClass), $this->getPrimaryKeyName($modelClass)];
    }

    /**
     * @param string $modelClass
     * @param string $relationshipName
     *
     * @return array
     */
    private function createHasManyBuilder($modelClass, $relationshipName)
    {
        list ($reverseClass, $reverseName) = $this->getModelSchemes()
            ->getReverseRelationship($modelClass, $relationshipName);
        $reverseTable = $this->getModelSchemes()->getTable($reverseClass);
        $foreignKey   = $this->getModelSchemes()->getForeignKey($reverseClass, $reverseName);
        $builder      = $this->getConnection()->createQueryBuilder();
        $builder
            ->select($this->getColumns($reverseClass))
            ->from($reverseTable);

        return [$builder, $reverseClass, $reverseTable, $foreignKey];
    }

    /**
     * @param string $modelClass
     * @param string $relationshipName
     *
     * @return array
     */
    private function createBelongsToManyBuilder($modelClass, $relationshipName)
    {
        list ($intermediateTable, $foreignKey, $reverseForeignKey) =
            $this->getModelSchemes()->getBelongsToManyRelationship($modelClass, $relationshipName);
        $reverseClass = $this->getModelSchemes()->getReverseModelClass($modelClass, $relationshipName);
        $reverseTable = $this->getModelSchemes()->getTable($reverseClass);
        $reversePk    = $this->getModelSchemes()->getPrimaryKey($reverseClass);

        $joinCondition = $this->buildTableColumn($reverseTable, $reversePk) . '=' .
            $this->buildTableColumn($intermediateTable, $reverseForeignKey);
        $builder       = $this->getConnection()->createQueryBuilder();
        $builder
            ->select($this->getColumns($reverseClass))
            ->from($reverseTable)
            ->innerJoin($reverseTable, $intermediateTable, null, $joinCondition);

        return [$builder, $reverseClass, $intermediateTable, $foreignKey];
    }
}
