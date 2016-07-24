<?php namespace Limoncello\JsonApi\Http\Query;

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

use Limoncello\JsonApi\Contracts\Http\Query\FilterParameterInterface;

/**
 * @package Limoncello\JsonApi
 */
class FilterParameter implements FilterParameterInterface
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $originalName;

    /**
     * @var mixed
     */
    private $value;

    /**
     * @var bool
     */
    private $isRelationship;

    /**
     * @var int|null
     */
    private $relationshipType = null;

    /**
     * @param string   $originalName
     * @param string   $name
     * @param mixed    $value
     * @param bool     $isRelationship
     * @param int|null $relationshipType
     */
    public function __construct($originalName, $name, $value, $isRelationship, $relationshipType)
    {
        $this->originalName     = $originalName;
        $this->name             = $name;
        $this->value            = $value;
        $this->isRelationship   = $isRelationship;
        if ($isRelationship === true) {
            $this->relationshipType = $relationshipType;
        }
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function getOriginalName()
    {
        return $this->originalName;
    }

    /**
     * @inheritdoc
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @inheritdoc
     */
    public function isIsRelationship()
    {
        return $this->isRelationship;
    }

    /**
     * @inheritdoc
     */
    public function getRelationshipType()
    {
        return $this->relationshipType;
    }
}
