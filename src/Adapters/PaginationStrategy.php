<?php namespace Limoncello\JsonApi\Adapters;

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

use Limoncello\JsonApi\Contracts\Adapters\PaginationStrategyInterface;

/**
 * @package Limoncello\JsonApi
 */
class PaginationStrategy implements PaginationStrategyInterface
{
    /** Paging constant */
    const DEFAULT_LIMIT_SIZE = 20;

    /** Paging constant */
    const MAX_LIMIT_SIZE = 100;

    /**
     * @var int
     */
    private $defaultPageLimit;

    /**
     * @param int $defaultPageLimit
     */
    public function __construct($defaultPageLimit = self::DEFAULT_LIMIT_SIZE)
    {
        $this->defaultPageLimit = $defaultPageLimit;
    }

    /**
     * @inheritdoc
     */
    public function getParameters($rootClass, $class, $path, $relationshipName)
    {
        // input parameters are ignored (same paging params for all)
        // feel free to change it in child classes
        $rootClass && $class && $path && $relationshipName ?: null;

        $offset = 0;

        return [$offset, $this->defaultPageLimit + 1];
    }

    /**
     * @inheritdoc
     */
    public function parseParameters(array $parameters = null)
    {
        if ($parameters === null) {
            return [0, $this->defaultPageLimit + 1];
        }

        $limit = $this->getValue(
            $parameters,
            static::PARAM_PAGING_SIZE,
            static::DEFAULT_LIMIT_SIZE,
            1,
            static::MAX_LIMIT_SIZE
        );

        $offset = $this->getValue($parameters, static::PARAM_PAGING_SKIP, 0, 0, PHP_INT_MAX);

        return [$offset, $limit + 1];
    }

    /**
     * @param array  $parameters
     * @param string $key
     * @param int    $default
     * @param int    $min
     * @param int    $max
     *
     * @return int
     */
    private function getValue(array $parameters, $key, $default, $min, $max)
    {
        $result = $default;
        if (isset($parameters[$key]) === true) {
            $value = $parameters[$key];
            if (is_string($value) === true || is_int($value) === true) {
                $value = intval($value);
                if ($min <= $value && $value <= $max) {
                    $result = $value;
                }
            }
        }

        return $result;
    }
}
