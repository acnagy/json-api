<?php namespace Limoncello\JsonApi\Document;

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

use Limoncello\JsonApi\Contracts\Document\ResourceInterface;

/**
 * @package Limoncello\JsonApi
 */
class Resource extends ResourceIdentifier implements ResourceInterface
{
    /**
     * @var array
     */
    private $attributes;

    /**
     * @var array
     */
    private $toOne;

    /**
     * @var array
     */
    private $toMany;

    /**
     * @param string          $type
     * @param int|null|string $index
     * @param array           $attributes
     * @param array           $toOne
     * @param array           $toMany
     */
    public function __construct($type, $index, array $attributes, array $toOne, array $toMany)
    {
        parent::__construct($type, $index);

        $this->attributes = $attributes;
        $this->toOne      = $toOne;
        $this->toMany     = $toMany;
    }

    /**
     * @inheritdoc
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @inheritdoc
     */
    public function getToOneRelationships()
    {
        return $this->toOne;
    }

    /**
     * @inheritdoc
     */
    public function getToManyRelationships()
    {
        return $this->toMany;
    }
}
