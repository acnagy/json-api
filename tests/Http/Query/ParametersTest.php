<?php namespace Limoncello\Tests\JsonApi\Http\Query;

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

use Limoncello\JsonApi\Http\Query\IncludeParameter;
use Limoncello\JsonApi\Http\Query\SortParameter;
use Limoncello\Tests\JsonApi\TestCase;
use Mockery;
use Neomerx\JsonApi\Contracts\Encoder\Parameters\SortParameterInterface as JsonLibrarySortParameterInterface;

/**
 * @package Limoncello\Tests\JsonApi
 */
class ParametersTest extends TestCase
{
    /**
     * Test parameters.
     */
    public function testParameters()
    {
        $includeParam = new IncludeParameter('original', ['path']);
        $this->assertEquals('original', $includeParam->getOriginalPath());
        $this->assertEquals(['path'], $includeParam->getPath());

        /** @var Mockery\Mock $mock */
        $mock = Mockery::mock(JsonLibrarySortParameterInterface::class);
        $mock->shouldReceive('getField')->once()->withNoArgs()->andReturn('original');

        /** @var JsonLibrarySortParameterInterface $mock */

        $sortParam = new SortParameter($mock, 'name', false, null);
        $this->assertEquals('original', $sortParam->getOriginalName());
    }
}
