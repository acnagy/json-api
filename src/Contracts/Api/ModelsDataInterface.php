<?php namespace Limoncello\JsonApi\Contracts\Api;

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

use Limoncello\Models\Contracts\PaginatedDataInterface;
use Limoncello\Models\Contracts\RelationshipStorageInterface;

/**
 * @package Limoncello\JsonApi
 */
interface ModelsDataInterface
{
    /**
     * @return PaginatedDataInterface
     */
    public function getPaginatedData();

    /**
     * @return RelationshipStorageInterface|null
     */
    public function getRelationshipStorage();
}
