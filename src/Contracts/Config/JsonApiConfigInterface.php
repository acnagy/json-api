<?php namespace Limoncello\JsonApi\Contracts\Config;

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

/**
 * @package Limoncello\JsonApi
 */
interface JsonApiConfigInterface
{
    /** Config key */
    const KEY_MODEL_TO_SCHEMA_MAP = 0;

    /** Config key */
    const KEY_JSON = self::KEY_MODEL_TO_SCHEMA_MAP + 1;

    /** Config key @deprecated */
    const KEY_EXECUTE_DB_QUERIES_ONE_BY_ONE = self::KEY_JSON + 1;

    /** Config key */
    const KEY_JSON_RELATIONSHIP_PAGING_SIZE = 0;

    /** Config key */
    const KEY_JSON_OPTIONS = self::KEY_JSON_RELATIONSHIP_PAGING_SIZE + 1;

    /** Config key */
    const KEY_JSON_DEPTH = self::KEY_JSON_OPTIONS + 1;

    /** Config key */
    const KEY_JSON_IS_SHOW_VERSION = self::KEY_JSON_DEPTH + 1;

    /** Config key */
    const KEY_JSON_VERSION_META = self::KEY_JSON_IS_SHOW_VERSION + 1;

    /** Config key */
    const KEY_JSON_URL_PREFIX = self::KEY_JSON_VERSION_META + 1;

    /**
     * @return int
     */
    public function getJsonEncodeOptions();

    /**
     * @param int $options
     *
     * @return $this
     */
    public function setJsonEncodeOptions($options);

    /**
     * @param int $depth
     *
     * @return $this
     */
    public function setJsonEncodeDepth($depth);

    /**
     * @return $this
     */
    public function setShowVersion();

    /**
     * @return $this
     */
    public function setHideVersion();

    /**
     * @param mixed $meta
     *
     * @return $this
     */
    public function setMeta($meta);

    /**
     * @param string $prefix
     *
     * @return $this
     */
    public function setUriPrefix($prefix);

    /**
     * @param int $size
     *
     * @return $this
     */
    public function setRelationshipPagingSize($size);

    /**
     * @return array
     */
    public function getConfig();
}
