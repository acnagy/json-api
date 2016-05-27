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

use Limoncello\JsonApi\Contracts\Document\ResourceIdentifierInterface;

/**
 * @package Limoncello\JsonApi
 */
class ResourceIdentifier implements ResourceIdentifierInterface
{
    /**
     * @var string|int|null
     */
    private $index;

    /**
     * @var string
     */
    private $type;

    /**
     * @param string          $type
     * @param int|null|string $index
     */
    public function __construct($type, $index)
    {
        $this->index = $index;
        $this->type  = $type;
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->index;
    }

    /**
     * @inheritdoc
     */
    public function getType()
    {
        return $this->type;
    }
}
