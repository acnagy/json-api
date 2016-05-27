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

use ArrayObject;
use Closure;
use Generator;
use Limoncello\JsonApi\Contracts\Adapters\FilterOperationsInterface;
use Limoncello\JsonApi\Contracts\Adapters\PaginationStrategyInterface;
use Limoncello\JsonApi\Contracts\Adapters\RepositoryInterface;
use Limoncello\JsonApi\Contracts\FactoryInterface;
use Limoncello\JsonApi\Contracts\I18n\TranslatorInterface as T;
use Limoncello\JsonApi\Contracts\QueryBuilderInterface;
use Limoncello\Models\Contracts\ModelStorageInterface;
use Limoncello\Models\Contracts\RelationshipStorageInterface;
use Limoncello\Models\Contracts\SchemaStorageInterface;
use Limoncello\Models\Contracts\TagStorageInterface;
use Limoncello\Models\RelationshipTypes;
use Neomerx\JsonApi\Contracts\Document\DocumentInterface;
use Neomerx\JsonApi\Exceptions\ErrorCollection;
use PDO;
use PDOStatement;

/**
 * @package Limoncello\JsonApi
 */
class Repository implements RepositoryInterface
{
    /** Path constant */
    const ROOT_PATH      = '';

    /** Path constant */
    const PATH_SEPARATOR = DocumentInterface::PATH_SEPARATOR;

    /**
     * @var PDO
     */
    private $pdo;

    /**
     * @var string
     */
    private $class;

    /**
     * @var FactoryInterface
     */
    private $factory;

    /**
     * @var SchemaStorageInterface
     */
    private $schemaStorage;

    /**
     * @var QueryBuilderInterface
     */
    private $builder;

    /**
     * @var PaginationStrategyInterface
     */
    private $relationshipPaging;

    /**
     * @var FilterOperationsInterface
     */
    private $filterOperations;

    /**
     * @var T
     */
    private $translator;

    /**
     * @var bool
     */
    private $isExecuteOneByOne = true;

    /**
     * @var ErrorCollection
     */
    private $errors;

    /**
     * @param FactoryInterface            $factory
     * @param string                      $class
     * @param PDO                         $pdo
     * @param SchemaStorageInterface      $schemaStorage
     * @param QueryBuilderInterface       $builder
     * @param FilterOperationsInterface   $filterOperations
     * @param PaginationStrategyInterface $relationshipPaging
     * @param T                           $translator
     * @param bool                        $isExecuteOnByOne
     */
    public function __construct(
        FactoryInterface $factory,
        $class,
        PDO $pdo,
        SchemaStorageInterface $schemaStorage,
        QueryBuilderInterface $builder,
        FilterOperationsInterface $filterOperations,
        PaginationStrategyInterface $relationshipPaging,
        T $translator,
        $isExecuteOnByOne = true
    ) {
        $this->pdo                = $pdo;
        $this->class              = $class;
        $this->factory            = $factory;
        $this->schemaStorage      = $schemaStorage;
        $this->builder            = $builder;
        $this->relationshipPaging = $relationshipPaging;
        $this->filterOperations   = $filterOperations;
        $this->translator         = $translator;
        $this->isExecuteOneByOne  = $isExecuteOnByOne;

        $this->resetErrors();
    }

    /**
     * @inheritdoc
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @inheritdoc
     */
    public function instance($identity = null)
    {
        $class = $this->getClass();
        $model = new $class;

        $identity === null ?: $this->setPrimaryKey($model, $identity);

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function setAttribute($model, $name, $value)
    {
        $model->{$name} = $value;
    }

    /**
     * @inheritdoc
     */
    public function getAttribute($model, $name)
    {
        $value = $model->{$name};

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function hasAttribute($model, $name)
    {
        $hasAttribute = isset($model->{$name});

        return $hasAttribute;
    }

    /**
     * @inheritdoc
     */
    public function inTransaction(Closure $closure)
    {
        $pdo = $this->getPdo();
        $pdo->beginTransaction();
        try {
            $isOk = ($closure() === false ? null : true);
        } finally {
            isset($isOk) === true ? $pdo->commit() : $pdo->rollBack();
        }
    }

    /**
     * @inheritdoc
     */
    public function index(array $filterParams = null, array $sortParams = null, array $pagingParams = null)
    {
        $this->resetErrors();

        $tableName = $this->getTableName();
        $builder   = $this->getBuilder()
            ->forTable($tableName)
            ->select($this->getColumns($this->getClass()));

        $this->applyFiltersToQuery($tableName, $filterParams);
        $this->applySortingToQuery($sortParams);

        list ($offset, $limit) = $this->getRelationshipPaging()->parseParameters($pagingParams);
        $builder->limit($limit, $offset);

        list ($query, $params) = $builder->get();

        $result = null;
        if ($this->getErrors()->count() <= 0) {
            $models = $this->queryMultiple($this->getClass(), $query, $params);
            if ($models !== false) {
                $hasMore = false;
                if ($limit !== null && $offset !== true) {
                    list($models, $hasMore, $limit, $offset) = $this->normalizePagingParams($models, $limit, $offset);
                }
                $result = $this->getFactory()->createPaginatedData($models, true, $hasMore, $offset, $limit);
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function read($index)
    {
        $table = $this->getTableName();

        list($query, $params) = $this->getBuilder()
            ->forTable($table)
            ->select($this->getColumns($this->getClass()))
            ->where($this->singleWhere($table, $this->getPrimaryKeyName(), '=', $index))
            ->get();

        $model = $this->querySingle($this->getClass(), $query, $params);

        return $model === false ? null : $model;
    }

    /**
     * @inheritdoc
     */
    public function readRelationships(array $models, array $paths)
    {
        $this->resetErrors();

        $result = $this->getFactory()->createRelationshipStorage();

        if (empty($models) === false && empty($paths) === false) {
            $modelStorage = $this->getFactory()->createModelStorage($this->getSchemaStorage());
            $modelsAtPath = $this->getFactory()->createTagStorage();

            // we gonna send this storage via function params so it is an equivalent for &array
            $classAtPath = new ArrayObject();

            foreach ($models as $model) {
                $uniqueModel = $modelStorage->register($model);
                $modelsAtPath->register($uniqueModel, [self::ROOT_PATH]);
            }
            $classAtPath[self::ROOT_PATH] = get_class($models[0]);

            foreach ($this->getPaths($paths) as list ($parentPath, $childPaths)) {
                $this->uploadResources($result, $modelsAtPath, $classAtPath, $modelStorage, $parentPath, $childPaths);
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function readRelationship(
        $index,
        $relationshipName,
        array $filterParams = null,
        array $sortParams = null,
        array $pagingParams = null
    ) {
        $this->resetErrors();

        if ($this->getSchemaStorage()->hasRelationship($this->getClass(), $relationshipName) === false) {
            $msg = $this->getTranslator()->get(T::MSG_ERR_INVALID_ELEMENT);
            $this->getErrors()->addRelationshipError($relationshipName, $msg);
            return null;
        }

        $offset = null;
        $limit  = null;
        list ($reverseClass, $reverseName) = $this->getSchemaStorage()
            ->getReverseRelationship($this->getClass(), $relationshipName);
        $tableName = $this->getSchemaStorage()->getTable($reverseClass);
        $relationshipType = $this->getSchemaStorage()->getRelationshipType($this->getClass(), $relationshipName);
        switch ($relationshipType) {
            case RelationshipTypes::BELONGS_TO:
                $indexClosure = function () use ($index) {
                    yield $index;
                };
                $this->buildBelongsToQuery($this->getClass(), $indexClosure(), $relationshipName);
                break;
            case RelationshipTypes::HAS_MANY:
                list ($offset, $limit) = $this->getRelationshipPaging()->parseParameters($pagingParams);
                $this->buildHasManyQuery($reverseClass, $index, $reverseName, $offset, $limit);
                break;
            case RelationshipTypes::BELONGS_TO_MANY:
                list ($offset, $limit) = $this->getRelationshipPaging()->parseParameters($pagingParams);
                $this->buildBelongsToManyQuery($this->getClass(), $index, $relationshipName, $offset, $limit);
                break;
        }

        $this->applyFiltersToQuery($tableName, $filterParams);
        $this->applySortingToQuery($sortParams);

        list ($query, $params) = $this->getBuilder()->get();

        $result = null;
        if ($this->getErrors()->count() <= 0) {
            $models = $this->queryMultiple($reverseClass, $query, $params);
            if ($models !== false) {
                $hasMore = false;
                if ($limit !== null && $offset !== true) {
                    list($models, $hasMore, $limit, $offset) = $this->normalizePagingParams($models, $limit, $offset);
                }
                $result = $this->getFactory()->createPaginatedData($models, true, $hasMore, $offset, $limit);
            }
        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function delete($index)
    {
        $table = $this->getTableName();
        list($query, $params) = $this->getBuilder()
            ->forTable($table)
            ->delete()
            ->where($this->singleWhere($table, $this->getPrimaryKeyName(), '=', $index))
            ->get();
        $this->execute($query, $params);
    }

    /**
     * @inheritdoc
     */
    public function create($model)
    {
        $columns = [];
        $values  = [];
        foreach ($this->getSchemaStorage()->getAttributes($this->getClass()) as $name) {
            if ($this->hasAttribute($model, $name) === true) {
                $value = $this->getAttribute($model, $name);
                $columns[] = $name;
                $values[]  = $value;
            }
        }

        $get = function (array $values) {
            foreach ($values as $value) {
                yield $value;
            }
        };

        $table = $this->getTableName();
        list($query, $params) = $this->getBuilder()
            ->forTable($table)
            ->insert($get($columns), $get($values))
            ->get();

        $this->execute($query, $params);
        $index = $this->getPdo()->lastInsertId();

        return $index;
    }

    /**
     * @inheritdoc
     */
    public function update($model, $original)
    {
        $getChanges = function () use ($original, $model) {
            foreach ($this->getSchemaStorage()->getAttributes($this->getClass()) as $column) {
                $originalValue = $this->getAttribute($original, $column);
                $currentValue  = $this->getAttribute($model, $column);
                if ($originalValue !== $currentValue) {
                    yield $column => $currentValue;
                }
            }
        };

        $index = $this->getId($model);
        $table = $this->getTableName();
        list($query, $params) = $this->getBuilder()
            ->forTable($table)
            ->update($getChanges())
            ->where($this->singleWhere($table, $this->getPrimaryKeyName(), '=', $index))
            ->get();

        $this->execute($query, $params);
    }

    /**
     * @inheritdoc
     */
    public function setToOneRelationship($model, $name, $value)
    {
        $key = $this->getSchemaStorage()->getForeignKey($this->getClass(), $name);
        $this->setAttribute($model, $key, $value);
    }

    /**
     * @inheritdoc
     */
    public function saveToManyRelationship($index, $name, array $values)
    {
        list ($table, $foreignKey, $reverseForeignKey) =
            $this->getSchemaStorage()->getBelongsToManyRelationship($this->getClass(), $name);
        $reversIds = function () use ($index, $values) {
            foreach ($values as $reverseIndex) {
                yield $index;
                yield $reverseIndex;
                yield null;
            }
        };
        $columns = function () use ($foreignKey, $reverseForeignKey) {
            yield $foreignKey;
            yield $reverseForeignKey;
        };

        list ($query, $params) = $this->getBuilder()
            ->forTable($table)
            ->insert($columns(), $reversIds())
            ->get();

        $this->execute($query, $params);
    }

    /**
     * @inheritdoc
     */
    public function cleanToManyRelationship($index, $name)
    {
        list ($table, $foreignKey) = $this->getSchemaStorage()->getBelongsToManyRelationship($this->getClass(), $name);

        list($query, $params) = $this->getBuilder()
            ->forTable($table)
            ->delete()
            ->where($this->singleWhere($table, $foreignKey, '=', $index))
            ->get();

        $this->execute($query, $params);
    }

    /**
     * @return PDO
     */
    protected function getPdo()
    {
        return $this->pdo;
    }

    /**
     * @return FactoryInterface
     */
    protected function getFactory()
    {
        return $this->factory;
    }

    /**
     * @return SchemaStorageInterface
     */
    protected function getSchemaStorage()
    {
        return $this->schemaStorage;
    }

    /**
     * @return QueryBuilderInterface
     */
    protected function getBuilder()
    {
        return $this->builder;
    }

    /**
     * @return PaginationStrategyInterface
     */
    protected function getRelationshipPaging()
    {
        return $this->relationshipPaging;
    }

    /**
     * @return T
     */
    protected function getTranslator()
    {
        return $this->translator;
    }

    /**
     * @inheritdoc
     */
    protected function getClass()
    {
        return $this->class;
    }

    /**
     * @return FilterOperationsInterface
     */
    protected function getFilterOperations()
    {
        return $this->filterOperations;
    }

    /**
     * @return bool
     */
    protected function isExecuteOneByOne()
    {
        return $this->isExecuteOneByOne;
    }

    /**
     * @param mixed $model
     *
     * @return string
     */
    protected function getId($model)
    {
        $pkName = $this->getSchemaStorage()->getPrimaryKey($this->getClass());
        $key    = $this->getAttribute($model, $pkName);

        return $key;
    }

    /**
     * @param mixed      $model
     * @param string|int $value
     */
    protected function setPrimaryKey($model, $value)
    {
        $pkName = $this->getSchemaStorage()->getPrimaryKey($this->getClass());
        $this->setAttribute($model, $pkName, $value);
    }

    /**
     * @return string
     */
    protected function getTableName()
    {
        $tableName = $this->getSchemaStorage()->getTable($this->getClass());

        return $tableName;
    }

    /**
     * @return string
     */
    protected function getPrimaryKeyName()
    {
        $primaryKey = $this->getSchemaStorage()->getPrimaryKey($this->getClass());

        return $primaryKey;
    }

    /**
     * @param string $class
     *
     * @return Generator
     */
    protected function getColumns($class)
    {
        $table = $this->getSchemaStorage()->getTable($class);
        foreach ($this->getSchemaStorage()->getAttributes($class) as $column) {
            yield $table => $column;
        }
    }

    /**
     * @param array $paths
     *
     * @return Generator
     */
    protected function getPaths(array $paths)
    {
        // The idea is to normalize paths. It means build all intermediate paths.
        // e.g. if only `a.b.c` path it given it will be normalized to `a`, `a.b` and `a.b.c`.
        // Path depths store depth of each path (e.g. 0 for root, 1 for `a`, 2 for `a.b` and etc).
        // It is needed for yielding them in correct order (from top level to bottom).
        $normalizedPaths = [];
        $pathsDepths     = [];
        foreach ($paths as $path) {
            $parentDepth = 0;
            $tmpPath     = self::ROOT_PATH;
            foreach (explode(self::PATH_SEPARATOR, $path) as $pathPiece) {
                $parent                    = $tmpPath;
                $tmpPath                   = empty($tmpPath) === true ?
                    $pathPiece : $tmpPath . self::PATH_SEPARATOR . $pathPiece;
                $normalizedPaths[$tmpPath] = [$parent, $pathPiece];
                $pathsDepths[$parent]      = $parentDepth++;
            }
        }

        // Here we collect paths in form of parent => [list of children]
        // e.g. '' => ['a', 'c', 'b'], 'b' => ['bb', 'aa'] and etc
        $parentWithChildren = [];
        foreach ($normalizedPaths as $path => list ($parent, $childPath)) {
            $parentWithChildren[$parent][] = $childPath;
        }

        // And finally sort by path depth and yield parent with its children. Top level paths first then deeper ones.
        asort($pathsDepths, SORT_NUMERIC);
        foreach ($pathsDepths as $parent => $depth) {
            $childPaths = $parentWithChildren[$parent];
            yield [$parent, $childPaths];
        }
    }

    /**
     * @param RelationshipStorageInterface $relationshipStorage
     * @param TagStorageInterface          $modelsAtPath
     * @param ArrayObject                  $classAtPath
     * @param ModelStorageInterface        $modelStorage
     * @param string                       $parentsPath
     * @param array                        $childRelationships
     *
     * @return void
     */
    protected function uploadResources(
        RelationshipStorageInterface $relationshipStorage,
        TagStorageInterface $modelsAtPath,
        ArrayObject $classAtPath,
        ModelStorageInterface $modelStorage,
        $parentsPath,
        array $childRelationships
    ) {
        $handlers = [];
        $queries  = [];
        $params   = [];
        $parentsClass = $classAtPath[$parentsPath];
        foreach ($childRelationships as $name) {
            if ($this->getSchemaStorage()->hasRelationship($parentsClass, $name) === false) {
                $msg = $this->getTranslator()->get(T::MSG_ERR_INVALID_ELEMENT);
                $this->getErrors()->addRelationshipError($parentsPath . static::PATH_SEPARATOR . $name, $msg);
                continue;
            }
            $relationshipType = $this->getSchemaStorage()->getRelationshipType($parentsClass, $name);
            switch ($relationshipType) {
                case RelationshipTypes::BELONGS_TO:
                    list ($subQuery, $subParams, $subHandler) = $this->createBelongsToQueriesAndHandlers(
                        $relationshipStorage,
                        $modelsAtPath,
                        $classAtPath,
                        $modelStorage,
                        $parentsPath,
                        $name
                    );
                    $queries[]  = $subQuery;
                    $params[]   = $subParams;
                    $handlers[] = $subHandler;
                    break;
                case RelationshipTypes::HAS_MANY:
                    $rootClass = $classAtPath[self::ROOT_PATH];
                    list($offset, $limit) = $this->getRelationshipPaging()
                        ->getParameters($rootClass, $parentsClass, $parentsPath, $name);
                    list ($subQueries, $subParams, $subHandlers) = $this->createHasManyQueriesAndHandlers(
                        $relationshipStorage,
                        $modelsAtPath,
                        $classAtPath,
                        $modelStorage,
                        $parentsPath,
                        $parentsClass,
                        $name,
                        $offset,
                        $limit
                    );
                    $queries  = array_merge($queries, $subQueries);
                    $params   = array_merge($params, $subParams);
                    $handlers = array_merge($handlers, $subHandlers);
                    break;
                case RelationshipTypes::BELONGS_TO_MANY:
                    $rootClass = $classAtPath[self::ROOT_PATH];
                    list($offset, $limit) = $this->getRelationshipPaging()
                        ->getParameters($rootClass, $parentsClass, $parentsPath, $name);
                    list ($subQueries, $subParams, $subHandlers) = $this->createBelongsToManyQueriesAndHandlers(
                        $relationshipStorage,
                        $modelsAtPath,
                        $classAtPath,
                        $modelStorage,
                        $parentsPath,
                        $parentsClass,
                        $name,
                        $offset,
                        $limit
                    );
                    $queries  = array_merge($queries, $subQueries);
                    $params   = array_merge($params, $subParams);
                    $handlers = array_merge($handlers, $subHandlers);
                    break;
            }
        }

        $this->isExecuteOneByOne() === true ?
            $this->execOneByOne($queries, $params, $handlers) : $this->execAllAtOnce($queries, $params, $handlers);
    }

    /**
     * @param RelationshipStorageInterface $relationshipStorage
     * @param TagStorageInterface          $modelsAtPath
     * @param ArrayObject                  $classAtPath
     * @param ModelStorageInterface        $modelStorage
     * @param                              $manyPath
     * @param                              $manyToOneRel
     *
     * @return array
     */
    protected function createBelongsToQueriesAndHandlers(
        RelationshipStorageInterface $relationshipStorage,
        TagStorageInterface $modelsAtPath,
        ArrayObject $classAtPath,
        ModelStorageInterface $modelStorage,
        $manyPath,
        $manyToOneRel
    ) {
        // terms `one` and `many` refer to one <-> many database relationship type

        $many           = $modelsAtPath->get($manyPath);
        $manyClass      = $classAtPath[$manyPath];
        $manyFk         = $this->getSchemaStorage()->getForeignKey($manyClass, $manyToOneRel);
        list($oneClass) = $this->getSchemaStorage()->getReverseRelationship($manyClass, $manyToOneRel);

        $onePath = empty($manyPath) === true ? $manyToOneRel : $manyPath . self::PATH_SEPARATOR . $manyToOneRel;

        $getId = function () use ($many, $manyFk) {
            foreach ($many as $item) {
                yield $item->{$manyFk};
            }
        };

        $this->buildBelongsToQuery($manyClass, $getId(), $manyToOneRel);
        list ($query, $params) = $this->getBuilder()->get();

        $handler = function (PDOStatement $statement) use (
            $relationshipStorage,
            $modelStorage,
            $modelsAtPath,
            $classAtPath,
            $many,
            $manyToOneRel,
            $manyFk,
            $oneClass,
            $onePath
        ) {
            while (($oneModel = $statement->fetchObject($oneClass)) !== false) {
                $modelsAtPath->register($modelStorage->register($oneModel), [$onePath]);
            }
            $classAtPath[$onePath] = $oneClass;

            foreach ($many as $item) {
                $oneId    = $item->{$manyFk};
                $oneModel = $modelStorage->get($oneClass, $oneId);
                $relationshipStorage->addToOneRelationship($item, $manyToOneRel, $oneModel);
            }
        };

        return [$query, $params, $handler];
    }

    /**
     * @param string    $className
     * @param Generator $indexes
     * @param string    $relationshipName
     *
     * @return void
     */
    protected function buildBelongsToQuery($className, Generator $indexes, $relationshipName)
    {
        list($oneClass) = $this->getSchemaStorage()->getReverseRelationship($className, $relationshipName);
        $oneTable       = $this->getSchemaStorage()->getTable($oneClass);
        $onePk          = $this->getSchemaStorage()->getPrimaryKey($oneClass);

        $this->getBuilder()
            ->forTable($oneTable)
            ->select($this->getColumns($oneClass))
            ->where($this->singleWhere($oneTable, $onePk, 'IN', $indexes));
    }

    /** @noinspection PhpTooManyParametersInspection
     * @param RelationshipStorageInterface $relationshipStorage
     * @param TagStorageInterface          $modelsAtPath
     * @param ArrayObject                  $classAtPath
     * @param ModelStorageInterface        $modelStorage
     * @param string                       $oneIndex
     * @param string                       $onePath
     * @param string                       $oneToManyRel
     * @param int|string|null              $offset
     * @param int|string|null              $limit
     *
     * @return Closure
     */
    protected function createHasManyHandler(
        RelationshipStorageInterface $relationshipStorage,
        TagStorageInterface $modelsAtPath,
        ArrayObject $classAtPath,
        ModelStorageInterface $modelStorage,
        $oneIndex,
        $onePath,
        $oneToManyRel,
        $offset,
        $limit
    ) {
        // terms `one` and `many` refer to one <-> many database relationship type

        return function (PDOStatement $statement) use (
            $relationshipStorage,
            $modelStorage,
            $modelsAtPath,
            $classAtPath,
            $oneIndex,
            $onePath,
            $oneToManyRel,
            $offset,
            $limit
        ) {
            $oneClass        = $classAtPath[$onePath];
            $one             = $modelStorage->get($oneClass, $oneIndex);
            list($manyClass) = $this->getSchemaStorage()->getReverseRelationship($oneClass, $oneToManyRel);

            $manyPath = empty($onePath) === true ? $oneToManyRel : $onePath . self::PATH_SEPARATOR . $oneToManyRel;

            $manyModels = [];
            while (($manyModel = $statement->fetchObject($manyClass)) !== false) {
                $manyModel    = $modelStorage->register($manyModel);
                $manyModels[] = $manyModel;
            }
            $classAtPath[$manyPath] = $manyClass;
            list($manyModels, $hasMore, $limit, $offset) = $this->normalizePagingParams($manyModels, $limit, $offset);
            $relationshipStorage
                ->addToManyRelationship($one, $oneToManyRel, $manyModels, $hasMore, $offset, $limit);
            foreach ($manyModels as $manyModel) {
                $modelsAtPath->register($manyModel, [$manyPath]);
            }
        };
    }

    /** @noinspection PhpTooManyParametersInspection
     * @param RelationshipStorageInterface $relationshipStorage
     * @param TagStorageInterface          $modelsAtPath
     * @param ArrayObject                  $classAtPath
     * @param ModelStorageInterface        $modelStorage
     * @param string                       $onePath
     * @param string                       $oneClass
     * @param string                       $oneToManyRel
     * @param int|string|null              $offset
     * @param int|string|null              $limit
     *
     * @return array
     */
    protected function createHasManyQueriesAndHandlers(
        RelationshipStorageInterface $relationshipStorage,
        TagStorageInterface $modelsAtPath,
        ArrayObject $classAtPath,
        ModelStorageInterface $modelStorage,
        $onePath,
        $oneClass,
        $oneToManyRel,
        $offset,
        $limit
    ) {
        // terms `one` and `many` refer to one <-> many database relationship type
        list ($manyClass, $manyToOneRel) = $this->getSchemaStorage()->getReverseRelationship($oneClass, $oneToManyRel);
        $onePk = $this->getSchemaStorage()->getPrimaryKey($oneClass);
        $ones  = $modelsAtPath->get($onePath);

        $queries    = [];
        $handlers   = [];
        $parameters = [];
        foreach ($ones as $one) {
            $oneIndex  = $one->{$onePk};

            // TODO optimize. All queries are the same. Only params differ. Prepare and then bound params.
            $this->buildHasManyQuery($manyClass, $oneIndex, $manyToOneRel, $offset, $limit);
            list($query, $params) = $this->getBuilder()->get();

            $queries[]    = $query;
            $parameters[] = $params;
            $handlers[]   = $this->createHasManyHandler(
                $relationshipStorage,
                $modelsAtPath,
                $classAtPath,
                $modelStorage,
                $oneIndex,
                $onePath,
                $oneToManyRel,
                $offset,
                $limit
            );
        }

        return [$queries, $parameters, $handlers];
    }

    /**
     * @param string          $className
     * @param string|int      $index
     * @param string          $relationshipName
     * @param int|string|null $offset
     * @param int|string|null $limit
     */
    protected function buildHasManyQuery($className, $index, $relationshipName, $offset, $limit)
    {
        $tableName  = $this->getSchemaStorage()->getTable($className);
        $foreignKey = $this->getSchemaStorage()->getForeignKey($className, $relationshipName);

        $this->getBuilder()
            ->forTable($tableName)
            ->select($this->getColumns($className))
            ->where($this->singleWhere($tableName, $foreignKey, '=', $index))
            ->limit($limit, $offset);
    }

    /** @noinspection PhpTooManyParametersInspection
     * @param RelationshipStorageInterface $relationshipStorage
     * @param TagStorageInterface          $modelsAtPath
     * @param ArrayObject                  $classAtPath
     * @param ModelStorageInterface        $modelStorage
     * @param string                       $primaryPath
     * @param string                       $primaryClass
     * @param string                       $primaryToReverseRel
     * @param int|string|null              $offset
     * @param int|string|null              $limit
     *
     * @return array
     */
    protected function createBelongsToManyQueriesAndHandlers(
        RelationshipStorageInterface $relationshipStorage,
        TagStorageInterface $modelsAtPath,
        ArrayObject $classAtPath,
        ModelStorageInterface $modelStorage,
        $primaryPath,
        $primaryClass,
        $primaryToReverseRel,
        $offset,
        $limit
    ) {
        list ($reverseClass) = $this->getSchemaStorage()->getReverseRelationship($primaryClass, $primaryToReverseRel);
        $primaryKey   = $this->getSchemaStorage()->getPrimaryKey($primaryClass);

        $queries    = [];
        $parameters = [];
        $handlers   = [];
        $primaryModels = $modelsAtPath->get($primaryPath);
        foreach ($primaryModels as $primaryModel) {
            $index  = $primaryModel->{$primaryKey};
            $this->buildBelongsToManyQuery($primaryClass, $index, $primaryToReverseRel, $offset, $limit);
            list ($query, $params) = $this->getBuilder()->get();

            $queries[]    = $query;
            $parameters[] = $params;
            $handlers[]   = $this->createBelongsManyHandler(
                $relationshipStorage,
                $modelsAtPath,
                $classAtPath,
                $modelStorage,
                $primaryModel,
                $primaryPath,
                $primaryToReverseRel,
                $reverseClass,
                $offset,
                $limit
            );
        }

        return [$queries, $parameters, $handlers];
    }

    /**
     * @param string     $className
     * @param string|int $index
     * @param string     $relationshipName
     * @param string|int $offset
     * @param string|int $limit
     */
    protected function buildBelongsToManyQuery($className, $index, $relationshipName, $offset, $limit)
    {
        list ($intermediateTable, $foreignKey, $reverseForeignKey) =
            $this->getSchemaStorage()->getBelongsToManyRelationship($className, $relationshipName);
        list ($reverseClass) = $this->getSchemaStorage()->getReverseRelationship($className, $relationshipName);
        $reverseTable = $this->getSchemaStorage()->getTable($reverseClass);
        $reversePk    = $this->getSchemaStorage()->getPrimaryKey($reverseClass);

        $get = function (array $value) {
            yield $value;
        };

        $this->getBuilder()
            ->forTable($reverseTable)
            ->select($this->getColumns($reverseClass))
            ->join($get([$reverseTable, $reversePk, $intermediateTable, $reverseForeignKey]))
            ->where($this->singleWhere($intermediateTable, $foreignKey, '=', $index))
            ->limit($limit, $offset);
    }

    /** @noinspection PhpTooManyParametersInspection
     * @param RelationshipStorageInterface $relationshipStorage
     * @param TagStorageInterface          $modelsAtPath
     * @param ArrayObject                  $classAtPath
     * @param ModelStorageInterface        $modelStorage
     * @param mixed                        $primaryModel
     * @param string                       $primaryPath
     * @param string                       $primaryToReverseRel
     * @param string                       $reverseClass
     * @param int|string|null              $offset
     * @param int|string|null              $limit
     *
     * @return Closure
     */
    protected function createBelongsManyHandler(
        RelationshipStorageInterface $relationshipStorage,
        TagStorageInterface $modelsAtPath,
        ArrayObject $classAtPath,
        ModelStorageInterface $modelStorage,
        $primaryModel,
        $primaryPath,
        $primaryToReverseRel,
        $reverseClass,
        $offset,
        $limit
    ) {
        return function (PDOStatement $statement) use (
            $relationshipStorage,
            $modelsAtPath,
            $classAtPath,
            $modelStorage,
            $primaryModel,
            $primaryPath,
            $primaryToReverseRel,
            $reverseClass,
            $offset,
            $limit
        ) {
            $reversePath = empty($primaryPath) === true ? $primaryToReverseRel :
                $primaryPath . self::PATH_SEPARATOR . $primaryToReverseRel;

            $reverseModels = [];
            while (($reverseModel = $statement->fetchObject($reverseClass)) !== false) {
                $reverseModel    = $modelStorage->register($reverseModel);
                $reverseModels[] = $reverseModel;
            }
            $classAtPath[$reversePath] = $reverseClass;

            list($reverseModels, $hasMore, $limit, $offset) =
                $this->normalizePagingParams($reverseModels, $limit, $offset);
            $relationshipStorage
                ->addToManyRelationship($primaryModel, $primaryToReverseRel, $reverseModels, $hasMore, $offset, $limit);
            foreach ($reverseModels as $reverseModel) {
                $modelsAtPath->register($reverseModel, [$reversePath]);
            }
        };
    }

    /**
     * @param string     $tableName
     * @param array|null $filterParams
     */
    protected function applyFiltersToQuery($tableName, array $filterParams = null)
    {
        if ($filterParams !== null) {
            $wheres = function () use ($filterParams, $tableName) {
                $ops = $this->getFilterOperations();
                foreach ($filterParams as $field => $value) {
                    if (is_string($value) === true || is_int($value) === true) {
                        foreach ($ops->getDefaultOperation($tableName, $field, [$value]) as $operation) {
                            yield $operation;
                        }
                    } elseif (is_array($value) === true) {
                        foreach ($value as $operation => $params) {
                            $normalizedParams = is_array($params) === true ? $params : [$params];
                            if ($ops->hasOperation($operation) === false) {
                                $msg = $this->getTranslator()->get(T::MSG_ERR_INVALID_FIELD);
                                $this->getErrors()->addQueryParameterError($operation, $msg);
                                continue;
                            }
                            $operations = $ops->getOperations($operation, $tableName, $field, $normalizedParams);
                            foreach ($operations as $operation) {
                                yield $operation;
                            }
                        }
                    }
                }
            };
            $this->getBuilder()->where($wheres());
        }
    }

    /**
     * @param array|null $sortParams
     */
    protected function applySortingToQuery(array $sortParams = null)
    {
        if ($sortParams !== null) {
            // TODO either replace input with Generator or get rid of generators in favour of arrays (other places too)
            $getOrderByPairs = function () use ($sortParams) {
                foreach ($sortParams as $column => $isAscending) {
                    yield $column => $isAscending;
                }
            };
            $this->getBuilder()->sort($getOrderByPairs());
        }
    }

    /**
     * @param string $table
     * @param string $column
     * @param string $operation
     * @param string $value
     *
     * @return Generator
     */
    protected function singleWhere($table, $column, $operation, $value)
    {
        yield [$table, $column, $operation, $value];
    }

    /**
     * @param string     $class
     * @param string     $query
     * @param array|null $parameters
     *
     * @return mixed
     */
    protected function querySingle($class, $query, array $parameters = null)
    {
        $statement = $this->getPdo()->prepare($query, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]);
        $statement->execute($parameters);
        /** @noinspection PhpMethodParametersCountMismatchInspection */
        $statement->setFetchMode(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, $class);

        $result = $statement->fetch();

        $statement->closeCursor();

        return $result;
    }

    /**
     * @param string     $class
     * @param string     $query
     * @param array|null $parameters
     *
     * @return array|false
     */
    protected function queryMultiple($class, $query, array $parameters = null)
    {
        // without this line PDO fails to assign parameters in LIMIT and OFFSET
        $this->getPdo()->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $statement = $this->getPdo()->prepare($query, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]);
        $statement->execute($parameters);
        /** @noinspection PhpMethodParametersCountMismatchInspection */
        $statement->setFetchMode(PDO::FETCH_CLASS|PDO::FETCH_PROPS_LATE, $class);

        $result = $statement->fetchAll();

        $statement->closeCursor();

        return $result;
    }

    /**
     * @param string     $query
     * @param array|null $parameters
     */
    protected function execute($query, array $parameters = null)
    {
        // without this line PDO fails to assign parameters in LIMIT and OFFSET
        $this->getPdo()->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $this->getPdo()->prepare($query)->execute($parameters);
    }

    /**
     * @param string[]  $queries
     * @param array     $parameters
     * @param Closure[] $handlers
     *
     * @return void
     */
    protected function execOneByOne(array $queries, array $parameters, array $handlers)
    {
        $counter = count($queries);
        assert($counter . '===' . count($handlers), 'Size of queries and handlers must match');

        for ($number = 0; $number < $counter; ++$number) {
            $query   = $queries[$number];
            $params  = $parameters[$number];
            $handler = $handlers[$number];

            // without this line PDO fails to assign parameters in LIMIT and OFFSET
            $this->getPdo()->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            $statement = $this->getPdo()->prepare($query, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]);
            $statement->execute($params);
            $handler($statement);

            $statement->closeCursor();
            unset($statement);
        }
    }

    /**
     * @param string[]  $queries
     * @param array     $parameters
     * @param Closure[] $handlers
     *
     * @return void
     */
    protected function execAllAtOnce(array $queries, array $parameters, array $handlers)
    {
        // TODO exec all at once still don't work with MySQL. It looks it's better to remove it.

        $counter = count($queries);
        assert($counter . '===' . count($handlers), 'Size of queries and handlers must match');

        $query            = $this->getBuilder()->implode($queries);
        $mergedParameters = [];
        foreach ($parameters as $parameter) {
            $mergedParameters = array_merge($mergedParameters, $parameter);
        }

//        $this->getPdo()->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $statement = $this->getPdo()->prepare($query, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]);
        $statement->execute($mergedParameters);

        do {
            /** @var Closure $curHandler */
            $curHandler = current($handlers);
            $curHandler($statement);
        } while ($statement->nextRowset() === true && next($handlers) !== false);

        $statement->closeCursor();
        unset($statement);
    }

    /**
     * @param array           $models
     * @param int|string|null $offset
     * @param int|string|null $limit
     *
     * @return array
     */
    private function normalizePagingParams(array $models, $limit, $offset)
    {
        $hasMore = count($models) >= $limit;
        $limit   = $hasMore === true ? $limit - 1 : null;
        $offset  = $limit === null && $hasMore === false ? null : $offset;
        $hasMore === false ?: array_pop($models);

        return [$models, $hasMore, $limit, $offset];
    }

    /**
     * @return void
     */
    private function resetErrors()
    {
        $this->errors = $this->factory->createErrorCollection();
    }
}
