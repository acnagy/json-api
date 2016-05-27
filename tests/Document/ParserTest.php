<?php namespace Limoncello\Tests\JsonApi\Document;

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

use Limoncello\JsonApi\Contracts\Document\ParserInterface;
use Limoncello\JsonApi\Factory;
use Limoncello\Tests\JsonApi\Data\Transformers\CommentOnUpdate;
use Limoncello\Tests\JsonApi\Data\Transformers\PostOnUpdate;
use Limoncello\Tests\JsonApi\Data\Transformers\UserOnUpdate;
use Limoncello\Tests\JsonApi\TestCase;
use Neomerx\JsonApi\Contracts\Document\ErrorInterface;

/**
 * @package Limoncello\Tests\JsonApi
 */
class ParserTest extends TestCase
{
    /**
     * Test parse.
     */
    public function testParseResourceWithoutRelationships()
    {
        $parser = $this->getParserForUsers();

        $json = <<<EOT
        {
            "meta" : {
                "copyright" : "Copyright 2015 Example Corp.",
                "authors" : [
                    "Yehuda Katz",
                    "Steve Klabnik",
                    "Dan Gebhardt"
                ]
            },
            "data" : {
                "type"       : "users",
                "id"         : "9",
                "attributes" : {
                    "first-name-attribute" : "Dan",
                    "last-name-attribute"  : "Gebhardt"
                },
                "links" : {
                    "self" : "http://example.com/people/9"
                }
            }
        }
EOT;
        $this->assertNotNull($result = $parser->parse($json));
        $this->assertEquals(0, $parser->getErrors()->count());
        $this->assertEquals('users', $result->getType());
        $this->assertEquals('9', $result->getId());
        $this->assertEquals([
            'first_name' => 'Dan',
            'last_name'  => 'Gebhardt',
        ], $result->getAttributes());
        $this->assertEquals([], $result->getToOneRelationships());
        $this->assertEquals([], $result->getToManyRelationships());
    }

    /**
     * Test parse.
     */
    public function testParseResourceWithRelationships()
    {
        $parser = $this->getParserForComments();

        $json = <<<EOT
        {
            "data" : {
                "type"  : "comments",
                "id"    : 1,
                "attributes" : {
                    "text-attribute" : "Outside every fat man there was an even fatter man trying to close in"
                },
                "relationships" : {
                    "user-relationship" : {
                        "data" : { "type" : "users", "id" : 9 }
                    },
                    "emotions-relationship" : {
                        "data" : [
                            { "type" : "emotions", "id" : 5 },
                            { "type" : "emotions", "id" : "12" }
                        ]
                    }
                },
                "links" : {
                    "self" : "http://example.com/posts/1"
                }
            }
        }
EOT;
        $this->assertNotNull($result = $parser->parse($json));
        $this->assertEquals(0, $parser->getErrors()->count());
        $this->assertEquals('comments', $result->getType());
        $this->assertEquals(1, $result->getId());
        $this->assertEquals([
            'text'  => 'Outside every fat man there was an even fatter man trying to close in',
        ], $result->getAttributes());

        $this->assertCount(1, $result->getToOneRelationships());
        $this->assertEquals('9', $result->getToOneRelationships()['user']);

        $this->assertCount(1, $result->getToManyRelationships());
        $comments = $result->getToManyRelationships()['emotions'];
        $this->assertCount(2, $comments);
        $this->assertEquals('5', $comments[0]);
        $this->assertEquals('12', $comments[1]);
    }

    /**
     * Test parse.
     */
    public function testParseResourceWithNonExistingAttributesAndRelationships()
    {
        $parser = $this->getParserForComments();

        $json = <<<EOT
        {
            "data" : {
                "type"  : "comments",
                "id"    : 1,
                "attributes" : {
                    "text-attribute-xxx" : "whatever"
                },
                "relationships" : {
                    "user-relationship-xxx" : {
                        "data" : { "type" : "users", "id" : 9 }
                    },
                    "emotions-relationship-xxx" : {
                        "data" : [
                            { "type" : "emotions", "id" : 5 },
                            { "type" : "emotions", "id" : "12" }
                        ]
                    }
                }
            }
        }
EOT;
        $this->assertNull($result = $parser->parse($json));
        $this->assertCount(3, $errors = $parser->getErrors());

        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/attributes/text-attribute-xxx'
        ], $errors[0]->getSource());

        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/relationships/user-relationship-xxx'
        ], $errors[1]->getSource());

        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/relationships/emotions-relationship-xxx'
        ], $errors[2]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParseResourceWithInvalidType()
    {
        $parser = $this->getParserForComments();

        $json = <<<EOT
        {
            "data" : {
                "type"  : "comments-xxx",
                "id"    : 1
            }
        }
EOT;
        $this->assertNull($result = $parser->parse($json));
        $this->assertCount(1, $errors = $parser->getErrors());

        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/type'
        ], $errors[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testEmptyRelationships()
    {
        $parser = $this->getParserForComments();

        $json = <<<EOT
        {
            "data" : {
                "type"  : "comments",
                "id"    : "1",
                "relationships" : {
                    "user-relationship" : {
                        "data" : null
                    },
                    "emotions-relationship" : {
                        "data" : []
                    }
                }
            }
        }
EOT;
        $this->assertNotNull($result = $parser->parse($json));
        $this->assertEquals(0, $parser->getErrors()->count());
        $this->assertEquals('comments', $result->getType());
        $this->assertEquals('1', $result->getId());
        $this->assertEquals([], $result->getAttributes());

        $this->assertCount(1, $result->getToOneRelationships());
        $this->assertNull($result->getToOneRelationships()['user']);

        $this->assertCount(1, $result->getToManyRelationships());
        $comments = $result->getToManyRelationships()['emotions'];
        $this->assertEquals([], $comments);
    }

    /**
     * Test parse.
     */
    public function testParsInvalidData1()
    {
        $parser = $this->getParserForPosts();

        $json = <<<EOT
        {
        }
EOT;
        $this->assertNull($parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParsInvalidData2()
    {
        $parser = $this->getParserForPosts();

        $json = <<<EOT
        {
            "data": null
        }
EOT;
        $this->assertNull($parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParsInvalidData3()
    {
        $parser = $this->getParserForPosts();

        $json = '';
        $this->assertNull($parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParsInvalidData4()
    {
        $parser = $this->getParserForPosts();

        $json = '[]';
        $this->assertNull($parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParsInvalidId1()
    {
        $parser = $this->getParserForPosts();

        $json = <<<EOT
        {
            "data" : {
                "type" : "posts"
            }
        }
EOT;
        $this->assertNull($parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/id'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParsInvalidId2()
    {
        $parser = $this->getParserForPosts();

        $json = <<<EOT
        {
            "data" : {
                "type" : "posts",
                "id" : 3.14
            }
        }
EOT;
        $this->assertNull($parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/id'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParsInvalidType1()
    {
        $parser = $this->getParserForPosts();
        $json = <<<EOT
        {
            "data" : {
                "type" : null,
                "id"   : "1"
            }
        }
EOT;
        $this->assertNull($parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/type'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParsInvalidType2()
    {
        $parser = $this->getParserForPosts();
        $json = <<<EOT
        {
            "data" : {
                "id" : "1"
            }
        }
EOT;
        $this->assertNull($parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/type'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParseInvalidRelationships1()
    {
        $parser = $this->getParserForPosts();
        $json = <<<EOT
        {
            "data" : {
                "type"  : "posts",
                "id"    : "1",
                "relationships" : "whatever"
            }
        }
EOT;
        $this->assertNull($result = $parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/relationships'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParseInvalidRelationships2()
    {
        $parser = $this->getParserForPosts();

        $json = <<<EOT
        {
            "data" : {
                "type"  : "posts",
                "id"    : "1",
                "relationships" : {
                    "author-relationship" : {
                        "data": "invalid value"
                    }
                }
            }
        }
EOT;
        $this->assertNull($result = $parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/relationships/author-relationship'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParseInvalidToOneRelationships()
    {
        $parser = $this->getParserForPosts();
        $json = <<<EOT
        {
            "data" : {
                "type"  : "posts",
                "id"    : "1",
                "relationships" : {
                    "author1-relationship" : {
                        "data" : { "type" : null, "id" : "9" }
                    },
                    "author2-relationship" : {
                        "data" : { "id" : "9" }
                    },
                    "author3-relationship" : {
                        "data" : { "type" : "people", "id" : null }
                    },
                    "author4-relationship" : {
                        "data" : { "type" : "people" }
                    }
                }
            }
        }
EOT;
        $this->assertNull($result = $parser->parse($json));
        $this->assertEquals(4, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/relationships/author1-relationship/data/type'
        ], $parser->getErrors()[0]->getSource());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/relationships/author2-relationship/data/type'
        ], $parser->getErrors()[1]->getSource());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/relationships/author3-relationship/data/id'
        ], $parser->getErrors()[2]->getSource());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/relationships/author4-relationship/data/id'
        ], $parser->getErrors()[3]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParseInvalidToManyRelationship1()
    {
        $parser = $this->getParserForPosts();

        $json = <<<EOT
        {
            "data" : {
                "type"  : "posts",
                "id"    : "1",
                "relationships" : {
                    "comments-relationship" : {
                        "data" : [
                            { "type" : null, "id" : "5" }
                        ]
                    }
                }
            }
        }
EOT;
        $this->assertNull($result = $parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/relationships/comments-relationship/data/type'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParseInvalidToManyRelationship2()
    {
        $parser = $this->getParserForPosts();

        $json = <<<EOT
        {
            "data" : {
                "type"  : "posts",
                "id"    : "1",
                "relationships" : {
                    "comments-relationship" : {
                        "data" : [
                            { "id" : "5" }
                        ]
                    }
                }
            }
        }
EOT;
        $this->assertNull($result = $parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/relationships/comments-relationship/data/type'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParseInvalidToManyRelationship3()
    {
        $parser = $this->getParserForPosts();

        $json = <<<EOT
        {
            "data" : {
                "type"  : "posts",
                "id"    : "1",
                "relationships" : {
                    "comments-relationship" : {
                        "data" : [
                            { "type" : "comments", "id" : null }
                        ]
                    }
                }
            }
        }
EOT;
        $this->assertNull($result = $parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/relationships/comments-relationship/data/id'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParseInvalidToManyRelationship4()
    {
        $parser = $this->getParserForPosts();

        $json = <<<EOT
        {
            "data" : {
                "type"  : "posts",
                "id"    : "1",
                "relationships" : {
                    "comments-relationship" : {
                        "data" : [
                            { "type" : "comments" }
                        ]
                    }
                }
            }
        }
EOT;
        $this->assertNull($result = $parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/relationships/comments-relationship/data/id'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParseInvalidToManyRelationship5()
    {
        $parser = $this->getParserForPosts();

        $json = <<<EOT
        {
            "data" : {
                "type"  : "posts",
                "id"    : "1",
                "relationships" : {
                    "comments-relationship" : {
                        "data" : [
                            "invalid value"
                        ]
                    }
                }
            }
        }
EOT;
        $this->assertNull($result = $parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/relationships/comments-relationship'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * Test parse.
     */
    public function testParseInvalidToManyRelationship6()
    {
        $parser = $this->getParserForPosts();

        $json = <<<EOT
        {
            "data" : {
                "type"  : "posts",
                "id"    : "1",
                "relationships" : {
                    "comments-relationship" : [ "invalid value" ]
                }
            }
        }
EOT;
        $this->assertNull($result = $parser->parse($json));
        $this->assertEquals(1, $parser->getErrors()->count());
        $this->assertEquals([
            ErrorInterface::SOURCE_POINTER => '/data/relationships/comments-relationship'
        ], $parser->getErrors()[0]->getSource());
    }

    /**
     * @return ParserInterface
     */
    private function getParserForPosts()
    {
        $factory        = new Factory();
        $modelSchemes   = $this->getModelSchemes();
        $translator     = $factory->createTranslator();
        $checksOnUpdate = new PostOnUpdate(
            $this->getJsonSchemes($modelSchemes),
            $modelSchemes,
            $factory->createTranslator()
        );

        return $factory->createParser($checksOnUpdate, $translator);
    }

    /**
     * @return ParserInterface
     */
    private function getParserForComments()
    {
        $factory        = new Factory();
        $modelSchemes   = $this->getModelSchemes();
        $translator     = $factory->createTranslator();
        $checksOnUpdate = new CommentOnUpdate(
            $this->getJsonSchemes($modelSchemes),
            $modelSchemes,
            $factory->createTranslator()
        );

        return $factory->createParser($checksOnUpdate, $translator);
    }

    /**
     * @return ParserInterface
     */
    private function getParserForUsers()
    {
        $factory        = new Factory();
        $modelSchemes   = $this->getModelSchemes();
        $translator     = $factory->createTranslator();
        $checksOnCreate = new UserOnUpdate(
            $this->getJsonSchemes($modelSchemes),
            $modelSchemes,
            $factory->createTranslator()
        );

        return $factory->createParser($checksOnCreate, $translator);
    }
}
