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

use Neomerx\JsonApi\Contracts\Schema\SchemaProviderInterface;

/**
 * @package Limoncello\JsonApi
 */
interface SchemaInterface extends SchemaProviderInterface
{
    /** Type */
    const TYPE = null;

    /** Model class name */
    const MODEL = null;

    /** Mapping key */
    const SCHEMA_ATTRIBUTES = 0;

    /** Mapping key */
    const SCHEMA_RELATIONSHIPS = self::SCHEMA_ATTRIBUTES + 1;

    /** Mapping key */
    const SCHEMA_INCLUDE = self::SCHEMA_RELATIONSHIPS + 1;

    /**
     * @return array
     */
    public static function getMappings();
}
