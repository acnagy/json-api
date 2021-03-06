<?php namespace Limoncello\Tests\JsonApi\Data\Schemes;

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

use Limoncello\JsonApi\Schema\Schema;
use Limoncello\Tests\JsonApi\Data\Models\Model;
use Neomerx\JsonApi\Contracts\Document\DocumentInterface;

/**
 * @package Limoncello\Tests\JsonApi
 */
abstract class BaseSchema extends Schema
{
    /** Attribute name */
    const RESOURCE_ID = DocumentInterface::KEYWORD_ID;

    /** Attribute name */
    const ATTR_CREATED_AT = 'created-at-attribute';

    /** Attribute name */
    const ATTR_UPDATED_AT = 'updated-at-attribute';

    /** Attribute name */
    const ATTR_DELETED_AT = 'deleted-at-attribute';

    /**
     * @inheritdoc
     */
    public function getId($resource)
    {
        /** @var Model $modelClass */
        $modelClass = static::MODEL;
        $pkName     = $modelClass::FIELD_ID;
        $index      = $resource->{$pkName};

        return $index;
    }
}
