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

use Closure;
use Limoncello\JsonApi\Contracts\Adapters\FilterOperationsInterface;
use Limoncello\JsonApi\Contracts\QueryBuilderInterface;

/**
 * @package Limoncello\JsonApi
 */
class FilterOperations implements FilterOperationsInterface
{
    /** Default filter operation */
    const DEFAULT_OPERATION = self::EQ;

    /** Operation name */
    const EQ = 'eq';

    /** Operation name */
    const EQUALS = 'equals';

    /** Operation name */
    const NE = 'ne';

    /** Operation name */
    const NOT_EQUALS = 'not-equals';

    /** Operation name */
    const GT = 'gt';

    /** Operation name */
    const GREATER_THAN = 'greater-than';

    /** Operation name */
    const GE = 'ge';

    /** Operation name */
    const GREATER_OR_EQUALS = 'greater-or-equals';

    /** Operation name */
    const LT = 'lt';

    /** Operation name */
    const LESS_THAN = 'less-than';

    /** Operation name */
    const LE = 'le';

    /** Operation name */
    const LESS_OR_EQUALS = 'less-or-equals';

    /** Operation name */
    const LIKE = 'like';

    /** Operation name */
    const IN = 'in';

    /**
     * @var Closure[]
     */
    protected $handlers;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->handlers = [
            self::EQ                => [$this, 'getHandlerForEq'],
            self::EQUALS            => [$this, 'getHandlerForEq'],
            self::NE                => [$this, 'getHandlerForNe'],
            self::NOT_EQUALS        => [$this, 'getHandlerForNe'],
            self::GT                => [$this, 'getHandlerForGt'],
            self::GREATER_THAN      => [$this, 'getHandlerForGt'],
            self::GE                => [$this, 'getHandlerForGe'],
            self::GREATER_OR_EQUALS => [$this, 'getHandlerForGe'],
            self::LT                => [$this, 'getHandlerForLt'],
            self::LESS_THAN         => [$this, 'getHandlerForLt'],
            self::LE                => [$this, 'getHandlerForLe'],
            self::LESS_OR_EQUALS    => [$this, 'getHandlerForLe'],
            self::LIKE              => [$this, 'getHandlerForLike'],
            self::IN                => [$this, 'getHandlerForIn'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function hasOperation($name)
    {
        $name   = $this->normalizeOperation($name);
        $result = array_key_exists($name, $this->handlers);

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getDefaultOperation($table, $field, array $arguments)
    {
        return $this->getOperations(static::DEFAULT_OPERATION, $table, $field, $arguments);
    }

    /**
     * @inheritdoc
     */
    public function getOperations($name, $table, $field, array $arguments)
    {
        $name    = $this->normalizeOperation($name);
        $handler = $this->getHandler($name);
        $result  = call_user_func($handler, $table, $field, $arguments);

        return $result;
    }

    /**
     * @param string   $name
     * @param Closure $handler
     *
     * @return void
     */
    public function register($name, Closure $handler)
    {
        $name = $this->normalizeOperation($name);
        $this->handlers[$name] = $handler;
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function unregister($name)
    {
        $name = $this->normalizeOperation($name);
        unset($this->handlers[$name]);
    }

    /**
     * @param string $name
     *
     * @return Closure
     */
    protected function getHandler($name)
    {
        $handler = $this->handlers[$name];
        if (($handler instanceof Closure) === false) {
            $handler = call_user_func($handler);
            $this->handlers[$name] = $handler;
        }

        return $handler;
    }

    /**
     * @return Closure
     */
    protected function getHandlerForEq()
    {
        return function ($table, $field, array $parameters) {
            $result = null;
            $value  = reset($parameters);
            if ((is_string($value) === true || is_int($value) === true) && empty($value) === false) {
                $result = [$table, $field, '=', $value];
            } elseif ($value === null) {
                $result = [$table, $field, QueryBuilderInterface::SQL_IS, $value];
            }
            yield $result;
        };
    }

    /**
     * @return Closure
     */
    protected function getHandlerForNe()
    {
        return function ($table, $field, array $parameters) {
            $result = null;
            $value  = reset($parameters);
            if (is_string($value) === true && empty($value) === false) {
                $result = [$table, $field, '<>', $value];
            } elseif ($value === null) {
                $result = [$table, $field, QueryBuilderInterface::SQL_IS_NOT, $value];
            }
            yield $result;
        };
    }

    /**
     * @return Closure
     */
    protected function getHandlerForGt()
    {
        return $this->getSingleParamHandler('>');
    }

    /**
     * @return Closure
     */
    protected function getHandlerForGe()
    {
        return $this->getSingleParamHandler('>=');
    }

    /**
     * @return Closure
     */
    protected function getHandlerForLt()
    {
        return $this->getSingleParamHandler('<');
    }

    /**
     * @return Closure
     */
    protected function getHandlerForLe()
    {
        return $this->getSingleParamHandler('<=');
    }

    /**
     * @return Closure
     */
    protected function getHandlerForLike()
    {
        return $this->getMultiParamMultiYieldHandler('LIKE');
    }

    /**
     * @return Closure
     */
    protected function getHandlerForIn()
    {
        return $this->getMultiParamSingleYieldHandler('IN');
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function normalizeOperation($name)
    {
        return strtolower($name);
    }

    /**
     * @param string $operation
     *
     * @return Closure
     */
    protected function getSingleParamHandler($operation)
    {
        return function ($table, $field, array $parameters) use ($operation) {
            $value  = reset($parameters);
            if (is_string($value) === true && empty($value) === false) {
                $result = [$table, $field, $operation, $value];
                yield $result;
            }
        };
    }

    /**
     * @param string $operation
     *
     * @return Closure
     */
    protected function getMultiParamMultiYieldHandler($operation)
    {
        return function ($table, $field, array $parameters) use ($operation) {
            foreach ($parameters as $parameter) {
                if (is_string($parameter) === true && empty($parameter) === false) {
                    $result = [$table, $field, $operation, $parameter];
                    yield $result;
                }
            }
        };
    }

    /**
     * @param string $operation
     *
     * @return Closure
     */
    protected function getMultiParamSingleYieldHandler($operation)
    {
        return function ($table, $field, array $parameters) use ($operation) {
            $result = [$table, $field, $operation, $parameters];
            yield $result;
        };
    }
}
