<?php namespace Limoncello\JsonApi\Builders;

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
use Generator;
use Limoncello\JsonApi\Contracts\I18n\TranslatorInterface as T;
use Limoncello\JsonApi\Contracts\QueryBuilderInterface;
use Limoncello\JsonApi\Exceptions\LogicException;

/**
 * @package Limoncello\JsonApi
 */
class QueryBuilder implements QueryBuilderInterface
{
    /** SQL constant */
    const NAME_QUOTE = '`';

    /** SQL constant */
    const SEPARATOR = ';';

    /** Query mode */
    const MODE_NONE = 0;

    /** Query mode */
    const MODE_INSERT = 1;

    /** Query mode */
    const MODE_SELECT = 2;

    /** Query mode */
    const MODE_UPDATE = 3;

    /** Query mode */
    const MODE_DELETE = 4;

    /**
     * @var T
     */
    private $translator;

    /**
     * @var int
     */
    private $queryMode;

    /**
     * @var null|string
     */
    private $tableName;

    /**
     * @var null|Generator
     */
    private $columns;

    /**
     * @var null|Generator
     */
    private $values;

    /**
     * @var null|Generator
     */
    private $joins;

    /**
     * @var null|Generator[]
     */
    private $wheres;

    /**
     * @var null|Generator
     */
    private $sorts;

    /**
     * @var null|int
     */
    private $limit;

    /**
     * @var null|int
     */
    private $offset;

    /**
     * @param T $translator
     */
    public function __construct(T $translator)
    {
        $this->translator = $translator;

        $this->reset();
    }

    /**
     * @inheritdoc
     */
    public function forTable($name)
    {
        $this->tableName = $name;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function insert(Generator $columns, Generator $values)
    {
        $this->queryMode = self::MODE_INSERT;

        $this->columns = $columns;
        $this->values  = $values;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function select(Generator $columns)
    {
        $this->queryMode = self::MODE_SELECT;

        $this->columns = $columns;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function update(Generator $columns)
    {
        $this->queryMode = self::MODE_UPDATE;

        $this->columns = $columns;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function delete()
    {
        $this->queryMode = self::MODE_DELETE;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function join(Generator $joins)
    {
        $this->joins = $joins;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function where(Generator $wheres)
    {
        $this->wheres[] = $wheres;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function sort(Generator $sorts)
    {
        $this->sorts = $sorts;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function limit($limit, $offset = 0)
    {
        $this->limit  = $limit;
        $this->offset = $offset;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function get()
    {
        switch ($this->queryMode) {
            case self::MODE_INSERT:
                $queryAndParameters = $this->getInsert();
                break;
            case self::MODE_SELECT:
                $queryAndParameters = $this->getSelect();
                break;
            case self::MODE_UPDATE:
                $queryAndParameters = $this->getUpdate();
                break;
            case self::MODE_DELETE:
                $queryAndParameters = $this->getDelete();
                break;
            case self::MODE_NONE:
            default:
                throw new LogicException($this->trans(T::MSG_ERR_QUERY_IS_NOT_CONFIGURED));
        }

        $this->reset();

        return $queryAndParameters;
    }

    /**
     * @inheritdoc
     */
    public function reset()
    {
        $this->tableName = null;
        $this->columns   = null;
        $this->values    = null;
        $this->joins     = null;
        $this->wheres    = null;
        $this->sorts     = null;
        $this->limit     = null;
        $this->offset    = null;
        $this->queryMode = self::MODE_NONE;
    }

    /**
     * @inheritdoc
     */
    public function implode(array $queries)
    {
        $query = implode(self::SEPARATOR, $queries);

        return $query;
    }

    /**
     * @param string $table
     * @param string $columnList
     *
     * @return string
     */
    protected function createSelectClause($table, $columnList)
    {
        return "SELECT $columnList FROM $table";
    }

    /**
     * @param string $table
     * @param string $columnList
     * @param string $valuePlaceholders
     *
     * @return string
     */
    protected function createInsertClause($table, $columnList, $valuePlaceholders)
    {
        return  "INSERT INTO `$table` ($columnList) VALUES $valuePlaceholders";
    }

    /**
     * @param string $table
     * @param string $valuePairs
     *
     * @return string
     */
    protected function createUpdateClause($table, $valuePairs)
    {
        return  "UPDATE `$table` SET $valuePairs";
    }

    /**
     * @param string $table
     *
     * @return string
     */
    protected function createDeleteClause($table)
    {
        return  "DELETE FROM `$table`";
    }

    /**
     * @param string $column
     * @param bool   $isAsc
     *
     * @return string
     */
    protected function createSortClause($column, $isAsc)
    {
        return $isAsc === true ? "`$column` ASC" : "`$column` DESC";
    }

    /**
     * @param string $table
     * @param string $column
     * @param string $operation
     * @param string $placeholder
     *
     * @return string
     */
    protected function createWhereClause($table, $column, $operation, $placeholder = '?')
    {
        return "`$table`.`$column` $operation $placeholder";
    }

    /**
     * @param string $table
     * @param string $column
     * @param string $placeholders
     *
     * @return string
     */
    protected function createWhereInClause($table, $column, $placeholders)
    {
        return "`$table`.`$column` IN ($placeholders)";
    }

    /**
     * @param string $clauses
     *
     * @return string
     */
    protected function createWhere($clauses)
    {
        return " WHERE $clauses";
    }

    /**
     * @param string $clauses
     *
     * @return string
     */
    protected function createOrderBy($clauses)
    {
        return " ORDER BY $clauses";
    }

    /**
     * @param string $table
     * @param string $column
     * @param string $joinTable
     * @param string $joinColumn
     *
     * @return string
     */
    protected function createJoinLinksClause($table, $column, $joinTable, $joinColumn)
    {
        return "`$table`.`$column`=`$joinTable`.`$joinColumn`";
    }

    /**
     * @param string $joinTable
     * @param string $joinLink
     *
     * @return string
     */
    protected function createJoinClause($joinTable, $joinLink)
    {
        return "JOIN $joinTable ON $joinLink";
    }

    /**
     * @return string
     */
    protected function createLimitClause()
    {
        return "LIMIT ? OFFSET ?";
    }

    /**
     * @param string $messageId
     *
     * @return string
     */
    protected function trans($messageId)
    {
        return $this->translator->get($messageId);
    }

    /**
     * @param string $prefix
     * @param string $postfix
     *
     * @return Closure
     */
    protected function getWrapClosure($prefix, $postfix)
    {
        return function ($value) use ($prefix, $postfix) {
            return $prefix . $value . $postfix;
        };
    }

    /**
     * @param string $value
     *
     * @return string
     */
    protected function sqlQuoteValue($value)
    {
        return static::NAME_QUOTE . $value . static::NAME_QUOTE;
    }

    /**
     * @param Generator $values
     * @param string    $glue
     *
     * @return null|string
     */
    protected function implodeValues(Generator $values, $glue = ',')
    {
        $result = null;
        if ($values->valid() === true) {
            $result = $values->current();
            $values->next();
            while ($values->valid() === true) {
                $result .= $glue . $values->current();
                $values->next();
            }
        }

        return $result;
    }

    /**
     * @param Generator $values
     * @param Closure   $formatter
     * @param string    $glue
     *
     * @return null|string
     */
    protected function implodeFormattedValues(Generator $values, Closure $formatter, $glue = ',')
    {
        $result = null;
        if ($values->valid() === true) {
            $result = $formatter($values->current());
            $values->next();
            while ($values->valid() === true) {
                $result .= $glue . $formatter($values->current());
                $values->next();
            }
        }

        return $result;
    }

    /**
     * @return array
     */
    private function getInsert()
    {
        $parameters   = [];
        $getRowValues = function () use (&$parameters) {
            $rowPlaceholders = null;
            foreach ($this->values as $value) {
                if ($rowPlaceholders !== null && $value === null) {
                    yield $rowPlaceholders;
                    $rowPlaceholders = null;
                    continue;
                }
                $rowPlaceholders = $rowPlaceholders === null ? '?' : $rowPlaceholders . ',?';
                $parameters[]    = $value;
            }

            if ($rowPlaceholders !== null) {
                yield $rowPlaceholders;
            }
        };

        $valuePlaceholders = $this->implodeFormattedValues($getRowValues(), $this->getWrapClosure('(', ')'));

        $insert = $this->createInsertClause($this->getTable(), $this->getColumnsWithoutTable(), $valuePlaceholders);

        return [$insert, $parameters];
    }

    /**
     * @return array
     */
    private function getSelect()
    {
        $select = $this->createSelectClause($this->getFromClause(), $this->getColumns());

        $parameters = null;
        if ($this->wheres !== null) {
            list ($clause, $parameters) = $this->getWhereClause();
            $clause === null ?: $select .= $this->createWhere($clause);
        }

        if ($this->sorts !== null && ($clause = $this->getSortClause()) !== null) {
            $select .= $this->createOrderBy($clause);
        }

        if ($this->limit !== null && $this->offset !== null) {
            list ($clause, $limitParameters) = $this->getLimitClause();
            $select    .= ' ' . $clause;
            $parameters = empty($parameters) === true ? $limitParameters : array_merge($parameters, $limitParameters);
        }

        return [$select, $parameters];
    }

    /**
     * @return array
     */
    private function getUpdate()
    {
        $parameters = [];
        $getUpdatePairs = function () use (&$parameters) {
            foreach ($this->columns as $column => $value) {
                $column       = $this->sqlQuoteValue($column);
                $parameters[] = $value;
                yield $column . '=?';
            }
        };

        $pairs  = $this->implodeValues($getUpdatePairs());
        $update = $this->createUpdateClause($this->getTable(), $pairs);

        if ($this->wheres !== null) {
            list ($clause, $whereParams) = $this->getWhereClause();
            $update    .= $this->createWhere($clause);
            $parameters = array_merge($parameters, $whereParams);
        }

        return [$update, $parameters];
    }

    /**
     * @return array
     */
    private function getDelete()
    {
        $delete = $this->createDeleteClause($this->getTable());

        $parameters = null;
        if ($this->wheres !== null) {
            list ($clause, $parameters) = $this->getWhereClause();
            $delete .= $this->createWhere($clause);
        }

        return [$delete, $parameters];
    }

    /**
     * @return string
     */
    private function getTable()
    {
        if ($this->tableName === null) {
            throw new LogicException($this->trans(T::MSG_ERR_QUERY_IS_NOT_CONFIGURED));
        }

        return $this->tableName;
    }

    /**
     * @return null|string
     */
    private function getColumns()
    {
        $getColumns = function () {
            foreach ($this->columns as $tableName => $columnOrRawSqlExpr) {
                if ($tableName !== null) {
                    yield $this->sqlQuoteValue($tableName) . '.' . $this->sqlQuoteValue($columnOrRawSqlExpr);
                } else {
                    yield $columnOrRawSqlExpr;
                }
            }
        };

        $columns = $this->implodeValues($getColumns());

        return $columns;
    }

    /**
     * @return null|string
     */
    private function getColumnsWithoutTable()
    {
        $getColumns = function () {
            foreach ($this->columns as $column) {
                yield $this->sqlQuoteValue($column);
            }
        };

        $columns = $this->implodeValues($getColumns());

        return $columns;
    }

    /**
     * @return string
     */
    private function getFromClause()
    {
        $clause = $this->sqlQuoteValue($this->getTable());

        if ($this->joins !== null) {
            $getJoin = function () {
                foreach ($this->joins as list ($table, $column, $joinTable, $joinColumn)) {
                    $quotedTable   = $this->sqlQuoteValue($joinTable);
                    $linkCondition = $this->createJoinLinksClause($table, $column, $joinTable, $joinColumn);
                    yield $this->createJoinClause($quotedTable, $linkCondition);
                }
            };
            $join       = $getJoin();
            $joinClause = $this->implodeValues($join, ' ');

            $clause .= ' ' . $joinClause;
        }

        return $clause;
    }

    /**
     * @return array
     */
    private function getWhereClause()
    {
        $parameters = [];
        $getWheres  = function () use (&$parameters) {
            foreach ($this->yieldFromGenerators($this->wheres) as list($table, $column, $operation, $parameter)) {
                switch ($operation) {
                    case static::SQL_IN:
                        $inParams        = [];
                        $getPlaceholders = function () use ($parameter, &$inParams) {
                            foreach ($parameter as $index) {
                                $inParams[] = $index;
                                yield '?';
                            }
                        };
                        $placeholders = $this->implodeValues($getPlaceholders());
                        yield $this->createWhereInClause($table, $column, $placeholders);
                        $parameters = array_merge($parameters, $inParams);
                        break;
                    case static::SQL_IS:
                        if ($parameter === null) {
                            // it can only be `IS NULL` (for non null it will be `=...`)
                            yield $this->createWhereClause($table, $column, $operation, static::SQL_NULL);
                        }
                        break;
                    case static::SQL_IS_NOT:
                        if ($parameter === null) {
                            // it can only be `NOT NULL` (for non null it will be `<>...`)
                            yield $this->createWhereClause($table, $column, $operation, static::SQL_NULL);
                        }
                        break;
                    default:
                        yield $this->createWhereClause($table, $column, $operation);
                        $parameters[] = $parameter;
                        break;
                }
            }
        };

        $clause = $this->implodeValues($getWheres(), ' AND ');

        return [$clause, $parameters];
    }

    /**
     * @return null|string
     */
    private function getSortClause()
    {
        $getSorts = function () {
            foreach ($this->sorts as $column => $isAsc) {
                yield $this->createSortClause($column, $isAsc);
            }
        };

        $clause = $this->implodeValues($getSorts());

        return $clause;
    }

    /**
     * @param Generator[] $generators
     *
     * @return Generator
     */
    private function yieldFromGenerators(array $generators = null)
    {
        if ($generators !== null) {
            foreach ($generators as $generator) {
                /** @var Generator $generator */
                foreach ($generator as $operation) {
                    yield $operation;
                }
            }
        }
    }

    /**
     * @return array
     */
    private function getLimitClause()
    {
        $clause = $this->createLimitClause();

        return [$clause, [$this->limit, $this->offset]];
    }
}
