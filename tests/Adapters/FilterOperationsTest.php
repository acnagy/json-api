<?php namespace Limoncello\Tests\JsonApi\Adapters;

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

use Limoncello\JsonApi\Adapters\FilterOperations;
use Limoncello\Tests\JsonApi\TestCase;

/**
 * @package Limoncello\Tests\JsonApi
 */
class FilterOperationsTest extends TestCase
{
    /**
     * Test parse.
     */
    public function testRegisterUnregister()
    {
        $ops = new FilterOperations();

        $this->assertFalse($ops->hasOperation('xyz'));

        $ops->register('xyz', function () {
            // doesn't matter for this test
        });

        $this->assertTrue($ops->hasOperation('xyz'));

        $ops->unregister('xyz');

        $this->assertFalse($ops->hasOperation('xyz'));
    }

    /**
     * Test operations.
     */
    public function testOperations()
    {
        $table  = 't';
        $column = 'c';

        $input = [
            [FilterOperations::EQ, ['value'], [[$table, $column, '=', 'value']]],
            [FilterOperations::EQUALS, ['value'], [[$table, $column, '=', 'value']]],
            [FilterOperations::NE, ['value'], [[$table, $column, '<>', 'value']]],
            [FilterOperations::NOT_EQUALS, ['value'], [[$table, $column, '<>', 'value']]],
            [FilterOperations::GT, ['value'], [[$table, $column, '>', 'value']]],
            [FilterOperations::GREATER_THAN, ['value'], [[$table, $column, '>', 'value']]],
            [FilterOperations::GE, ['value'], [[$table, $column, '>=', 'value']]],
            [FilterOperations::GREATER_OR_EQUALS, ['value'], [[$table, $column, '>=', 'value']]],
            [FilterOperations::LT, ['value'], [[$table, $column, '<', 'value']]],
            [FilterOperations::LESS_THAN, ['value'], [[$table, $column, '<', 'value']]],
            [FilterOperations::LE, ['value'], [[$table, $column, '<=', 'value']]],
            [FilterOperations::LESS_OR_EQUALS, ['value'], [[$table, $column, '<=', 'value']]],
            [FilterOperations::LIKE, ['value'], [[$table, $column, 'LIKE', 'value']]],
            [FilterOperations::LIKE, ['value1', 'value2'], [
                [$table, $column, 'LIKE', 'value1'],
                [$table, $column, 'LIKE', 'value2'],
            ]],
            [FilterOperations::IN, ['value1', 'value2'], [[$table, $column, 'IN', ['value1', 'value2']]]],
            [FilterOperations::EQ, [null], [[$table, $column, 'IS', null]]],
            [FilterOperations::NE, [null], [[$table, $column, 'IS NOT', null]]],
        ];

        $ops = new FilterOperations();
        foreach ($input as list($operation, $arguments, $expected)) {
            $operationsIterator = $ops->getOperations($operation, $table, $column, $arguments);
            $operationsArray    = iterator_to_array($operationsIterator);
            $this->assertEquals($expected, $operationsArray);
        }
    }

    /**
     * Test default operation.
     */
    public function testDefaultOperation()
    {
        $ops = new FilterOperations();
        $this->assertEquals(
            [['table', 'column', '=', 'value']],
            iterator_to_array($ops->getDefaultOperation('table', 'column', ['value']))
        );
    }
}
