<?php namespace Limoncello\Tests\JsonApi\Http;

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

use Doctrine\DBAL\Connection;
use Limoncello\ContainerLight\Container;
use Limoncello\JsonApi\Adapters\FilterOperations;
use Limoncello\JsonApi\Adapters\PaginationStrategy;
use Limoncello\JsonApi\Config\JsonApiConfig;
use Limoncello\JsonApi\Contracts\Adapters\FilterOperationsInterface;
use Limoncello\JsonApi\Contracts\Adapters\PaginationStrategyInterface;
use Limoncello\JsonApi\Contracts\Adapters\RepositoryInterface;
use Limoncello\JsonApi\Contracts\Config\JsonApiConfigInterface;
use Limoncello\JsonApi\Contracts\Encoder\EncoderInterface;
use Limoncello\JsonApi\Contracts\FactoryInterface;
use Limoncello\JsonApi\Contracts\I18n\TranslatorInterface;
use Limoncello\JsonApi\Contracts\Models\ModelSchemesInterface;
use Limoncello\JsonApi\Contracts\Models\RelationshipStorageInterface;
use Limoncello\JsonApi\Contracts\Schema\JsonSchemesInterface;
use Limoncello\JsonApi\Factory;
use Limoncello\Tests\JsonApi\Data\Api\CommentsApi;
use Limoncello\Tests\JsonApi\Data\Http\BoardsController;
use Limoncello\Tests\JsonApi\Data\Http\CategoriesController;
use Limoncello\Tests\JsonApi\Data\Http\CommentsController;
use Limoncello\Tests\JsonApi\Data\Http\UsersController;
use Limoncello\Tests\JsonApi\Data\Models\Comment;
use Limoncello\Tests\JsonApi\Data\Models\CommentEmotion;
use Limoncello\Tests\JsonApi\Data\Schemes\BoardSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\CategorySchema;
use Limoncello\Tests\JsonApi\Data\Schemes\CommentSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\EmotionSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\UserSchema;
use Limoncello\Tests\JsonApi\Data\Validation\AppValidator;
use Limoncello\Tests\JsonApi\TestCase;
use Limoncello\Validation\Contracts\TranslatorInterface as ValidationTranslatorInterface;
use Limoncello\Validation\I18n\Locales\EnUsLocale;
use Limoncello\Validation\I18n\Translator as ValidationTranslator;
use Mockery;
use Mockery\Mock;
use Neomerx\JsonApi\Contracts\Document\DocumentInterface;
use Neomerx\JsonApi\Contracts\Http\Query\QueryParametersParserInterface;
use Neomerx\JsonApi\Encoder\EncoderOptions;
use Neomerx\JsonApi\Exceptions\JsonApiException;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Uri;

/**
 * @package Limoncello\Tests\JsonApi
 */
class ControllerTest extends TestCase
{
    /**
     * Controller test.
     */
    public function testIndexWithoutParameters()
    {
        $routeParams = [];
        $container   = $this->createContainer();
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn([]);
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn(new Uri('http://localhost.local/comments'));

        /** @var ServerRequestInterface $request */

        $response = CommentsController::index($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());

        $body     = (string)($response->getBody());
        $resource = json_decode($body, true);

        $this->assertEquals(
            'http://localhost.local/comments?page[skip]=10&page[size]=10',
            urldecode($resource[DocumentInterface::KEYWORD_LINKS][DocumentInterface::KEYWORD_NEXT])
        );
        $this->assertCount(10, $resource[DocumentInterface::KEYWORD_DATA]);
    }

    /**
     * Controller test.
     */
    public function testIndexSortByIdDesc()
    {
        $routeParams = [];
        $container   = $this->createContainer();
        $queryParams = [
            'sort' => '-' . CommentSchema::RESOURCE_ID,
        ];
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn($queryParams);
        $uri = new Uri('http://localhost.local/comments?' . http_build_query($queryParams));
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn($uri);

        /** @var ServerRequestInterface $request */

        $response = CommentsController::index($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());

        $body     = (string)($response->getBody());
        $resource = json_decode($body, true);

        $this->assertEquals(
            'http://localhost.local/comments?sort=-id&page[skip]=10&page[size]=10',
            urldecode($resource[DocumentInterface::KEYWORD_LINKS][DocumentInterface::KEYWORD_NEXT])
        );
        $this->assertCount(10, $resources = $resource[DocumentInterface::KEYWORD_DATA]);

        // check IDs are in descending order
        $allDesc = true;
        for ($index = 1; $index < count($resources); $index++) {
            if ($resources[$index]['id'] > $resources[$index - 1]['id']) {
                $allDesc = false;
                break;
            }
        }
        $this->assertTrue($allDesc);
    }

    /**
     * Controller test.
     */
    public function testIndexWithParameters()
    {
        $routeParams = [];
        $queryParams = [
            'filter' => [
                CommentSchema::RESOURCE_ID => [
                    'in' => ['10', '11', '15', '17', '21'],
                ],
                CommentSchema::ATTR_TEXT   => [
                    'like' => '%',
                ],
                CommentSchema::REL_POST    => [
                    'in' => ['8', '11', '15'],
                ],
            ],
            'sort'    => CommentSchema::REL_POST,
            'include' => CommentSchema::REL_USER,
        ];
        $container   = $this->createContainer();
        $uri         = new Uri('http://localhost.local/comments?' . http_build_query($queryParams));
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn($queryParams);
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn($uri);

        /** @var ServerRequestInterface $request */

        $response = CommentsController::index($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());

        $body     = (string)($response->getBody());
        $resource = json_decode($body, true);

        // check reply has resource included
        $this->assertCount(1, $resource[DocumentInterface::KEYWORD_INCLUDED]);
        // manually checked it should be 4 rows selected
        $this->assertCount(4, $resource[DocumentInterface::KEYWORD_DATA]);
        // check response sorted by post.id
        $this->assertEquals(8, $resource['data'][0]['relationships']['post-relationship']['data']['id']);
        $this->assertEquals(11, $resource['data'][1]['relationships']['post-relationship']['data']['id']);
        $this->assertEquals(11, $resource['data'][2]['relationships']['post-relationship']['data']['id']);
        $this->assertEquals(15, $resource['data'][3]['relationships']['post-relationship']['data']['id']);
    }

    /**
     * Controller test.
     */
    public function testIndexWithParametersJoinedByOR()
    {
        $routeParams = [];
        $queryParams = [
            'filter' => [
                'or' => [
                    CommentSchema::RESOURCE_ID => [
                        'in' => ['10', '11',],
                    ],
                    // ID 11 has 'quo' in 'text' and we will check it won't be returned twice
                    CommentSchema::ATTR_TEXT   => [
                        'like' => '%quo%',
                    ],
                ],
            ],
        ];
        $container   = $this->createContainer();
        $uri         = new Uri('http://localhost.local/comments?' . http_build_query($queryParams));
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn($queryParams);
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn($uri);

        /** @var ServerRequestInterface $request */

        $response = CommentsController::index($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());

        $body     = (string)($response->getBody());
        $resource = json_decode($body, true);

        // manually checked it should be 8 rows selected
        $this->assertCount(8, $resource[DocumentInterface::KEYWORD_DATA]);
        $this->assertEquals(10, $resource['data'][0]['id']);
        $this->assertEquals(11, $resource['data'][1]['id']);
        $this->assertEquals(33, $resource['data'][2]['id']);
        $this->assertEquals(44, $resource['data'][3]['id']);
        $this->assertEquals(48, $resource['data'][4]['id']);
        $this->assertEquals(66, $resource['data'][5]['id']);
        $this->assertEquals(77, $resource['data'][6]['id']);
        $this->assertEquals(81, $resource['data'][7]['id']);
    }

    /**
     * Controller test.
     */
    public function testIndexWithParametersWithInvalidJoinParam()
    {
        $routeParams = [];
        $queryParams = [
            'filter' => [
                'or' => [
                    CommentSchema::RESOURCE_ID => [
                        'in' => ['10', '11',],
                    ],
                ],
                'xxx' => 'only one top-level element is allowed if AND/OR is used',
            ],
        ];
        $container   = $this->createContainer();
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn($queryParams);

        /** @var ServerRequestInterface $request */

        $exception = null;
        try {
            CommentsController::index($routeParams, $container, $request);
        } catch (JsonApiException $exception) {
        }
        $this->assertNotNull($exception);

        $errors = $exception->getErrors();
        $this->assertCount(1, $errors);
        $this->assertEquals(['parameter' => 'xxx'], $errors[0]->getSource());
    }

    /**
     * Controller test.
     */
    public function testIndexWithInvalidParameters()
    {
        $routeParams = [];
        $queryParams = [
            'filter' => ['aaa' => ['in' => ['10', '11']]],
            'sort'    => 'bbb',
            'include' => 'ccc',
        ];
        $container   = $this->createContainer();
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn($queryParams);

        /** @var ServerRequestInterface $request */

        $exception = null;
        try {
            CommentsController::index($routeParams, $container, $request);
        } catch (JsonApiException $exception) {
        }
        $this->assertNotNull($exception);

        $this->assertCount(3, $errors = $exception->getErrors());
        $this->assertEquals(['parameter' => 'aaa'], $errors[0]->getSource());
        $this->assertEquals(['parameter' => 'bbb'], $errors[1]->getSource());
        $this->assertEquals(['parameter' => 'ccc'], $errors[2]->getSource());
    }

    /**
     * Controller test.
     */
    public function testPaginationInRelationship()
    {
        $routeParams = [];
        $queryParams = [
            'include' => BoardSchema::REL_POSTS,
        ];
        $container   = $this->createContainer();
        $uri         = new Uri('http://localhost.local/boards?' . http_build_query($queryParams));
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn($queryParams);
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn($uri);

        /** @var ServerRequestInterface $request */

        // replace paging strategy to get paginated results in the relationship
        $container[PaginationStrategyInterface::class] = new PaginationStrategy(3);

        $response = BoardsController::index($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());

        $body     = (string)($response->getBody());
        $resource = json_decode($body, true);

        // check reply has resource included
        $this->assertCount(7, $resource[DocumentInterface::KEYWORD_INCLUDED]);
        $this->assertCount(3, $resource[DocumentInterface::KEYWORD_DATA]);
        // check response sorted by post.id
        $this->assertEquals(1, $resource['data'][0]['id']);
        $this->assertEquals(2, $resource['data'][1]['id']);
        $this->assertEquals(3, $resource['data'][2]['id']);

        // manually checked that one of the elements should have paginated data in relationship
        $this->assertTrue(isset($resource['data'][2]['relationships']['posts-relationship']['links']['next']));
        $link = $resource['data'][2]['relationships']['posts-relationship']['links']['next'];
        $this->assertEquals('/boards/3/relationships/posts-relationship?skip=3&size=3', $link);
    }

    /**
     * Controller test.
     */
    public function testIncludeNullableRelationshipToItself()
    {
        $routeParams = [];
        $queryParams = [
            'include' => CategorySchema::REL_PARENT . ',' . CategorySchema::REL_CHILDREN,
        ];
        $container   = $this->createContainer();
        $uri         = new Uri('http://localhost.local/categories?' . http_build_query($queryParams));
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn($queryParams);
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn($uri);

        /** @var ServerRequestInterface $request */

        $response = CategoriesController::index($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());

        $body      = (string)($response->getBody());
        $resources = json_decode($body, true);

        // manually checked it should be 4 rows selected
        $this->assertCount(3, $resources[DocumentInterface::KEYWORD_DATA]);
        // check response sorted by post.id
        $this->assertNull($resources['data'][0]['relationships']['parent-relationship']['data']['id']);
        $this->assertCount(2, $resources['data'][0]['relationships']['children-relationship']['data']);
        $this->assertEquals(1, $resources['data'][1]['relationships']['parent-relationship']['data']['id']);
        $this->assertCount(0, $resources['data'][1]['relationships']['children-relationship']['data']);
        $this->assertEquals(1, $resources['data'][2]['relationships']['parent-relationship']['data']['id']);
        $this->assertCount(0, $resources['data'][2]['relationships']['children-relationship']['data']);
    }

    /**
     * Controller test.
     */
    public function testReadToOneRelationship()
    {
        $routeParams = [CommentsController::ROUTE_KEY_INDEX => '2'];
        $queryParams = [];
        $container   = $this->createContainer();
        $uri         = new Uri('http://localhost.local/comments/2/users?' . http_build_query($queryParams));
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn($queryParams);
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn($uri);

        /** @var ServerRequestInterface $request */

        // replace paging strategy to get paginated results in the relationship
        $container[PaginationStrategyInterface::class] = new PaginationStrategy(3);

        $response = CommentsController::readUser($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());

        $body     = (string)($response->getBody());
        $resource = json_decode($body, true);

        $this->assertNotEmpty(3, $resource);
        $this->assertEquals(5, $resource['data']['id']);
    }

    /**
     * Controller test.
     */
    public function testIndexWithHasManyFilter()
    {
        $routeParams = [];
        $queryParams = [
            'filter' => [
                UserSchema::REL_COMMENTS => [
                    'gt' => '1',
                    'lt' => '10',
                ],
                UserSchema::REL_POSTS => [
                    'gt' => '1',
                    'lt' => '7',
                ],
            ]
        ];
        $container   = $this->createContainer();
        $uri         = new Uri('http://localhost.local/comments?' . http_build_query($queryParams));
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn($queryParams);
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn($uri);

        /** @var ServerRequestInterface $request */

        $response = UsersController::index($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());

        $body     = (string)($response->getBody());
        $resource = json_decode($body, true);

        // manually checked it should be 2 rows
        // - comments with ID from 1 to 10 have user IDs 2, 4, 5
        // - posts with ID from 1 to 7 have user IDs 3, 4, 5
        // - therefore output must have 2 users with IDs 4 and 5
        $this->assertCount(2, $resource[DocumentInterface::KEYWORD_DATA]);
        $this->assertEquals(4, $resource['data'][0]['id']);
        $this->assertEquals(5, $resource['data'][1]['id']);
    }

    /**
     * Controller test.
     */
    public function testIndexWithBelongsToManyFilter()
    {
        $routeParams = [];
        // comments with ID 2 and 4 have more than 1 emotions. We will check that only distinct rows to be returned.
        $queryParams = [
            'filter' => [
                CommentSchema::RESOURCE_ID => [
                    'in' => ['2', '3', '4'],
                ],
                CommentSchema::REL_EMOTIONS => [
                    'in' => ['2', '3', '4'],
                ],
            ]
        ];
        $container   = $this->createContainer();
        $uri         = new Uri('http://localhost.local/comments?' . http_build_query($queryParams));
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn($queryParams);
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn($uri);

        /** @var ServerRequestInterface $request */

        try {
            // disable index filtering for this test
            CommentsApi::$isFilterIndexForCurrentUser = false;
            $response = CommentsController::index($routeParams, $container, $request);
        } finally {
            CommentsApi::$isFilterIndexForCurrentUser = CommentsApi::DEBUG_KEY_DEFAULT_FILTER_INDEX;
        }
        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());

        $body     = (string)($response->getBody());
        $resource = json_decode($body, true);

        // manually checked if rows are not distinct it would be 6 rows
        $this->assertCount(3, $resource[DocumentInterface::KEYWORD_DATA]);
        $this->assertEquals(2, $resource['data'][0]['id']);
        $this->assertEquals(3, $resource['data'][1]['id']);
        $this->assertEquals(4, $resource['data'][2]['id']);
    }

    /**
     * Controller test.
     */
    public function testCreate()
    {
        $text = 'Some comment text';
        $jsonInput = <<<EOT
        {
            "data" : {
                "type"  : "comments",
                "id"    : null,
                "attributes" : {
                    "text-attribute" : "$text"
                },
                "relationships" : {
                    "post-relationship" : {
                        "data" : { "type" : "posts", "id" : "1" }
                    },
                    "emotions-relationship" : {
                        "data" : [
                            { "type": "emotions", "id":"2" },
                            { "type": "emotions", "id":"3" }
                        ]
                    }
                }
            }
        }
EOT;

        $routeParams = [];
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getBody')->once()->withNoArgs()->andReturn($jsonInput);
        //$request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn([]);
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn(new Uri('http://localhost.local/comments'));

        // check the item is not in the database
        $tableName   = Comment::TABLE_NAME;
        $idColumn    = Comment::FIELD_ID;
        $index       = '101';
        $container   = $this->createContainer();
        /** @var Connection $connection */
        $connection  = $container->get(Connection::class);
        $this->assertEmpty($connection->executeQuery("SELECT * FROM $tableName WHERE $idColumn = $index")->fetch());

        /** @var ServerRequestInterface $request */

        $response = CommentsController::create($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(['/comments/101'], $response->getHeader('Location'));
        $this->assertNotEmpty((string)($response->getBody()));

        // check the item is in the database
        $this->assertNotEmpty($connection->executeQuery("SELECT * FROM $tableName WHERE $idColumn = $index")->fetch());
    }

    /**
     * Controller test.
     */
    public function testReadWithoutParameters()
    {
        $routeParams = [CommentsController::ROUTE_KEY_INDEX => '2'];
        $container   = $this->createContainer();
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn([]);
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn(new Uri('http://localhost.local/comments'));

        /** @var ServerRequestInterface $request */

        $response = CommentsController::read($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());

        $body     = (string)($response->getBody());
        $resource = json_decode($body, true)[DocumentInterface::KEYWORD_DATA];

        $this->assertEquals('comments', $resource['type']);
        $this->assertEquals('2', $resource['id']);
        $this->assertEquals([
            'user-relationship'     => ['data' => ['type' => 'users', 'id' => '5']],
            'post-relationship'     => ['data' => ['type' => 'posts', 'id' => '18']],
            'emotions-relationship' => ['links' => ['self' => '/comments/2/relationships/emotions-relationship']],
        ], $resource['relationships']);
    }

    /**
     * Controller test.
     */
    public function testUpdate()
    {
        $text      = 'Some comment text';
        $index     = '1';
        $jsonInput = <<<EOT
        {
            "data" : {
                "type"  : "comments",
                "id"    : "$index",
                "attributes" : {
                    "text-attribute" : "$text"
                },
                "relationships" : {
                    "post-relationship" : {
                        "data" : { "type" : "posts", "id" : "1" }
                    },
                    "emotions-relationship" : {
                        "data" : [
                            { "type": "emotions", "id":"2" },
                            { "type": "emotions", "id":"3" }
                        ]
                    }
                }
            }
        }
EOT;

        $routeParams = [CommentsController::ROUTE_KEY_INDEX => $index];
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getBody')->once()->withNoArgs()->andReturn($jsonInput);
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn(new Uri('http://localhost.local/comments'));

        /** @var ServerRequestInterface $request */

        $container = $this->createContainer();
        $response  = CommentsController::update($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());

        $this->assertNotEmpty($body = (string)($response->getBody()));
        $resource = json_decode($body, true)[DocumentInterface::KEYWORD_DATA];
        $this->assertEquals($text, $resource[DocumentInterface::KEYWORD_ATTRIBUTES][CommentSchema::ATTR_TEXT]);
        $this->assertNotEmpty($resource[DocumentInterface::KEYWORD_ATTRIBUTES][CommentSchema::ATTR_UPDATED_AT]);

        // check the item is in the database
        $tableName  = Comment::TABLE_NAME;
        $idColumn   = Comment::FIELD_ID;
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $this->assertNotEmpty(
            $row = $connection->executeQuery("SELECT * FROM $tableName WHERE $idColumn = $index")->fetch()
        );
        $this->assertEquals(1, $row[Comment::FIELD_ID_POST]);
        $tableName  = CommentEmotion::TABLE_NAME;
        $idColumn   = CommentEmotion::FIELD_ID_COMMENT;
        $columnName = CommentEmotion::FIELD_ID_EMOTION;
        $emotionIds =
            $connection->executeQuery("SELECT $columnName FROM $tableName WHERE $idColumn = $index")->fetchAll();
        $this->assertEquals(
            [[CommentEmotion::FIELD_ID_EMOTION => '2'], [CommentEmotion::FIELD_ID_EMOTION => '3']],
            $emotionIds
        );
    }

    /**
     * Controller test.
     */
    public function testUpdateNonExistingItem()
    {
        $text      = 'Some comment text';
        $index     = '-1';
        $jsonInput = <<<EOT
        {
            "data" : {
                "type"  : "comments",
                "id"    : "$index",
                "attributes" : {
                    "text-attribute" : "$text"
                }
            }
        }
EOT;

        $routeParams = [CommentsController::ROUTE_KEY_INDEX => $index];
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getBody')->once()->withNoArgs()->andReturn($jsonInput);
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn(new Uri('http://localhost.local/comments'));

        /** @var ServerRequestInterface $request */

        $container = $this->createContainer();
        $response  = CommentsController::update($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(404, $response->getStatusCode());
    }

    /**
     * Controller test.
     */
    public function testSendInvalidInput()
    {
        $index     = '1';
        $jsonInput = '{';

        $routeParams = [CommentsController::ROUTE_KEY_INDEX => $index];
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getBody')->once()->withNoArgs()->andReturn($jsonInput);

        /** @var ServerRequestInterface $request */

        $exception  = null;
        try {
            CommentsController::update($routeParams, $this->createContainer(), $request);
        } catch (JsonApiException $exception) {
        }
        $this->assertNotNull($exception);

        $this->assertCount(1, $errors = $exception->getErrors());
        $this->assertEquals(['pointer' => '/data'], $errors[0]->getSource());
    }

    /**
     * Controller test.
     */
    public function testDelete()
    {
        $tableName = Comment::TABLE_NAME;
        $idColumn  = Comment::FIELD_ID;

        $container   = $this->createContainer();
        /** @var Connection $connection */
        $connection  = $container->get(Connection::class);

        // add comment to delete
        $this->assertEquals(1, $connection->insert($tableName, [
            Comment::FIELD_TEXT       => 'Some text',
            Comment::FIELD_ID_USER    => '1',
            Comment::FIELD_ID_POST    => '2',
            Comment::FIELD_CREATED_AT => '2000-01-02',
        ]));
        $index = $connection->lastInsertId();

        // check the item is in the database
        $this->assertNotEmpty($connection->executeQuery("SELECT * FROM $tableName WHERE $idColumn = $index")->fetch());

        $routeParams = [CommentsController::ROUTE_KEY_INDEX => $index];

        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn(new Uri('http://localhost.local/comments'));

        /** @var ServerRequestInterface $request */

        $response = CommentsController::delete($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(204, $response->getStatusCode());

        // check the item is not in the database
        $this->assertFalse($connection->executeQuery("SELECT * FROM $tableName WHERE $idColumn = $index")->fetch());
    }

    /**
     * Controller test.
     */
    public function testReadRelationship()
    {
        $index       = '2';
        $routeParams = [CommentsController::ROUTE_KEY_INDEX => $index];
        $queryParams = [
            'sort' => '+' . EmotionSchema::ATTR_NAME,
        ];
        $container   = $this->createContainer();
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn($queryParams);
        $uri = new Uri('http://localhost.local/comments/relationships/emotions?' . http_build_query($queryParams));
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn($uri);

        /** @var ServerRequestInterface $request */

        $response = CommentsController::readEmotions($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());

        $body     = (string)($response->getBody());
        $resource = json_decode($body, true);

        $this->assertCount(4, $resource[DocumentInterface::KEYWORD_DATA]);
        // manually checked that emotions should have these ids and sorted by name in ascending order.
        $this->assertEquals('4', $resource['data'][0]['id']);
        $this->assertEquals('5', $resource['data'][1]['id']);
        $this->assertEquals('3', $resource['data'][2]['id']);
        $this->assertEquals('2', $resource['data'][3]['id']);
    }

    /**
     * Controller test.
     */
    public function testReadRelationshipIdentifiers()
    {
        $index       = '2';
        $routeParams = [CommentsController::ROUTE_KEY_INDEX => $index];
        $queryParams = [
            'sort' => '+' . EmotionSchema::ATTR_NAME,
        ];
        $container   = $this->createContainer();
        /** @var Mock $request */
        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getQueryParams')->once()->withNoArgs()->andReturn($queryParams);
        $uri = new Uri('http://localhost.local/comments/relationships/emotions?' . http_build_query($queryParams));
        $request->shouldReceive('getUri')->once()->withNoArgs()->andReturn($uri);

        /** @var ServerRequestInterface $request */

        $response = CommentsController::readEmotionsIdentifiers($routeParams, $container, $request);
        $this->assertNotNull($response);
        $this->assertEquals(200, $response->getStatusCode());

        $body     = (string)($response->getBody());
        $resource = json_decode($body, true);

        $this->assertCount(4, $resource[DocumentInterface::KEYWORD_DATA]);
        // manually checked that emotions should have these ids and sorted by name in ascending order.
        $this->assertEquals('4', $resource['data'][0]['id']);
        $this->assertEquals('5', $resource['data'][1]['id']);
        $this->assertEquals('3', $resource['data'][2]['id']);
        $this->assertEquals('2', $resource['data'][3]['id']);

        // check we have only IDs in response (no attributes)
        $this->assertArrayNotHasKey(DocumentInterface::KEYWORD_ATTRIBUTES, $resource['data'][0]);
        $this->assertArrayNotHasKey(DocumentInterface::KEYWORD_ATTRIBUTES, $resource['data'][1]);
        $this->assertArrayNotHasKey(DocumentInterface::KEYWORD_ATTRIBUTES, $resource['data'][2]);
        $this->assertArrayNotHasKey(DocumentInterface::KEYWORD_ATTRIBUTES, $resource['data'][3]);
    }

    /**
     * @return Container
     */
    private function createContainer()
    {
        $container = new Container();

        $container[FactoryInterface::class]               = $factory = new Factory();
        $container[QueryParametersParserInterface::class] = $factory
            ->getJsonApiFactory()->createQueryParametersParser();
        $container[ModelSchemesInterface::class]       = $modelSchemes = $this->getModelSchemes();
        /** @var RelationshipStorageInterface $storage */
        $storage                                       = null;
        $container[JsonSchemesInterface::class]        = $jsonSchemes = $this->getJsonSchemes($modelSchemes, $storage);
        $container[Connection::class]                  = $connection = $this->initDb();
        $container[TranslatorInterface::class]         = $translator = $factory->createTranslator();
        $container[FilterOperationsInterface::class]   = $filterOperations = new FilterOperations($translator);
        $container[PaginationStrategyInterface::class] = new PaginationStrategy(10);
        $container[RepositoryInterface::class]         = $repository = $factory->createRepository(
            $connection,
            $modelSchemes,
            $filterOperations,
            $translator
        );
        $container[JsonApiConfigInterface::class]        = $config = $this->createJsonApiConfig();

        $container[EncoderInterface::class] = function () use ($config, $factory, $jsonSchemes) {
            $encoderConfig = $config->getConfig()[JsonApiConfigInterface::KEY_JSON];

            $urlPrefix = $encoderConfig[JsonApiConfigInterface::KEY_JSON_URL_PREFIX];
            $encoder   = $factory->createEncoder($jsonSchemes, new EncoderOptions(
                $encoderConfig[JsonApiConfigInterface::KEY_JSON_OPTIONS],
                $urlPrefix,
                $encoderConfig[JsonApiConfigInterface::KEY_JSON_DEPTH]
            ));
            if (isset($encoderConfig[JsonApiConfigInterface::KEY_JSON_VERSION_META]) === true) {
                $meta = $encoderConfig[JsonApiConfigInterface::KEY_JSON_VERSION_META];
                $encoder->withMeta($meta);
            }
            if (isset($encoderConfig[JsonApiConfigInterface::KEY_JSON_IS_SHOW_VERSION]) === true &&
                $encoderConfig[JsonApiConfigInterface::KEY_JSON_IS_SHOW_VERSION] === true
            ) {
                $encoder->withJsonApiVersion();
            }

            return $encoder;
        };

        $container[ValidationTranslatorInterface::class] = $validationTranslator =
            new ValidationTranslator(EnUsLocale::getLocaleCode(), EnUsLocale::getMessages());

        $container[AppValidator::class] =
            new AppValidator($translator, $validationTranslator, $jsonSchemes, $modelSchemes, $connection);

        return $container;
    }

    /**
     * @return JsonApiConfigInterface
     */
    private function createJsonApiConfig()
    {
        $config = new JsonApiConfig();
        $config
            ->setModelSchemaMap($this->getSchemeMap())
            ->setRelationshipPagingSize(20)
            ->setJsonEncodeOptions($config->getJsonEncodeOptions() | JSON_PRETTY_PRINT)
            ->setHideVersion();

        return $config;
    }
}
