<?php namespace Limoncello\JsonApi\Models;

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

use Limoncello\JsonApi\Contracts\Models\PaginatedDataInterface;

/**
 * @package Limoncello\Models
 */
class PaginatedData implements PaginatedDataInterface
{
    /** @var  mixed */
    private $data;

    /** @var  bool */
    private $isCollection = false;

    /** @var  bool */
    private $hasMoreItems = false;

    /** @var  int|null */
    private $offset = null;

    /** @var  int|null */
    private $size = null;

    /**
     * @param mixed $data
     */
    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @inheritdoc
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @inheritdoc
     */
    public function isCollection()
    {
        return $this->isCollection;
    }

    /**
     * @inheritdoc
     */
    public function setIsCollection($isCollection)
    {
        $this->isCollection = $isCollection;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function hasMoreItems()
    {
        return $this->hasMoreItems;
    }

    /**
     * @inheritdoc
     */
    public function setHasMoreItems($hasMoreItems)
    {
        $this->hasMoreItems = $hasMoreItems;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @inheritdoc
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getLimit()
    {
        return $this->size;
    }

    /**
     * @inheritdoc
     */
    public function setLimit($size)
    {
        $this->size = $size;

        return $this;
    }
}
