<?php namespace Limoncello\JsonApi\Config;

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

use Limoncello\JsonApi\Contracts\Config\JsonApiConfigInterface;

/**
 * @package Limoncello\JsonApi
 */
class JsonApiConfig implements JsonApiConfigInterface
{
    /**
     * @var array
     */
    private $modelSchemaMap;

    /**
     * @var int
     */
    private $options = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    /**
     * @var int
     */
    private $depth = 512;

    /**
     * @var bool
     */
    private $isShowVersion = false;

    /**
     * @var mixed
     */
    private $meta = null;

    /**
     * @var string
     */
    private $uriPrefix = null;

    /**
     * @var int
     */
    private $pagingSize = 20;

    /**
     * @param array $modelSchemaMap
     */
    public function __construct(array $modelSchemaMap)
    {
        $this->modelSchemaMap = $modelSchemaMap;
    }

    /**
     * @inheritdoc
     */
    public function getJsonEncodeOptions()
    {
        return $this->options;
    }

    /**
     * @inheritdoc
     */
    public function setJsonEncodeOptions($options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setJsonEncodeDepth($depth)
    {
        $this->depth = $depth;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setShowVersion()
    {
        $this->isShowVersion = true;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setHideVersion()
    {
        $this->isShowVersion = false;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setMeta($meta)
    {
        $this->meta = $meta;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setUriPrefix($prefix)
    {
        $this->uriPrefix = $prefix;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setRelationshipPagingSize($size)
    {
        $this->pagingSize = $size;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getConfig()
    {
        return [
            self::KEY_MODEL_TO_SCHEMA_MAP => $this->modelSchemaMap,

            self::KEY_JSON => [
                self::KEY_JSON_RELATIONSHIP_PAGING_SIZE => $this->pagingSize,
                self::KEY_JSON_OPTIONS                  => $this->options,
                self::KEY_JSON_DEPTH                    => $this->depth,
                self::KEY_JSON_IS_SHOW_VERSION          => $this->isShowVersion,
                self::KEY_JSON_VERSION_META             => $this->meta,
                self::KEY_JSON_URL_PREFIX               => $this->uriPrefix,
            ],

            self::KEY_EXECUTE_DB_QUERIES_ONE_BY_ONE => true,
        ];
    }
}
