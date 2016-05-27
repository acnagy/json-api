<?php namespace Limoncello\JsonApi;

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

use Limoncello\JsonApi\Contracts\Adapters\RepositoryInterface;
use Limoncello\JsonApi\Contracts\CrudInterface;
use Limoncello\JsonApi\Contracts\Document\ResourceInterface;
use Limoncello\JsonApi\Contracts\FactoryInterface;
use Neomerx\JsonApi\Exceptions\JsonApiException as E;

/**
 * @package Limoncello\JsonApi
 */
class Crud implements CrudInterface
{
    /** Event id */
    const EVENT_ON_CREATE = 0;

    /** Event id */
    const EVENT_ON_CREATING = 1;

    /** Event id */
    const EVENT_ON_UPDATE = 2;

    /** Event id */
    const EVENT_ON_UPDATING = 3;

    /** Event id */
    const EVENT_ON_DELETE = 4;

    /** Event id */
    const EVENT_ON_DELETING = 5;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var FactoryInterface
     */
    private $factory;

    /**
     * @param RepositoryInterface $repository
     * @param FactoryInterface    $factory
     */
    public function __construct(RepositoryInterface $repository, FactoryInterface $factory)
    {
        $this->factory    = $factory;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function index(
        array $filterParams = null,
        array $sortParams = null,
        array $includePaths = null,
        array $pagingParams = null
    ) {
        $data = $this->getRepository()->index($filterParams, $sortParams, $pagingParams);
        $this->checkRepoErrors();

        $relationships = null;
        if (empty($data) === false && $includePaths !== null) {
            $relationships = $this->getRepository()->readRelationships($data->getData(), $includePaths);
            $this->checkRepoErrors();
        }

        $result = $this->getFactory()->createModelsData($data, $relationships);

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function read($index, array $filterParams = null, array $includePaths = null)
    {
        $data = $this->getRepository()->read($index);
        $this->checkRepoErrors();

        $relationships = null;
        if ($data !== null && $includePaths !== null) {
            $relationships = $this->getRepository()->readRelationships([$data], $includePaths);
            $this->checkRepoErrors();
        }

        $result = $this->getFactory()->createModelsData(
            $this->getFactory()->createPaginatedData($data),
            $relationships
        );

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function readRelationship(
        $index,
        $name,
        array $filterParams = null,
        array $sortParams = null,
        array $pagingParams = null
    ) {
        $data = $this->getRepository()->readRelationship($index, $name, $filterParams, $sortParams, $pagingParams);
        $this->checkRepoErrors();

        return $data;
    }

    /**
     * @inheritdoc
     */
    public function delete($index)
    {
        $this->event(self::EVENT_ON_DELETE, $index);
        $this->getRepository()->inTransaction(function () use ($index) {
            $this->getRepository()->delete($index);
            $this->checkRepoErrors();
            $this->event(self::EVENT_ON_DELETING, $index);
        });
    }

    /**
     * @inheritdoc
     */
    public function create(ResourceInterface $resource)
    {
        $model = $this->getRepository()->instance($resource->getId());
        $this->checkRepoErrors();

        $this->setAttributes($model, $resource->getAttributes());

        $this->setToOne($model, $resource->getToOneRelationships());

        $toMany = $resource->getToManyRelationships();

        $index = null;
        $this->event(self::EVENT_ON_CREATE, $model);
        $this->getRepository()->inTransaction(function () use ($model, $toMany, &$index) {
            $index = $this->getRepository()->create($model);
            $this->checkRepoErrors();
            $this->setToManyOnCreate($index, $toMany);
            $this->event(self::EVENT_ON_CREATING, $model);
        });

        return $index;
    }

    /**
     * @inheritdoc
     */
    public function update($index, ResourceInterface $resource)
    {
        $data  = $this->read($index)->getPaginatedData();
        $model = $data->getData();

        $original = clone $model;

        $this->setAttributes($model, $resource->getAttributes());

        $this->setToOne($model, $resource->getToOneRelationships());

        $toMany = $resource->getToManyRelationships();

        $this->event(self::EVENT_ON_UPDATE, $model);
        $this->getRepository()->inTransaction(function () use ($index, $model, $original, $toMany) {
            $this->getRepository()->update($model, $original);
            $this->checkRepoErrors();
            $this->setToManyOnUpdate($index, $toMany);
            $this->event(self::EVENT_ON_UPDATING, $model);
        });
    }

    /**
     * @param int   $eventId
     * @param mixed $model
     */
    protected function event($eventId, $model)
    {
        $eventId && $model ?: null;
    }

    /**
     * @return RepositoryInterface
     */
    protected function getRepository()
    {
        return $this->repository;
    }

    /**
     * @return FactoryInterface
     */
    protected function getFactory()
    {
        return $this->factory;
    }

    /**
     * @param mixed $model
     * @param array $attributes
     */
    private function setAttributes($model, array $attributes)
    {
        foreach ($attributes as $name => $value) {
            $this->getRepository()->setAttribute($model, $name, $value);
        }
        $this->checkRepoErrors();
    }

    /**
     * @param mixed $model
     * @param array $toOne
     */
    private function setToOne($model, array $toOne)
    {
        foreach ($toOne as $name => $value) {
            $this->getRepository()->setToOneRelationship($model, $name, $value);
        }
        $this->checkRepoErrors();
    }

    /**
     * @param int|string $index
     * @param array      $toMany
     *
     * @return void
     */
    private function setToManyOnCreate($index, array $toMany)
    {
        foreach ($toMany as $name => $values) {
            $this->getRepository()->saveToManyRelationship($index, $name, $values);
        }
        $this->checkRepoErrors();
    }

    /**
     * @param int|string $index
     * @param array      $toMany
     *
     * @return void
     */
    private function setToManyOnUpdate($index, array $toMany)
    {
        foreach ($toMany as $name => $values) {
            $this->getRepository()->cleanToManyRelationship($index, $name);
            $this->checkRepoErrors();
            $this->getRepository()->saveToManyRelationship($index, $name, $values);
        }
        $this->checkRepoErrors();
    }

    /**
     * @return void
     */
    private function checkRepoErrors()
    {
        $errors = $this->getRepository()->getErrors();
        $errors->count() <= 0 ?: $this->throwEx(new E($errors));
    }

    /**
     * @param E $exception
     */
    private function throwEx(E $exception)
    {
        throw $exception;
    }
}
