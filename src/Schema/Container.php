<?php namespace Limoncello\JsonApi\Schema;

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
use Limoncello\JsonApi\Contracts\Schema\ContainerInterface;
use Limoncello\JsonApi\Contracts\Schema\SchemaInterface;
use Limoncello\Models\Contracts\RelationshipStorageInterface;
use Limoncello\Models\Contracts\SchemaStorageInterface;
use Neomerx\JsonApi\Contracts\Schema\SchemaFactoryInterface;

/**
 * @package Limoncello\JsonApi
 */
class Container extends \Neomerx\JsonApi\Schema\Container implements ContainerInterface
{
    /**
     * @var RelationshipStorageInterface
     */
    private $relationshipStorage;

    /**
     * @var SchemaStorageInterface
     */
    private $modelSchemes;

    /**
     * @param SchemaFactoryInterface $factory
     * @param array                  $schemas
     * @param SchemaStorageInterface $modelSchemes
     */
    public function __construct(SchemaFactoryInterface $factory, array $schemas, SchemaStorageInterface $modelSchemes)
    {
        parent::__construct($factory, $schemas);
        $this->modelSchemes = $modelSchemes;
    }

    /**
     * @inheritdoc
     */
    public function getRelationshipStorage()
    {
        return $this->relationshipStorage;
    }

    /**
     * @inheritdoc
     */
    public function setRelationshipStorage(RelationshipStorageInterface $storage)
    {
        $this->relationshipStorage = $storage;
    }

    /**
     * @return SchemaStorageInterface
     */
    protected function getModelSchemes()
    {
        return $this->modelSchemes;
    }

    /** @noinspection PhpMissingParentCallCommonInspection
     * @param Closure $closure
     *
     * @return SchemaInterface
     */
    protected function createSchemaFromClosure(Closure $closure)
    {
        $schema = $closure($this->getFactory(), $this, $this->getModelSchemes());

        return $schema;
    }

    /** @noinspection PhpMissingParentCallCommonInspection
     * @param string $className
     *
     * @return SchemaInterface
     */
    protected function createSchemaFromClassName($className)
    {
        $schema = new $className($this->getFactory(), $this, $this->getModelSchemes());

        return $schema;
    }
}
