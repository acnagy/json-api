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
use Limoncello\JsonApi\Adapters\FilterOperations;
use Limoncello\JsonApi\Adapters\PaginationStrategy;
use Limoncello\JsonApi\Contracts\Adapters\PaginationStrategyInterface;
use Limoncello\JsonApi\Contracts\Api\CrudInterface;
use Limoncello\JsonApi\Contracts\Http\Query\SortParameterInterface;
use Limoncello\JsonApi\Factory;
use Limoncello\JsonApi\Http\Query\FilterParameter;
use Limoncello\JsonApi\Http\Query\FilterParameterCollection;
use Limoncello\JsonApi\Http\Query\IncludeParameter;
use Limoncello\JsonApi\Http\Query\SortParameter;
use Limoncello\JsonApi\Models\RelationshipTypes;
use Limoncello\Tests\JsonApi\Data\Api\CommentsApi;
use Limoncello\Tests\JsonApi\Data\Api\PostsApi;
use Limoncello\Tests\JsonApi\Data\Api\UsersApi;
use Limoncello\Tests\JsonApi\Data\Models\Board;
use Limoncello\Tests\JsonApi\Data\Models\Comment;
use Limoncello\Tests\JsonApi\Data\Models\CommentEmotion;
use Limoncello\Tests\JsonApi\Data\Models\Post;
use Limoncello\Tests\JsonApi\Data\Models\User;
use Limoncello\Tests\JsonApi\Data\Schemes\CommentSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\PostSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\UserSchema;
use Limoncello\Tests\JsonApi\TestCase;
use Neomerx\JsonApi\Contracts\Document\DocumentInterface;
use Neomerx\JsonApi\Contracts\Document\ErrorInterface;
use Neomerx\JsonApi\Encoder\Parameters\SortParameter as JsonLibrarySortParameter;
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
     * Test create read and delete newly created resource.
     */
    public function testCreateReadAndDeletePost()
    {
        $userId     = 1;
        $boardId    = 2;
        $text       = 'Some text';
        $title      = 'Some title';
        $attributes = [
            Post::FIELD_TITLE    => $title,
            Post::FIELD_TEXT     => $text,
            Post::FIELD_ID_BOARD => $boardId,
            Post::FIELD_ID_USER  => $userId,
        ];

        $crud = $this->createCrud(PostsApi::class);

        $this->assertNotNull($index = $crud->create($attributes));
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
        $userId     = 1;
        $postId     = 2;
        $text       = 'Some text';
        $attributes = [
            Comment::FIELD_TEXT    => $text,
            Comment::FIELD_ID_POST => $postId,
            Comment::FIELD_ID_USER => $userId,
        ];
        $toMany     = [
            Comment::REL_EMOTIONS => ['3', '4'],
        ];

        $crud = $this->createCrud(CommentsApi::class);

        $this->assertNotNull($index = $crud->create($attributes, $toMany));
        $this->assertNotNull($data = $crud->read($index));
        $this->assertNotNull($model = $data->getPaginatedData()->getData());

        /** @var Comment $model */

        $this->assertEquals($userId, $model->{Comment::FIELD_ID_USER});
        $this->assertEquals($postId, $model->{Comment::FIELD_ID_POST});
        $this->assertEquals($text, $model->{Comment::FIELD_TEXT});
        $this->assertNotEmpty($index = $model->{Comment::FIELD_ID});

        // check resources is saved
        /** @noinspection SqlDialectInspection */
        $res = $this->connection
            ->query('SELECT * FROM ' . Comment::TABLE_NAME . ' WHERE ' . Comment::FIELD_ID . " = $index")
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertNotEquals(false, $res);
        $this->assertEquals($userId, $res[Comment::FIELD_ID_USER]);
        $this->assertEquals($postId, $res[Comment::FIELD_ID_POST]);
        // check resource to-many relationship are saved
        /** @noinspection SqlDialectInspection */
        $res = $this->connection->query(
            'SELECT * FROM ' . CommentEmotion::TABLE_NAME . ' WHERE ' . CommentEmotion::FIELD_ID_COMMENT . " = $index"
        )->fetchAll(PDO::FETCH_ASSOC);
        $this->assertNotEquals(false, $res);
        $this->assertCount(2, $res);

        // same checks but this time via API
        $includePaths = [
            new IncludeParameter(CommentSchema::REL_USER, [Comment::REL_USER]),
            new IncludeParameter(CommentSchema::REL_POST, [Comment::REL_POST]),
            new IncludeParameter(CommentSchema::REL_EMOTIONS, [Comment::REL_EMOTIONS]),
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
        $this->assertSame(null, $emotions->getLimit());
    }

    /**
     * Test update resource with to-many (belongs-to-many relationships).
     */
    public function testUpdateCommentsWithEmotions()
    {
        $commentId  = 1;
        $userId     = 2;
        $postId     = 3;
        $text       = 'Some text';
        $attributes = [
            Comment::FIELD_TEXT    => $text,
            Comment::FIELD_ID_POST => $postId,
            Comment::FIELD_ID_USER => $userId,
        ];
        $toMany     = [
            Comment::REL_EMOTIONS => ['3', '4'],
        ];

        $crud = $this->createCrud(CommentsApi::class);

        $changedRecords = $crud->update($commentId, $attributes, $toMany);
        $this->assertEquals(3, $changedRecords);
        $this->assertNotNull($data = $crud->read($commentId)->getPaginatedData());
        $this->assertNotNull($model = $data->getData());

        /** @var Comment $model */

        $this->assertEquals($userId, $model->{Comment::FIELD_ID_USER});
        $this->assertEquals($postId, $model->{Comment::FIELD_ID_POST});
        $this->assertEquals($text, $model->{Comment::FIELD_TEXT});
        $this->assertNotEmpty($index = $model->{Comment::FIELD_ID});

        $includePaths = [
            new IncludeParameter(CommentSchema::REL_USER, [Comment::REL_USER]),
            new IncludeParameter(CommentSchema::REL_POST, [Comment::REL_POST]),
            new IncludeParameter(CommentSchema::REL_EMOTIONS, [Comment::REL_EMOTIONS]),
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
        $this->assertSame(null, $emotions->getLimit());
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
        $jsonPath1    = PostSchema::REL_COMMENTS . $s . CommentSchema::REL_EMOTIONS;
        $modelPath1   = [Post::REL_COMMENTS, Comment::REL_EMOTIONS];
        $jsonPath2    = PostSchema::REL_COMMENTS . $s . CommentSchema::REL_POST . $s . PostSchema::REL_USER;
        $modelPath2   = [Post::REL_COMMENTS, Comment::REL_POST, Post::REL_USER];
        $includePaths = [
            new IncludeParameter(PostSchema::REL_BOARD, [Post::REL_BOARD]),
            new IncludeParameter(PostSchema::REL_COMMENTS, [Post::REL_COMMENTS]),
            new IncludeParameter($jsonPath1, $modelPath1),
            new IncludeParameter($jsonPath2, $modelPath2),
        ];
        $data         = $crud->read($index, null, $includePaths);
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
        $limit       = $commentsRel->getLimit();
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
        $this->assertEquals(self::DEFAULT_PAGE, $emotions->getLimit());

        $emotions = $relationships->getRelationship($comments[1], Comment::REL_EMOTIONS);
        $this->assertCount(1, $emotions->getData());
        $this->assertFalse($emotions->hasMoreItems());
        $this->assertSame(null, $emotions->getOffset());
        $this->assertSame(null, $emotions->getLimit());

        $comment  = $comments[2];
        $emotions = $relationships->getRelationship($comment, Comment::REL_EMOTIONS);
        $this->assertCount(1, $emotions->getData());
        $this->assertFalse($emotions->hasMoreItems());
        $this->assertSame(null, $emotions->getOffset());
        $this->assertSame(null, $emotions->getLimit());

        $this->assertNotNull($post = $relationships->getRelationship($comment, Comment::REL_POST)->getData());
        $this->assertNotNull($user = $relationships->getRelationship($post, Post::REL_USER)->getData());

        // check no data for relationships we didn't asked to download
        $this->assertFalse($relationships->hasRelationship($user, User::REL_ROLE));
        $this->assertFalse($relationships->hasRelationship($user, User::REL_COMMENTS));
    }

    /**
     * Check 'read' with included paths where could be nulls.
     */
    public function testReadWithNullableInclude()
    {
        $crud = $this->createCrud(PostsApi::class);

        $index = 18;

        // check that editor relationship for selected post is `null`
        /** @noinspection SqlDialectInspection */
        $query   = 'SELECT ' . Post::FIELD_ID_EDITOR . ' FROM ' . Post::TABLE_NAME .
            ' WHERE ' . Post::FIELD_ID . " = $index";
        $idEditor = $this->connection->query($query)->fetch(PDO::FETCH_NUM)[0];
        $this->assertNull($idEditor);

        $includePaths = [
            new IncludeParameter(PostSchema::REL_EDITOR, [Post::REL_EDITOR]),
        ];

        $data = $crud->read($index, null, $includePaths);

        $this->assertNotNull($data);
        $this->assertNotNull($model = $data->getPaginatedData()->getData());
        $this->assertFalse($data->getPaginatedData()->isCollection());
        $this->assertNull($data->getRelationshipStorage()->getRelationship($model, Post::REL_EDITOR)->getData());
    }

    /**
     * Test index.
     */
    public function testIndex()
    {
        $crud = $this->createCrud(PostsApi::class);
        $s    = DocumentInterface::PATH_SEPARATOR;

        $jsonPath1    = PostSchema::REL_COMMENTS . $s . CommentSchema::REL_EMOTIONS;
        $modelPath1   = [Post::REL_COMMENTS, Comment::REL_EMOTIONS];
        $jsonPath2    = PostSchema::REL_COMMENTS . $s . CommentSchema::REL_POST . $s . PostSchema::REL_USER;
        $modelPath2   = [Post::REL_COMMENTS, Comment::REL_POST, Post::REL_USER];
        $includePaths = [
            new IncludeParameter(PostSchema::REL_BOARD, [Post::REL_BOARD]),
            new IncludeParameter(PostSchema::REL_COMMENTS, [Post::REL_COMMENTS]),
            new IncludeParameter($jsonPath1, $modelPath1),
            new IncludeParameter($jsonPath2, $modelPath2),
        ];

        $relType1       = RelationshipTypes::BELONGS_TO;
        $sortParameters = [
            $this->createSortParameter(PostSchema::REL_BOARD, Post::REL_BOARD, false, true, $relType1),
            $this->createSortParameter(PostSchema::ATTR_TITLE, Post::FIELD_TITLE, true),
        ];
        $pagingOffset        = 1;
        $pagingSize          = 2;
        $pagingParameters    = [
            PaginationStrategyInterface::PARAM_PAGING_SKIP => $pagingOffset,
            PaginationStrategyInterface::PARAM_PAGING_SIZE => $pagingSize,
        ];
        $relType2 = RelationshipTypes::BELONGS_TO;
        $filteringParameters = new FilterParameterCollection();
        $filteringParameters->add(
            new FilterParameter(PostSchema::ATTR_TITLE, Post::FIELD_TITLE, ['like' => ['%', '%']], false, null)
        );
        $filteringParameters->add(
            new FilterParameter(PostSchema::REL_USER, Post::REL_USER, ['lt' => '5'], true, $relType2)
        );

        $data = $crud->index($filteringParameters, $sortParameters, $includePaths, $pagingParameters);

        $this->assertNotEmpty($data->getPaginatedData()->getData());
        $this->assertCount($pagingSize, $data->getPaginatedData()->getData());
        $this->assertEquals(20, $data->getPaginatedData()->getData()[0]->{Post::FIELD_ID});
        $this->assertEquals(9, $data->getPaginatedData()->getData()[1]->{Post::FIELD_ID});
        $this->assertTrue($data->getPaginatedData()->isCollection());
        $this->assertEquals($pagingOffset, $data->getPaginatedData()->getOffset());
        $this->assertEquals($pagingSize, $data->getPaginatedData()->getLimit());

        return [$data, $filteringParameters, $sortParameters, $includePaths, $pagingParameters];
    }

    /**
     * Test index.
     */
    public function testIndexDefaultFilteringOperationOnRelationship()
    {
        $crud = $this->createCrud(PostsApi::class);

        $pagingOffset        = 0;
        $pagingSize          = 20;
        $pagingParameters    = [
            PaginationStrategyInterface::PARAM_PAGING_SKIP => $pagingOffset,
            PaginationStrategyInterface::PARAM_PAGING_SIZE => $pagingSize,
        ];

        $filteringParameters = new FilterParameterCollection();
        $value               = '2,4';
        $filteringParameters->add(
            new FilterParameter(PostSchema::REL_USER, Post::REL_USER, $value, true, RelationshipTypes::BELONGS_TO)
        );

        $data = $crud->index($filteringParameters, null, null, $pagingParameters);

        $this->assertCount(6, $data->getPaginatedData()->getData());
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

        $relType1         = RelationshipTypes::BELONGS_TO;
        $sortParameters   = [
            $this->createSortParameter(CommentSchema::REL_USER, Comment::REL_USER, false, true, $relType1),
            $this->createSortParameter(CommentSchema::ATTR_TEXT, Comment::FIELD_TEXT, true),
        ];
        $pagingOffset     = 1;
        $pagingSize       = 2;
        $pagingParameters = [
            PaginationStrategyInterface::PARAM_PAGING_SKIP => $pagingOffset,
            PaginationStrategyInterface::PARAM_PAGING_SIZE => $pagingSize,
        ];
        $relType2         = RelationshipTypes::BELONGS_TO;
        $filterParameters = new FilterParameterCollection();
        $filterParameters->add(
            new FilterParameter(CommentSchema::REL_USER, Comment::REL_USER, ['lt' => '5'], true, $relType2)
        );
        $filterParameters->add(
            new FilterParameter(CommentSchema::ATTR_TEXT, Comment::FIELD_TEXT, ['like' => '%'], false, null)
        );

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
        $this->assertEquals($pagingSize, $data->getLimit());
    }

    /**
     * Test index.
     */
    public function testIndexWithFilterByBooleanColumn()
    {
        $crud = $this->createCrud(UsersApi::class);

        $filteringParameters = new FilterParameterCollection();
        $filteringParameters->add(
            new FilterParameter(UserSchema::ATTR_IS_ACTIVE, User::FIELD_IS_ACTIVE, ['eq' => '1'], false, null)
        );

        $data  = $crud->index($filteringParameters);
        $users = $data->getPaginatedData()->getData();
        $this->assertNotEmpty($users);

        /** @noinspection SqlDialectInspection */
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

        $index               = 2;
        $filteringParameters = new FilterParameterCollection();
        $filteringParameters->add(
            new FilterParameter(PostSchema::RESOURCE_ID, Post::FIELD_ID, ['eq' => $index], false, null)
        );

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

        $relType = RelationshipTypes::BELONGS_TO;
        $filteringParameters = new FilterParameterCollection();
        $filteringParameters->add(
            new FilterParameter(PostSchema::REL_USER, Post::REL_USER, ['CCC' => '5'], true, $relType)
        );

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

        $this->assertEquals('user-relationship', $errors[0]->getSource()[ErrorInterface::SOURCE_PARAMETER]);
        $this->assertEquals('ccc', $errors[0]->getDetail());
    }

    /**
     * Test read typed row.
     */
    public function testReadRow()
    {
        $crud = $this->createCrud(PostsApi::class);

        $row = $crud->readRow(1);

        $this->assertTrue(is_int($row[Post::FIELD_ID_BOARD]));
        $this->assertTrue(is_string($row[Post::FIELD_TEXT]));
    }

    /**
     * Test index.
     */
    public function testCount()
    {
        $crud = $this->createCrud(PostsApi::class);

        $filteringParameters = new FilterParameterCollection();
        $filteringParameters->add(
            new FilterParameter(PostSchema::ATTR_TITLE, Post::FIELD_TITLE, ['like' => ['%', '%']], false, null)
        );
        $relationshipType = RelationshipTypes::BELONGS_TO;
        $filteringParameters->add(
            new FilterParameter(PostSchema::REL_USER, Post::REL_USER, ['lt' => '5'], true, $relationshipType)
        );

        $result = $crud->count($filteringParameters);

        $this->assertEquals(14, $result);
    }

    /**
     * @param string $class
     *
     * @return CrudInterface
     */
    private function createCrud($class)
    {
        $this->connection = $this->initDb();
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
        $crud      = new $class($factory, $repository, $modelSchemes, $relPaging);

        return $crud;
    }

    /**
     * @param string   $originalName
     * @param string   $name
     * @param bool     $isAscending
     * @param bool     $isRelationship
     * @param null|int $relationshipType
     *
     * @return SortParameterInterface
     */
    private function createSortParameter(
        $originalName,
        $name,
        $isAscending,
        $isRelationship = false,
        $relationshipType = null
    ) {
        $sortParam = new JsonLibrarySortParameter($originalName, $isAscending);
        $result    = new SortParameter($sortParam, $name, $isRelationship, $relationshipType);

        return $result;
    }
}
