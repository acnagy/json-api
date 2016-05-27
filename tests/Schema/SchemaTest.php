<?php namespace Limoncello\Tests\JsonApi\Schema;

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

use Limoncello\JsonApi\Contracts\ModelsDataInterface;
use Limoncello\Models\Contracts\RelationshipStorageInterface;
use Limoncello\Tests\JsonApi\CrudTest;
use Limoncello\Tests\JsonApi\Data\Schemes\CommentSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\PostSchema;
use Limoncello\Tests\JsonApi\TestCase;
use Neomerx\JsonApi\Contracts\Encoder\EncoderInterface;
use Neomerx\JsonApi\Encoder\EncoderOptions;
use Neomerx\JsonApi\Encoder\Parameters\EncodingParameters;
use Neomerx\JsonApi\Factories\Factory;

/**
 * @package Limoncello\Tests\JsonApi
 */
class SchemaTest extends TestCase
{
    public function testEncodeWithIncludes()
    {
        $test = new CrudTest();
        list($data, , , $includePaths) = $test->init()->testIndex();

        /** @var ModelsDataInterface $data */

        $this->assertEquals([
            'board',
            'comments',
            'comments.emotions',
            'comments.post.user',
        ], $includePaths);
        $params = new EncodingParameters([
            PostSchema::REL_BOARD,
            PostSchema::REL_COMMENTS,
            PostSchema::REL_COMMENTS . '.' . CommentSchema::REL_EMOTIONS,
            PostSchema::REL_COMMENTS . '.' . CommentSchema::REL_POST . '.' . PostSchema::REL_USER,
        ]);

        $encoder = $this->createEncoder($data->getRelationshipStorage());

        $foo = $encoder->encodeData($data->getPaginatedData()->getData(), $params);
        $foo ?: null;

        // TODO add actual output check
    }

    public function testEncodeWithoutIncludes()
    {
        $test = new CrudTest();
        list($data) = $test->init()->testIndex();

        /** @var ModelsDataInterface $data */

        $encoder = $this->createEncoder();

        $json = $encoder->encodeData($data->getPaginatedData()->getData());
        $this->assertNotEmpty($json);

        // TODO add actual output check
    }

    /**
     * @param RelationshipStorageInterface|null $storage
     * @param EncoderOptions|null               $encodeOptions
     *
     * @return EncoderInterface
     */
    protected function createEncoder(RelationshipStorageInterface $storage = null, EncoderOptions $encodeOptions = null)
    {
        $factory   = new Factory();
        $container = $this->getJsonSchemes($this->getModelSchemes(), $storage);
        $encoder   = $factory->createEncoder($container, $encodeOptions);

        return $encoder;
    }
}
