<?php namespace Limoncello\JsonApi\Contracts\Schema;

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

use Limoncello\Models\Contracts\RelationshipStorageInterface;

/**
 * @package Limoncello\JsonApi
 */
interface ContainerInterface extends \Neomerx\JsonApi\Contracts\Schema\ContainerInterface
{
    /**
     * @return RelationshipStorageInterface
     */
    public function getRelationshipStorage();
    /**
     * @param RelationshipStorageInterface $storage
     */
    public function setRelationshipStorage(RelationshipStorageInterface $storage);

    /**
     * @inheritdoc
     *
     * @return SchemaInterface
     */
    public function getSchema($resourceObject);

    /**
     * @inheritdoc
     *
     * @return SchemaInterface
     */
    public function getSchemaByType($type);

    /**
     * @inheritdoc
     *
     * @return SchemaInterface
     */
    public function getSchemaByResourceType($resourceType);
}
