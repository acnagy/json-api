<?php namespace Limoncello\JsonApi\Api;

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
use Doctrine\DBAL\Driver\PDOConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Type;
use Generator;
use Limoncello\JsonApi\Contracts\Adapters\PaginationStrategyInterface;
use Limoncello\JsonApi\Contracts\Adapters\RepositoryInterface;
use Limoncello\JsonApi\Contracts\Api\CrudInterface;
use Limoncello\JsonApi\Contracts\FactoryInterface;
use Limoncello\JsonApi\Contracts\Http\Query\IncludeParameterInterface;
use Limoncello\JsonApi\Contracts\Models\ModelSchemesInterface;
use Limoncello\JsonApi\Contracts\Models\ModelStorageInterface;
use Limoncello\JsonApi\Contracts\Models\PaginatedDataInterface;
use Limoncello\JsonApi\Contracts\Models\RelationshipStorageInterface;
use Limoncello\JsonApi\Contracts\Models\TagStorageInterface;
use Limoncello\JsonApi\Http\Query\FilterParameterCollection;
use Limoncello\JsonApi\Models\RelationshipTypes;
use Neomerx\JsonApi\Contracts\Document\DocumentInterface;
use Neomerx\JsonApi\Exceptions\ErrorCollection;
use Neomerx\JsonApi\Exceptions\JsonApiException as E;

/**
 * @package Limoncello\JsonApi
 *
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Crud implements CrudInterface
{
    /** Internal constant. Query param name. */
    protected static $indexBind = ':index';

    /** Internal constant. Path constant. */
    protected static $rootPath = '';

    /** Internal constant. Path constant. */
    protected static $pathSeparator = DocumentInterface::PATH_SEPARATOR;

    /**
     * @var FactoryInterface
     */
    private $factory;

    /**
     * @var string
     */
    private $modelClass;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var ModelSchemesInterface
     */
    private $modelSchemes;

    /**
     * @var PaginationStrategyInterface
     */
    private $paginationStrategy;

    /**
     * @param FactoryInterface            $factory
     * @param string                      $modelClass
     * @param RepositoryInterface         $repository
     * @param ModelSchemesInterface       $modelSchemes
     * @param PaginationStrategyInterface $paginationStrategy
     */
    public function __construct(
        FactoryInterface $factory,
        $modelClass,
        RepositoryInterface $repository,
        ModelSchemesInterface $modelSchemes,
        PaginationStrategyInterface $paginationStrategy
    ) {
        $this->factory            = $factory;
        $this->modelClass         = $modelClass;
        $this->repository         = $repository;
        $this->modelSchemes       = $modelSchemes;
        $this->paginationStrategy = $paginationStrategy;
    }

    /**
     * @inheritdoc
     */
    public function index(
        FilterParameterCollection $filterParams = null,
        array $sortParams = null,
        array $includePaths = null,
        array $pagingParams = null
    ) {
        $modelClass = $this->getModelClass();

        $builder = $this->getRepository()->index($modelClass);

        $errors = $this->getFactory()->createErrorCollection();
        $filterParams === null ?: $this->getRepository()->applyFilters($errors, $builder, $modelClass, $filterParams);
        $this->checkErrors($errors);
        $sortParams === null ?: $this->getRepository()->applySorting($builder, $modelClass, $sortParams);

        list($offset, $limit) = $this->getPaginationStrategy()->parseParameters($pagingParams);
        $builder->setFirstResult($offset)->setMaxResults($limit);

        $data = $this->fetchCollectionData($this->builderOnIndex($builder), $modelClass, $limit, $offset);

        $relationships = null;
        if ($data->getData() !== null && $includePaths !== null) {
            $relationships = $this->readRelationships($data, $includePaths);
        }

        $result = $this->getFactory()->createModelsData($data, $relationships);

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function count(FilterParameterCollection $filterParams = null)
    {
        $modelClass = $this->getModelClass();

        $builder = $this->getRepository()->count($modelClass);

        $errors = $this->getFactory()->createErrorCollection();
        $filterParams === null ?: $this->getRepository()->applyFilters($errors, $builder, $modelClass, $filterParams);
        $this->checkErrors($errors);

        $result = $this->builderOnCount($builder)->execute()->fetchColumn();

        return $result === false ? null : $result;
    }

    /**
     * @inheritdoc
     */
    public function read($index, FilterParameterCollection $filterParams = null, array $includePaths = null)
    {
        $modelClass = $this->getModelClass();

        $builder = $this->getRepository()
            ->read($modelClass, static::$indexBind)
            ->setParameter(static::$indexBind, $index);

        $errors = $this->getFactory()->createErrorCollection();
        $filterParams === null ?: $this->getRepository()->applyFilters($errors, $builder, $modelClass, $filterParams);
        $this->checkErrors($errors);

        $data = $this->fetchSingleData($this->builderOnRead($builder), $modelClass);

        $relationships = null;
        if ($data->getData() !== null && $includePaths !== null) {
            $relationships = $this->readRelationships($data, $includePaths);
        }

        $result = $this->getFactory()->createModelsData($data, $relationships);

        return $result;
    }

    /**
     * @inheritdoc
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function readRelationship(
        $index,
        $name,
        FilterParameterCollection $filterParams = null,
        array $sortParams = null,
        array $pagingParams = null
    ) {
        $modelClass = $this->getModelClass();

        /** @var QueryBuilder $builder */
        list ($builder, $resultClass, $relationshipType) =
            $this->getRepository()->readRelationship($modelClass, static::$indexBind, $name);

        $errors = $this->getFactory()->createErrorCollection();
        $filterParams === null ?: $this->getRepository()->applyFilters($errors, $builder, $resultClass, $filterParams);
        $this->checkErrors($errors);
        $sortParams === null ?: $this->getRepository()->applySorting($builder, $resultClass, $sortParams);

        $builder->setParameter(static::$indexBind, $index);

        $isCollection = $relationshipType === RelationshipTypes::HAS_MANY ||
            $relationshipType === RelationshipTypes::BELONGS_TO_MANY;

        if ($isCollection == true) {
            list($offset, $limit) = $this->getPaginationStrategy()->parseParameters($pagingParams);
            $builder->setFirstResult($offset)->setMaxResults($limit);
            $data = $this
                ->fetchCollectionData($this->builderOnReadRelationship($builder), $resultClass, $limit, $offset);
        } else {
            $data = $this->fetchSingleData($this->builderOnReadRelationship($builder), $resultClass);
        }

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function readRow($index)
    {
        $modelClass = $this->getModelClass();
        $builder    = $this->getRepository()
            ->read($modelClass, static::$indexBind)
            ->setParameter(static::$indexBind, $index);
        $typedRow   = $this->fetchRow($builder, $modelClass);

        return $typedRow;
    }

    /**
     * @inheritdoc
     */
    public function delete($index)
    {
        $modelClass = $this->getModelClass();

        $builder = $this->builderOnDelete($this->getRepository()->delete($modelClass, $index));

        $deleted = $builder->execute();

        return (int)$deleted;
    }

    /**
     * @inheritdoc
     */
    public function create(array $attributes, array $toMany = [])
    {
        $modelClass = $this->getModelClass();

        $allowedChanges = $this->filterAttributesOnCreate($modelClass, $attributes);

        $index    = null;
        $saveMain = $this->getRepository()->create($modelClass, $allowedChanges);
        $saveMain = $this->builderSaveResourceOnCreate($saveMain);
        $saveMain->getSQL(); // prepare
        $this->inTransaction(function () use ($modelClass, $saveMain, $toMany, &$index) {
            $saveMain->execute();
            $index = $saveMain->getConnection()->lastInsertId();
            foreach ($toMany as $name => $values) {
                $indexBind      = ':index';
                $otherIndexBind = ':otherIndex';
                $saveToMany     = $this->getRepository()
                    ->createToManyRelationship($modelClass, $indexBind, $name, $otherIndexBind);
                $saveToMany     = $this->builderSaveRelationshipOnCreate($name, $saveToMany);
                $saveToMany->setParameter($indexBind, $index);
                foreach ($values as $value) {
                    $saveToMany->setParameter($otherIndexBind, $value)->execute();
                }
            }
        });

        return $index;
    }

    /**
     * @inheritdoc
     */
    public function update($index, array $attributes, array $toMany = [])
    {
        $updated    = 0;
        $modelClass = $this->getModelClass();

        $allowedChanges = $this->filterAttributesOnUpdate($modelClass, $attributes);

        $saveMain = $this->getRepository()->update($modelClass, $index, $allowedChanges);
        $saveMain = $this->builderSaveResourceOnUpdate($saveMain);
        $saveMain->getSQL(); // prepare
        $this->inTransaction(function () use ($modelClass, $saveMain, $toMany, $index, &$updated) {
            $updated = $saveMain->execute();
            foreach ($toMany as $name => $values) {
                $indexBind      = ':index';
                $otherIndexBind = ':otherIndex';

                $cleanToMany = $this->getRepository()->cleanToManyRelationship($modelClass, $indexBind, $name);
                $cleanToMany = $this->builderCleanRelationshipOnUpdate($name, $cleanToMany);
                $cleanToMany->setParameter($indexBind, $index)->execute();

                $saveToMany = $this->getRepository()
                    ->createToManyRelationship($modelClass, $indexBind, $name, $otherIndexBind);
                $saveToMany = $this->builderSaveRelationshipOnUpdate($name, $saveToMany);
                $saveToMany->setParameter($indexBind, $index);
                foreach ($values as $value) {
                    $updated += (int)$saveToMany->setParameter($otherIndexBind, $value)->execute();
                }
            }
        });

        return (int)$updated;
    }

    /**
     * @return FactoryInterface
     */
    protected function getFactory()
    {
        return $this->factory;
    }

    /**
     * @return string
     */
    protected function getModelClass()
    {
        return $this->modelClass;
    }

    /**
     * @return RepositoryInterface
     */
    protected function getRepository()
    {
        return $this->repository;
    }

    /**
     * @return ModelSchemesInterface
     */
    protected function getModelSchemes()
    {
        return $this->modelSchemes;
    }

    /**
     * @return PaginationStrategyInterface
     */
    protected function getPaginationStrategy()
    {
        return $this->paginationStrategy;
    }

    /**
     * @param Closure $closure
     *
     * @return void
     */
    protected function inTransaction(Closure $closure)
    {
        $connection = $this->getRepository()->getConnection();
        $connection->beginTransaction();
        try {
            $isOk = ($closure() === false ? null : true);
        } finally {
            isset($isOk) === true ? $connection->commit() : $connection->rollBack();
        }
    }

    /**
     * @param QueryBuilder $builder
     * @param string       $class
     *
     * @return PaginatedDataInterface
     */
    protected function fetchSingleData(QueryBuilder $builder, $class)
    {
        $model = $this->fetchSingle($builder, $class);
        $data  = $this->getFactory()->createPaginatedData($model);

        return $data;
    }

    /**
     * @param QueryBuilder $builder
     * @param string       $class
     * @param int          $limit
     * @param int          $offset
     *
     * @return PaginatedDataInterface
     */
    protected function fetchCollectionData(QueryBuilder $builder, $class, $limit, $offset)
    {
        list($models, $hasMore, $limit, $offset) = $this->fetchCollection($builder, $class, $limit, $offset);

        $data = $this->getFactory()
            ->createPaginatedData($models)
            ->setIsCollection(true)
            ->setHasMoreItems($hasMore)
            ->setOffset($offset)
            ->setLimit($limit);

        return $data;
    }

    /**
     * @param QueryBuilder $builder
     * @param string       $class
     *
     * @return array|null
     */
    protected function fetchRow(QueryBuilder $builder, $class)
    {
        $statement = $builder->execute();
        $statement->setFetchMode(PDOConnection::FETCH_ASSOC);
        $platform = $builder->getConnection()->getDatabasePlatform();
        $types    = $this->getModelSchemes()->getAttributeTypeInstances($class);

        $model = null;
        if (($attributes = $statement->fetch()) !== false) {
            $model = $this->readRowFromAssoc($attributes, $types, $platform);
        }

        return $model;
    }

    /**
     * @param QueryBuilder $builder
     * @param string       $class
     *
     * @return mixed|null
     */
    protected function fetchSingle(QueryBuilder $builder, $class)
    {
        $statement = $builder->execute();
        $statement->setFetchMode(PDOConnection::FETCH_ASSOC);
        $platform = $builder->getConnection()->getDatabasePlatform();
        $types    = $this->getModelSchemes()->getAttributeTypeInstances($class);

        $model = null;
        if (($attributes = $statement->fetch()) !== false) {
            $model = $this->readInstanceFromAssoc($class, $attributes, $types, $platform);
        }

        return $model;
    }

    /**
     * @param QueryBuilder $builder
     * @param string       $class
     * @param int          $limit
     * @param int          $offset
     *
     * @return array
     */
    protected function fetchCollection(QueryBuilder $builder, $class, $limit, $offset)
    {
        $statement = $builder->execute();
        $statement->setFetchMode(PDOConnection::FETCH_ASSOC);
        $platform = $builder->getConnection()->getDatabasePlatform();
        $types    = $this->getModelSchemes()->getAttributeTypeInstances($class);

        $models = [];
        while (($attributes = $statement->fetch()) !== false) {
            $models[] = $this->readInstanceFromAssoc($class, $attributes, $types, $platform);
        }

        return $this->normalizePagingParams($models, $limit, $offset);
    }

    /**
     * @param string $modelClass
     * @param array  $attributes
     *
     * @return array
     */
    protected function filterAttributesOnCreate($modelClass, array $attributes)
    {
        $allowedAttributes = array_flip($this->getModelSchemes()->getAttributes($modelClass));
        $allowedChanges    = array_intersect_key($attributes, $allowedAttributes);

        return $allowedChanges;
    }

    /**
     * @param string $modelClass
     * @param array  $attributes
     *
     * @return array
     */
    protected function filterAttributesOnUpdate($modelClass, array $attributes)
    {
        $allowedAttributes = array_flip($this->getModelSchemes()->getAttributes($modelClass));
        $allowedChanges    = array_intersect_key($attributes, $allowedAttributes);

        return $allowedChanges;
    }

    /**
     * @param QueryBuilder $builder
     *
     * @return QueryBuilder
     */
    protected function builderOnCount(QueryBuilder $builder)
    {
        return $builder;
    }

    /**
     * @param QueryBuilder $builder
     *
     * @return QueryBuilder
     */
    protected function builderOnIndex(QueryBuilder $builder)
    {
        return $builder;
    }

    /**
     * @param QueryBuilder $builder
     *
     * @return QueryBuilder
     */
    protected function builderOnRead(QueryBuilder $builder)
    {
        return $builder;
    }

    /**
     * @param QueryBuilder $builder
     *
     * @return QueryBuilder
     */
    protected function builderOnReadRelationship(QueryBuilder $builder)
    {
        return $builder;
    }

    /**
     * @param QueryBuilder $builder
     *
     * @return QueryBuilder
     */
    protected function builderSaveResourceOnCreate(QueryBuilder $builder)
    {
        return $builder;
    }

    /**
     * @param QueryBuilder $builder
     *
     * @return QueryBuilder
     */
    protected function builderSaveResourceOnUpdate(QueryBuilder $builder)
    {
        return $builder;
    }

    /**
     * @param string       $relationshipName
     * @param QueryBuilder $builder
     *
     * @return QueryBuilder
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function builderSaveRelationshipOnCreate(/** @noinspection PhpUnusedParameterInspection */
        $relationshipName,
        QueryBuilder $builder
    ) {
        return $builder;
    }

    /**
     * @param string       $relationshipName
     * @param QueryBuilder $builder
     *
     * @return QueryBuilder
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function builderSaveRelationshipOnUpdate(/** @noinspection PhpUnusedParameterInspection */
        $relationshipName,
        QueryBuilder $builder
    ) {
        return $builder;
    }

    /**
     * @param string       $relationshipName
     * @param QueryBuilder $builder
     *
     * @return QueryBuilder
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function builderCleanRelationshipOnUpdate(/** @noinspection PhpUnusedParameterInspection */
        $relationshipName,
        QueryBuilder $builder
    ) {
        return $builder;
    }

    /**
     * @param QueryBuilder $builder
     *
     * @return QueryBuilder
     */
    protected function builderOnDelete(QueryBuilder $builder)
    {
        return $builder;
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
     * @param ErrorCollection $errors
     *
     * @return void
     */
    private function checkErrors(ErrorCollection $errors)
    {
        if (empty($errors->getArrayCopy()) === false) {
            throw new E($errors);
        }
    }

    /**
     * @param PaginatedDataInterface      $data
     * @param IncludeParameterInterface[] $paths
     *
     * @return RelationshipStorageInterface
     *
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    private function readRelationships(PaginatedDataInterface $data, array $paths)
    {
        $result = $this->getFactory()->createRelationshipStorage();

        if (empty($data->getData()) === false && empty($paths) === false) {
            $modelStorage = $this->getFactory()->createModelStorage($this->getModelSchemes());
            $modelsAtPath = $this->getFactory()->createTagStorage();

            // we gonna send this storage via function params so it is an equivalent for &array
            $classAtPath = new ArrayObject();

            $model = null;
            if ($data->isCollection() === true) {
                foreach ($data->getData() as $model) {
                    $uniqueModel = $modelStorage->register($model);
                    if ($uniqueModel !== null) {
                        $modelsAtPath->register($uniqueModel, static::$rootPath);
                    }
                }
            } else {
                $model       = $data->getData();
                $uniqueModel = $modelStorage->register($model);
                if ($uniqueModel !== null) {
                    $modelsAtPath->register($uniqueModel, static::$rootPath);
                }
            }
            $classAtPath[static::$rootPath] = get_class($model);

            foreach ($this->getPaths($paths) as list ($parentPath, $childPaths)) {
                $this->loadRelationshipsLayer(
                    $result,
                    $modelsAtPath,
                    $classAtPath,
                    $modelStorage,
                    $parentPath,
                    $childPaths
                );
            }
        }

        return $result;
    }

    /**
     * @param IncludeParameterInterface[] $paths
     *
     * @return Generator
     */
    private function getPaths(array $paths)
    {
        // The idea is to normalize paths. It means build all intermediate paths.
        // e.g. if only `a.b.c` path it given it will be normalized to `a`, `a.b` and `a.b.c`.
        // Path depths store depth of each path (e.g. 0 for root, 1 for `a`, 2 for `a.b` and etc).
        // It is needed for yielding them in correct order (from top level to bottom).
        $normalizedPaths = [];
        $pathsDepths     = [];
        foreach ($paths as $path) {
            $parentDepth = 0;
            $tmpPath     = static::$rootPath;
            foreach ($path->getPath() as $pathPiece) {
                $parent                    = $tmpPath;
                $tmpPath                   = empty($tmpPath) === true ?
                    $pathPiece : $tmpPath . static::$pathSeparator . $pathPiece;
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
            $depth ?: null; // suppress unused
            $childPaths = $parentWithChildren[$parent];
            yield [$parent, $childPaths];
        }
    }

    /**
     * @param RelationshipStorageInterface $result
     * @param TagStorageInterface          $modelsAtPath
     * @param ArrayObject                  $classAtPath
     * @param ModelStorageInterface        $deDup
     * @param string                       $parentsPath
     * @param array                        $childRelationships
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function loadRelationshipsLayer(
        RelationshipStorageInterface $result,
        TagStorageInterface $modelsAtPath,
        ArrayObject $classAtPath,
        ModelStorageInterface $deDup,
        $parentsPath,
        array $childRelationships
    ) {
        $rootClass   = $classAtPath[static::$rootPath];
        $parentClass = $classAtPath[$parentsPath];
        $parents     = $modelsAtPath->get($parentsPath);

        // What should we do? We have do find all child resources for $parents at paths $childRelationships (1 level
        // child paths) and add them to $relationships. While doing it we have to deduplicate resources with
        // $models.

        foreach ($childRelationships as $name) {
            $childrenPath = $parentsPath !== static::$rootPath ? $parentsPath . static::$pathSeparator . $name : $name;

            /** @var QueryBuilder $builder */
            list ($builder, $class, $relationshipType) =
                $this->getRepository()->readRelationship($parentClass, static::$indexBind, $name);

            $classAtPath[$childrenPath] = $class;

            switch ($relationshipType) {
                case RelationshipTypes::BELONGS_TO:
                    $pkName = $this->getModelSchemes()->getPrimaryKey($parentClass);
                    foreach ($parents as $parent) {
                        $builder->setParameter(static::$indexBind, $parent->{$pkName});
                        $child = $deDup->register($this->fetchSingle($builder, $class));
                        if ($child !== null) {
                            $modelsAtPath->register($child, $childrenPath);
                        }
                        $result->addToOneRelationship($parent, $name, $child);
                    }
                    break;
                case RelationshipTypes::HAS_MANY:
                case RelationshipTypes::BELONGS_TO_MANY:
                    list ($queryOffset, $queryLimit) = $this->getPaginationStrategy()
                        ->getParameters($rootClass, $parentClass, $parentsPath, $name);
                    $builder->setFirstResult($queryOffset)->setMaxResults($queryLimit);
                    $pkName = $this->getModelSchemes()->getPrimaryKey($parentClass);
                    foreach ($parents as $parent) {
                        $builder->setParameter(static::$indexBind, $parent->{$pkName});
                        list($children, $hasMore, $limit, $offset) =
                            $this->fetchCollection($builder, $class, $queryLimit, $queryOffset);
                        $deDupedChildren = [];
                        foreach ($children as $child) {
                            $child = $deDup->register($child);
                            $modelsAtPath->register($child, $childrenPath);
                            if ($child !== null) {
                                $deDupedChildren[] = $child;
                            }
                        }
                        $result->addToManyRelationship($parent, $name, $deDupedChildren, $hasMore, $offset, $limit);
                    }
                    break;
            }
        }
    }

    /**
     * @param string           $class
     * @param array            $attributes
     * @param Type[]           $types
     * @param AbstractPlatform $platform
     *
     * @return mixed|null
     */
    private function readInstanceFromAssoc($class, array $attributes, array $types, AbstractPlatform $platform)
    {
        $instance = new $class();
        foreach ($attributes as $name => $value) {
            if (array_key_exists($name, $types) === true) {
                /** @var Type $type */
                $type  = $types[$name];
                $value = $type->convertToPHPValue($value, $platform);
            }
            $instance->{$name} = $value;
        }

        return $instance;
    }

    /**
     * @param array            $attributes
     * @param Type[]           $types
     * @param AbstractPlatform $platform
     *
     * @return array
     */
    private function readRowFromAssoc(array $attributes, array $types, AbstractPlatform $platform)
    {
        $row = [];
        foreach ($attributes as $name => $value) {
            if (array_key_exists($name, $types) === true) {
                /** @var Type $type */
                $type  = $types[$name];
                $value = $type->convertToPHPValue($value, $platform);
            }
            $row[$name] = $value;
        }

        return $row;
    }
}
