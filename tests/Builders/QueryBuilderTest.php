<?php namespace Limoncello\Tests\JsonApi\Builders;

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

use Generator;
use Limoncello\JsonApi\Builders\QueryBuilder;
use Limoncello\JsonApi\Contracts\QueryBuilderInterface;
use Limoncello\JsonApi\Factory;
use Limoncello\Tests\JsonApi\TestCase;

/**
 * @package Limoncello\Tests\JsonApi
 */
class QueryBuilderTest extends TestCase
{
    /**
     * @var QueryBuilderInterface
     */
    private $builder;

    /**
     * Set up tests.
     */
    protected function setUp()
    {
        parent::setUp();

        $factory = new Factory();

        $this->builder = new QueryBuilder($factory->createTranslator());
    }

    /**
     * @expectedException \LogicException
     */
    public function testNotConfigured1()
    {
        $this->builder->get();
    }

    /**
     * @expectedException \LogicException
     */
    public function testNotConfigured2()
    {
        $this->builder->delete()->get();
    }

    /**
     * Test implode.
     */
    public function testImplode()
    {
        $this->assertEquals('q1;q2', $this->builder->implode(['q1', 'q2']));
    }

    /**
     * Test `WHERE` with `IS NULL` and `NOT NULL` conditions.
     */
    public function testSelectWithWhereNullAndNotNull()
    {
        list($query, $parameters) = $this->builder
            ->forTable('table')
            ->select($this->getPairs(['table' => 'column']))
            ->where($this->getPairs([
                ['table', 'column1', 'IS', null],
                ['table', 'column2', 'IS NOT', null],
            ]))
            ->get();

        $this->assertEquals(
            'SELECT `table`.`column` FROM `table` WHERE `table`.`column1` IS NULL AND `table`.`column2` IS NOT NULL',
            $query
        );
        $this->assertEmpty($parameters);
    }

    /**
     * Test multi-table join
     */
    public function testMultiJoin()
    {
        list($query, $parameters) = $this->builder
            ->forTable('table')
            ->select($this->getPairs(['t1' => 'c12']))
            ->join($this->get([
                ['t1', 'c11', 't2', 'c22'],
                ['t2', 'c23', 't3', 'c31'],
            ]))
            ->get();

        $expected = <<<EOT
SELECT `t1`.`c12` FROM `table` JOIN `t2` ON `t1`.`c11`=`t2`.`c22` JOIN `t3` ON `t2`.`c23`=`t3`.`c31`
EOT;

        $this->assertEquals($expected, $query);
        $this->assertEmpty($parameters);
    }

    /**
     * @param array $values
     *
     * @return Generator
     */
    private function get(array $values)
    {
        foreach ($values as $value) {
            yield $value;
        }
    }

    /**
     * @param array $values
     *
     * @return Generator
     */
    private function getPairs(array $values)
    {
        foreach ($values as $key => $value) {
            yield $key => $value;
        }
    }
}
