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

use Limoncello\JsonApi\Contracts\Api\ModelsDataInterface;
use Limoncello\JsonApi\Contracts\Models\PaginatedDataInterface;
use Limoncello\JsonApi\Contracts\Models\RelationshipStorageInterface;

/**
 * @package Limoncello\JsonApi
 */
class ModelsData implements ModelsDataInterface
{
    /**
     * @var PaginatedDataInterface
     */
    private $paginatedData;

    /**
     * @var RelationshipStorageInterface
     */
    private $relationshipStorage;

    /**
     * @param PaginatedDataInterface       $paginatedData
     * @param RelationshipStorageInterface $relationshipStorage
     */
    public function __construct(
        PaginatedDataInterface $paginatedData,
        RelationshipStorageInterface $relationshipStorage = null
    ) {
        $this->paginatedData       = $paginatedData;
        $this->relationshipStorage = $relationshipStorage;
    }

    /**
     * @inheritdoc
     */
    public function getPaginatedData()
    {
        return $this->paginatedData;
    }

    /**
     * @inheritdoc
     */
    public function getRelationshipStorage()
    {
        return $this->relationshipStorage;
    }
}
