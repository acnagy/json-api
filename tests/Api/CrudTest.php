<?php namespace Limoncello\Tests\JsonApi\Api;

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
use Doctrine\DBAL\DriverManager;
use Limoncello\JsonApi\Adapters\FilterOperations;
use Limoncello\JsonApi\Adapters\PaginationStrategy;
use Limoncello\JsonApi\Contracts\Adapters\PaginationStrategyInterface;
use Limoncello\JsonApi\Contracts\Api\CrudInterface;
use Limoncello\JsonApi\Contracts\Document\ParserInterface;
use Limoncello\JsonApi\Factory;
use Limoncello\Tests\JsonApi\Data\Api\CommentsApi;
use Limoncello\Tests\JsonApi\Data\Api\PostsApi;
use Limoncello\Tests\JsonApi\Data\Api\UsersApi;
use Limoncello\Tests\JsonApi\Data\Migrations\Runner as MigrationRunner;
use Limoncello\Tests\JsonApi\Data\Models\Board;
use Limoncello\Tests\JsonApi\Data\Models\Comment;
use Limoncello\Tests\JsonApi\Data\Models\CommentEmotion;
use Limoncello\Tests\JsonApi\Data\Models\Post;
use Limoncello\Tests\JsonApi\Data\Models\User;
use Limoncello\Tests\JsonApi\Data\Seeds\Runner as SeedRunner;
use Limoncello\Tests\JsonApi\Data\Transformers\CommentOnCreate;
use Limoncello\Tests\JsonApi\Data\Transformers\CommentOnUpdate;
use Limoncello\Tests\JsonApi\Data\Transformers\PostOnCreate;
use Limoncello\Tests\JsonApi\TestCase;
use Neomerx\JsonApi\Contracts\Document\DocumentInterface;
use Neomerx\JsonApi\Contracts\Document\ErrorInterface;
use Neomerx\JsonApi\Exceptions\JsonApiException;
use PDO;

/**
 * @package Limoncello\Tests\JsonApi
 */
class CrudTest extends TestCase
{
    const DEFAULT_PAGE = 3;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * Set up tests.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->init();
    }

    /**
     * @return $this
     */
    public function init()
    {
        $this->connection = DriverManager::getConnection(['url' => 'sqlite:///:memory:']);
        $this->assertNotSame(false, $this->connection->exec('PRAGMA foreign_keys = ON;'));

        (new MigrationRunner())->migrate($this->connection->getSchemaManager());
        (new SeedRunner())->run($this->connection);

        return $this;
    }

    /**
     * Test create read and delete newly created resource.
     */
    public function testCreateReadAndDeletePost()
    {
        $userId  = 1;
        $boardId = 2;
        $text    = 'Some text';
        $title   = 'Some title';
        $json = <<<EOT
        {
            "data" : {
                "type"       : "posts",
                "id"         : null,
                "attributes" : {
                    "title-attribute" : "$title",
                    "text-attribute"  : "$text"
                },
                "relationships" : {
                    "user-relationship" : {
                        "data" : { "type" : "users", "id" : $userId }
                    },
                    "board-relationship" : {
                        "data" : { "type" : "boards", "id" : $boardId }
                    }
                }
            }
        }
EOT;

        $crud = $this->createCrud(PostsApi::class);

        $parser   = $this->createPostParserForCreate();
        $resource = $parser->parse($json);
        $this->assertNotNull($index = $crud->create($resource));
        $this->assertNotNull($data = $crud->read($index));
        $this->assertNotNull($model = $data->getPaginatedData()->getData());

        /** @var Post $model */

        $this->assertEquals($userId, $model->{Post::FIELD_ID_USER});
        $this->assertEquals($boardId, $model->{Post::FIELD_ID_BOARD});
        $this->assertEquals($title, $model->{Post::FIELD_TITLE});
        $this->assertEquals($text, $model->{Post::FIELD_TEXT});
        $this->assertNotEmpty($index = $model->{Post::FIELD_ID});

        $this->assertNotNull($data = $crud->read($index));
        $this->assertNotNull($data->getPaginatedData()->getData());

        $crud->delete($index);

        $this->assertNotNull($data = $crud->read($index));
        $this->assertNull($data->getPaginatedData()->getData());

        // second delete does nothing (already deleted)
        $crud->delete($index);
    }

    /**
     * Test create resource with to-many (belongs-to-many relationships).
     */
    public function testCreateCommentsWithEmotions()
    {
        $userId = 1;
        $postId = 2;
        $text   = 'Some text';
        $json = <<<EOT
        {
            "data" : {
                "type"       : "comments",
                "id"         : null,
                "attributes" : {
                    "text-attribute" : "$text"
                },
                "relationships" : {
                    "user-relationship" : {
                        "data" : { "type" : "users", "id" : $userId }
                    },
                    "post-relationship" : {
                        "data" : { "type" : "posts", "id" : $postId }
                    },
                    "emotions-relationship" : {
                        "data" : [{ "type" : "emotions", "id" : 3 }, { "type" : "emotions", "id" : "4" }]
                    }
                }
            }
        }
EOT;
        $crud = $this->createCrud(CommentsApi::class);

        $parser   = $this->createCommentParserForCreate();
        $resource = $parser->parse($json);
        $this->assertLessThanOrEqual(0, $parser->getErrors()->count());
        $this->assertNotNull($index = $crud->create($resource));
        $this->assertNotNull($data = $crud->read($index));
        $this->assertNotNull($model = $data->getPaginatedData()->getData());

        /** @var Comment $model */

        $this->assertEquals($userId, $model->{Comment::FIELD_ID_USER});
        $this->assertEquals($postId, $model->{Comment::FIELD_ID_POST});
        $this->assertEquals($text, $model->{Comment::FIELD_TEXT});
        $this->assertNotEmpty($index = $model->{Comment::FIELD_ID});

        // check resources is saved
        $res = $this->connection
            ->query('SELECT * FROM ' . Comment::TABLE_NAME . ' WHERE ' . Comment::FIELD_ID . " = $index")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEquals(false, $res);
        $this->assertEquals($userId, $res[Comment::FIELD_ID_USER]);
        $this->assertEquals($postId, $res[Comment::FIELD_ID_POST]);
        // check resource to-many relationship are saved
        $res = $this->connection->query(
            'SELECT * FROM ' . CommentEmotion::TABLE_NAME . ' WHERE ' . CommentEmotion::FIELD_ID_COMMENT . " = $index"
        )->fetchAll(PDO::FETCH_ASSOC);
        $this->assertNotEquals(false, $res);
        $this->assertCount(2, $res);

        // same checks but this time via API
        $includePaths = [
            Comment::REL_USER,
            Comment::REL_POST,
            Comment::REL_EMOTIONS,
        ];
        $this->assertNotNull($data = $crud->read($index, null, $includePaths));
        $this->assertNotNull($comment = $data->getPaginatedData()->getData());
        $this->assertNotEmpty($relationships = $data->getRelationshipStorage());
        $this->assertEquals(
            $userId,
            $relationships->getRelationship($comment, Comment::REL_USER)->getData()->{User::FIELD_ID}
        );
        $this->assertEquals(
            $postId,
            $relationships->getRelationship($comment, Comment::REL_POST)->getData()->{Post::FIELD_ID}
        );
        $emotions = $relationships->getRelationship($comment, Comment::REL_EMOTIONS);
        $this->assertCount(2, $emotions->getData());
        $this->assertFalse($emotions->hasMoreItems());
        $this->assertSame(null, $emotions->getOffset());
        $this->assertSame(null, $emotions->getSize());
    }

    /**
     * Test update resource with to-many (belongs-to-many relationships).
     */
    public function testUpdateCommentsWithEmotions()
    {
        $commentId = 1;
        $userId    = 2;
        $postId    = 3;
        $text      = 'Some text';
        $json = <<<EOT
        {
            "data" : {
                "type"       : "comments",
                "id"         : $commentId,
                "attributes" : {
                    "text-attribute" : "$text"
                },
                "relationships" : {
                    "user-relationship" : {
                        "data" : { "type" : "users", "id" : $userId }
                    },
                    "post-relationship" : {
                        "data" : { "type" : "posts", "id" : $postId }
                    },
                    "emotions-relationship" : {
                        "data" : [{ "type" : "emotions", "id" : 3 }, { "type" : "emotions", "id" : "4" }]
                    }
                }
            }
        }
EOT;
        $crud = $this->createCrud(CommentsApi::class);

        $parser   = $this->createCommentParserForUpdate();
        $resource = $parser->parse($json);
        $this->assertLessThanOrEqual(0, $parser->getErrors()->count());
        $crud->update($commentId, $resource);
        $this->assertNotNull($data = $crud->read($commentId)->getPaginatedData());
        $this->assertNotNull($model = $data->getData());

        /** @var Comment $model */

        $this->assertEquals($userId, $model->{Comment::FIELD_ID_USER});
        $this->assertEquals($postId, $model->{Comment::FIELD_ID_POST});
        $this->assertEquals($text, $model->{Comment::FIELD_TEXT});
        $this->assertNotEmpty($index = $model->{Comment::FIELD_ID});

        $includePaths = [
            Comment::REL_USER,
            Comment::REL_POST,
            Comment::REL_EMOTIONS,
        ];
        $this->assertNotNull($data = $crud->read($index, null, $includePaths));
        $this->assertNotNull($comment = $data->getPaginatedData()->getData());
        $this->assertNotEmpty($relationships = $data->getRelationshipStorage());
        $this->assertEquals(
            $userId,
            $relationships->getRelationship($comment, Comment::REL_USER)->getData()->{User::FIELD_ID}
        );
        $this->assertEquals(
            $postId,
            $relationships->getRelationship($comment, Comment::REL_POST)->getData()->{Post::FIELD_ID}
        );
        $emotions = $relationships->getRelationship($comment, Comment::REL_EMOTIONS);
        $this->assertCount(2, $emotions->getData());
        $this->assertFalse($emotions->hasMoreItems());
        $this->assertSame(null, $emotions->getOffset());
        $this->assertSame(null, $emotions->getSize());
    }

    /**
     * @expectedException \Doctrine\DBAL\Exception\DriverException
     */
    public function testDeleteResourceWithConstraints()
    {
        $crud = $this->createCrud(PostsApi::class);
        $crud->delete(1);
    }

    /**
     * Check 'read' with included paths.
     */
    public function testReadWithIncludes()
    {
        $crud = $this->createCrud(PostsApi::class);

        $index        = 18;
        $s            = DocumentInterface::PATH_SEPARATOR;
        $includePaths = [
            Post::REL_BOARD,
            Post::REL_COMMENTS,
            Post::REL_COMMENTS . $s . Comment::REL_EMOTIONS,
            Post::REL_COMMENTS . $s . Comment::REL_POST . $s . Post::REL_USER,
        ];
        $data = $crud->read($index, null, $includePaths);
        $this->assertNotNull($data);
        $this->assertNotNull($model = $data->getPaginatedData()->getData());
        $this->assertFalse($data->getPaginatedData()->isCollection());
        $this->assertNotEmpty($relationships = $data->getRelationshipStorage());

        $board = $relationships->getRelationship($model, Post::REL_BOARD)->getData();
        $this->assertEquals(Board::class, get_class($board));
        $this->assertEquals($model->{Post::FIELD_ID_BOARD}, $board->{Board::FIELD_ID});

        $commentsRel = $relationships->getRelationship($model, Post::REL_COMMENTS);
        $comments    = $commentsRel->getData();
        $hasMore     = $commentsRel->hasMoreItems();
        $offset      = $commentsRel->getOffset();
        $limit       = $commentsRel->getSize();
        $this->assertNotEmpty($comments);
        $this->assertCount(3, $comments);
        $this->assertEquals(Comment::class, get_class($comments[0]));
        $this->assertEquals($index, $comments[0]->{Comment::FIELD_ID_POST});
        $this->assertTrue($hasMore);
        $this->assertCount(self::DEFAULT_PAGE, $comments);
        $this->assertEquals(0, $offset);
        $this->assertEquals(self::DEFAULT_PAGE, $limit);

        $emotions = $relationships->getRelationship($comments[0], Comment::REL_EMOTIONS);
        $this->assertCount(3, $emotions->getData());
        $this->assertTrue($emotions->hasMoreItems());
        $this->assertEquals(0, $emotions->getOffset());
        $this->assertEquals(self::DEFAULT_PAGE, $emotions->getSize());

        $emotions = $relationships->getRelationship($comments[1], Comment::REL_EMOTIONS);
        $this->assertCount(1, $emotions->getData());
        $this->assertFalse($emotions->hasMoreItems());
        $this->assertSame(null, $emotions->getOffset());
        $this->assertSame(null, $emotions->getSize());

        $comment = $comments[2];
        $emotions = $relationships->getRelationship($comment, Comment::REL_EMOTIONS);
        $this->assertCount(1, $emotions->getData());
        $this->assertFalse($emotions->hasMoreItems());
        $this->assertSame(null, $emotions->getOffset());
        $this->assertSame(null, $emotions->getSize());

        $this->assertNotNull($post = $relationships->getRelationship($comment, Comment::REL_POST)->getData());
        $this->assertNotNull($user = $relationships->getRelationship($post, Post::REL_USER)->getData());

        // check no data for relationships we didn't asked to download
        $this->assertFalse($relationships->hasRelationship($user, User::REL_ROLE));
        $this->assertFalse($relationships->hasRelationship($user, User::REL_COMMENTS));
    }

    /**
     * Test index.
     */
    public function testIndex()
    {
        $crud = $this->createCrud(PostsApi::class);
        $s    = DocumentInterface::PATH_SEPARATOR;

        $includePaths = [
            Post::REL_BOARD,
            Post::REL_COMMENTS,
            Post::REL_COMMENTS . $s . Comment::REL_EMOTIONS,
            Post::REL_COMMENTS . $s . Comment::REL_POST . $s . Post::REL_USER,
        ];
        $sortParameters   = [
            Post::FIELD_ID_BOARD => false,
            Post::FIELD_TITLE    => true,
        ];
        $pagingOffset     = 1;
        $pagingSize       = 2;
        $pagingParameters = [
            PaginationStrategyInterface::PARAM_PAGING_SKIP => $pagingOffset,
            PaginationStrategyInterface::PARAM_PAGING_SIZE => $pagingSize,
        ];
        $filteringParameters = [
            Post::FIELD_TITLE   => ['like' => ['%', '%']],
            Post::FIELD_ID_USER => ['lt'   => '5'],
        ];

        $data = $crud->index($filteringParameters, $sortParameters, $includePaths, $pagingParameters);

        $this->assertNotEmpty($data->getPaginatedData()->getData());
        $this->assertCount($pagingSize, $data->getPaginatedData()->getData());
        $this->assertEquals(20, $data->getPaginatedData()->getData()[0]->{Post::FIELD_ID});
        $this->assertEquals(9, $data->getPaginatedData()->getData()[1]->{Post::FIELD_ID});
        $this->assertTrue($data->getPaginatedData()->isCollection());
        $this->assertEquals($pagingOffset, $data->getPaginatedData()->getOffset());
        $this->assertEquals($pagingSize, $data->getPaginatedData()->getSize());

        return [$data, $filteringParameters, $sortParameters, $includePaths, $pagingParameters];
    }

    /**
     * Test index.
     */
    public function testCommentsIndex()
    {
        // check that API returns comments from only specific user (as configured in Comments API)
        $expectedUserId = 1;

        $crud = $this->createCrud(CommentsApi::class);

        $data = $crud->index();

        $this->assertNotEmpty($comments = $data->getPaginatedData()->getData());
        foreach ($comments as $comment) {
            $this->assertEquals($expectedUserId, $comment->{Comment::FIELD_ID_USER});
        }
    }

    /**
     * Test read relationship.
     */
    public function testReadRelationship()
    {
        $crud = $this->createCrud(PostsApi::class);

        $sortParameters   = [
            Comment::FIELD_ID_USER => false,
            Comment::FIELD_TEXT    => true,
        ];
        $pagingOffset     = 1;
        $pagingSize       = 2;
        $pagingParameters = [
            PaginationStrategyInterface::PARAM_PAGING_SKIP => $pagingOffset,
            PaginationStrategyInterface::PARAM_PAGING_SIZE => $pagingSize,
        ];
        $filterParameters = [
            Comment::FIELD_ID_USER => ['lt'   => '5'],
            Comment::FIELD_TEXT    => ['like' => '%'],
        ];

        $data = $crud->readRelationship(
            1,
            Post::REL_COMMENTS,
            $filterParameters,
            $sortParameters,
            $pagingParameters
        );

        $this->assertNotEmpty($data->getData());
        $this->assertCount($pagingSize, $data->getData());
        $this->assertEquals(9, $data->getData()[0]->{Comment::FIELD_ID});
        $this->assertEquals(85, $data->getData()[1]->{Comment::FIELD_ID});
        $this->assertTrue($data->isCollection());
        $this->assertEquals($pagingOffset, $data->getOffset());
        $this->assertEquals($pagingSize, $data->getSize());
    }

    /**
     * Test index.
     */
    public function testIndexWithFilterByBooleanColumn()
    {
        $crud = $this->createCrud(UsersApi::class);

        $filteringParameters = [
            User::FIELD_IS_ACTIVE => ['eq' => '1'],
        ];

        $data  = $crud->index($filteringParameters);
        $users = $data->getPaginatedData()->getData();
        $this->assertNotEmpty($users);

        $query   = 'SELECT COUNT(*) FROM ' . User::TABLE_NAME . ' WHERE ' . User::FIELD_IS_ACTIVE . ' = 1';
        $actives = $this->connection->query($query)->fetch(PDO::FETCH_NUM)[0];

        $this->assertEquals($actives, count($users));
    }

    /**
     * Test index.
     */
    public function testIndexWithEqualsOperator()
    {
        $crud = $this->createCrud(PostsApi::class);

        $index = 2;
        $filteringParameters = [
            Post::FIELD_ID => ['eq' => $index],
        ];

        $data = $crud->index($filteringParameters);

        $this->assertNotEmpty($data->getPaginatedData()->getData());
        $this->assertCount(1, $data->getPaginatedData()->getData());
        $this->assertEquals($index, $data->getPaginatedData()->getData()[0]->{Post::FIELD_ID});
        $this->assertTrue($data->getPaginatedData()->isCollection());
    }

    /**
     * Test index
     */
    public function testIndexWithInvalidPrimaryFilters()
    {
        $crud = $this->createCrud(PostsApi::class);

        $filteringParameters = [
            Post::REL_USER => ['CCC'  => '5'],
        ];

        $exception = null;
        $gotError  = false;
        try {
            $crud->index($filteringParameters);
        } catch (JsonApiException $exception) {
            $gotError = true;
        }

        $this->assertTrue($gotError);
        $errors = $exception->getErrors();
        $this->assertCount(1, $errors);

        $this->assertEquals('user', $errors[0]->getSource()[ErrorInterface::SOURCE_PARAMETER]);
        $this->assertEquals('ccc', $errors[0]->getDetail());
    }

    /**
     * Test index
     */
    public function testIndexWithInvalidIncludePath()
    {
        $crud = $this->createCrud(PostsApi::class);

        $includePaths = [
            Post::REL_COMMENTS . '.XXX',
        ];

        $exception = null;
        $gotError  = false;
        try {
            $crud->read(1, null, $includePaths);
        } catch (JsonApiException $exception) {
            $gotError = true;
        }

        $this->assertTrue($gotError);
        $errors = $exception->getErrors();
        $this->assertCount(1, $errors);

        $this->assertEquals('XXX', $errors[0]->getSource()[ErrorInterface::SOURCE_PARAMETER]);
    }

    /**
     * @param string $class
     *
     * @return CrudInterface
     */
    private function createCrud($class)
    {
        $factory          = new Factory();
        $translator       = $factory->createTranslator();
        $filterOperations = new FilterOperations($translator);
        $modelSchemes     = $this->getModelSchemes();
        $repository       = $factory->createRepository(
            $this->connection,
            $modelSchemes,
            $filterOperations,
            $translator
        );

        $relPaging = new PaginationStrategy(self::DEFAULT_PAGE);
        $crud      = new $class($factory, $repository, $modelSchemes, $relPaging, $translator);

        return $crud;
    }

    /**
     * @return ParserInterface
     */
    private function createPostParserForCreate()
    {
        $factory        = new Factory();
        $modelSchemes   = $this->getModelSchemes();
        $translator     = $factory->createTranslator();
        $checksOnCreate = new PostOnCreate(
            $this->getJsonSchemes($modelSchemes),
            $modelSchemes,
            $factory->createTranslator()
        );

        return $factory->createParser($checksOnCreate, $translator);
    }

    /**
     * @return ParserInterface
     */
    private function createCommentParserForCreate()
    {
        $factory        = new Factory();
        $modelSchemes   = $this->getModelSchemes();
        $translator     = $factory->createTranslator();
        $checksOnCreate = new CommentOnCreate(
            $this->getJsonSchemes($modelSchemes),
            $modelSchemes,
            $factory->createTranslator()
        );

        return $factory->createParser($checksOnCreate, $translator);
    }

    /**
     * @return ParserInterface
     */
    private function createCommentParserForUpdate()
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
}
