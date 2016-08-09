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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Limoncello\JsonApi\Adapters\FilterOperations;
use Limoncello\JsonApi\Adapters\Repository;
use Limoncello\JsonApi\Contracts\Adapters\RepositoryInterface;
use Limoncello\JsonApi\Contracts\Http\Query\SortParameterInterface;
use Limoncello\JsonApi\Http\Query\FilterParameter;
use Limoncello\JsonApi\Http\Query\FilterParameterCollection;
use Limoncello\JsonApi\Http\Query\SortParameter;
use Limoncello\JsonApi\I18n\Translator;
use Limoncello\JsonApi\Models\RelationshipTypes;
use Limoncello\Tests\JsonApi\Data\Models\Board;
use Limoncello\Tests\JsonApi\Data\Models\Comment;
use Limoncello\Tests\JsonApi\Data\Models\Emotion;
use Limoncello\Tests\JsonApi\Data\Models\Post;
use Limoncello\Tests\JsonApi\Data\Schemes\BoardSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\CommentSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\EmotionSchema;
use Limoncello\Tests\JsonApi\TestCase;
use Neomerx\JsonApi\Encoder\Parameters\SortParameter as JsonLibrarySortParameter;
use Neomerx\JsonApi\Exceptions\ErrorCollection;

/**
 * @package Limoncello\Tests\JsonApi
 */
class RepositoryTest extends TestCase
{
    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        $this->connection = $this->createConnection();

        $translator       = new Translator();
        $this->repository = new Repository(
            $this->connection,
            $this->getModelSchemes(),
            new FilterOperations($translator),
            $translator
        );
    }

    /**
     * Test builder.
     */
    public function testRead()
    {
        $indexBind = ':index';
        $this->assertNotNull($builder = $this->repository->read(Board::class, $indexBind));

        $expected =
            'SELECT `boards`.`id_board`, `boards`.`title`, `boards`.`created_at`, '.
            '`boards`.`updated_at`, `boards`.`deleted_at` ' .
            'FROM boards WHERE `boards`.`id_board`=' . $indexBind;

        $this->assertEquals($expected, $builder->getSQL());
    }

    /**
     * Test builder.
     */
    public function testIndex()
    {
        $this->assertNotNull($builder = $this->repository->index(Board::class));

        $expected =
            'SELECT `boards`.`id_board`, `boards`.`title`, `boards`.`created_at`, '.
            '`boards`.`updated_at`, `boards`.`deleted_at` ' .
            'FROM boards';

        $this->assertEquals($expected, $builder->getSQL());
    }

    /**
     * Test builder.
     */
    public function testIndexWithEmptyFilters()
    {
        $filterParams = new FilterParameterCollection();

        $errors = new ErrorCollection();
        $this->assertNotNull($builder = $this->repository->index(Board::class));
        $this->repository->applyFilters($errors, $builder, Board::class, $filterParams);
        $this->assertEmpty($errors);

        $expected =
            'SELECT `boards`.`id_board`, `boards`.`title`, `boards`.`created_at`, '.
            '`boards`.`updated_at`, `boards`.`deleted_at` ' .
            'FROM boards';

        $this->assertEquals($expected, $builder->getSQL());
        $this->assertEmpty($builder->getParameters());
    }

    /**
     * Test builder.
     */
    public function testIndexWithFilters()
    {
        $value       = [
            'equals'            => 'aaa',
            'not-equals'        => ['bbb', 'ccc'],
            'less-than'         => 'ddd',
            'less-or-equals'    => 'eee',
            'greater-than'      => 'fff',
            'greater-or-equals' => 'ggg',
            'like'              => 'hhh',
            'not-like'          => ['iii', 'jjj'],
            'in'                => 'kkk',
            'not-in'            => ['lll', 'mmm'],
            'is-null'           => null,
            'not-null'          => 'whatever',
        ];
        $filterParams = new FilterParameterCollection();
        $filterParams->add(
            new FilterParameter(BoardSchema::ATTR_TITLE, Board::FIELD_TITLE, $value, false, null)
        );

        $errors = new ErrorCollection();
        $this->assertNotNull($builder = $this->repository->index(Board::class));
        $this->repository->applyFilters($errors, $builder, Board::class, $filterParams);
        $this->assertEmpty($errors);

        $expected =
            'SELECT `boards`.`id_board`, `boards`.`title`, `boards`.`created_at`, '.
            '`boards`.`updated_at`, `boards`.`deleted_at` ' .
            'FROM boards ' .
            'WHERE ' .
            '(`boards`.`title` = :dcValue1) AND ' .
            '(`boards`.`title` <> :dcValue2) AND (`boards`.`title` <> :dcValue3) AND ' .
            '(`boards`.`title` < :dcValue4) AND ' .
            '(`boards`.`title` <= :dcValue5) AND ' .
            '(`boards`.`title` > :dcValue6) AND ' .
            '(`boards`.`title` >= :dcValue7) AND ' .
            '(`boards`.`title` LIKE :dcValue8) AND ' .
            '(`boards`.`title` NOT LIKE :dcValue9) AND (`boards`.`title` NOT LIKE :dcValue10) AND ' .
            '(`boards`.`title` IN (:dcValue11)) AND ' .
            '(`boards`.`title` NOT IN (:dcValue12, :dcValue13)) AND ' .
            '(`boards`.`title` IS NULL) AND ' .
            '(`boards`.`title` IS NOT NULL)';

        $this->assertEquals($expected, $builder->getSQL());
        $this->assertEquals([
            'dcValue1'  => 'aaa',
            'dcValue2'  => 'bbb',
            'dcValue3'  => 'ccc',
            'dcValue4'  => 'ddd',
            'dcValue5'  => 'eee',
            'dcValue6'  => 'fff',
            'dcValue7'  => 'ggg',
            'dcValue8'  => 'hhh',
            'dcValue9'  => 'iii',
            'dcValue10' => 'jjj',
            'dcValue11' => 'kkk',
            'dcValue12' => 'lll',
            'dcValue13' => 'mmm',
        ], $builder->getParameters());
    }

    /**
     * Test builder.
     */
    public function testIndexWithFiltersJoinedWithOR()
    {
        $value        = [
            'greater-or-equals' => 'aaa',
            'less-or-equals'    => 'bbb',
        ];
        $filterParams = new FilterParameterCollection();
        $filterParams->withOr()->add(
            new FilterParameter(BoardSchema::ATTR_TITLE, Board::FIELD_TITLE, $value, false, null)
        );

        $errors = new ErrorCollection();
        $this->assertNotNull($builder = $this->repository->index(Board::class));
        $this->repository->applyFilters($errors, $builder, Board::class, $filterParams);
        $this->assertEmpty($errors);

        $expected =
            'SELECT `boards`.`id_board`, `boards`.`title`, `boards`.`created_at`, '.
            '`boards`.`updated_at`, `boards`.`deleted_at` ' .
            'FROM boards ' .
            'WHERE (`boards`.`title` >= :dcValue1) OR (`boards`.`title` <= :dcValue2)';

        $this->assertEquals($expected, $builder->getSQL());
        $this->assertEquals([
            'dcValue1'  => 'aaa',
            'dcValue2'  => 'bbb',
        ], $builder->getParameters());
    }

    /**
     * Test builder.
     */
    public function testIndexFilterWithDefaultOperationOnAttributeMultiValue()
    {
        $value        = '1,3,2';
        $filterParams = new FilterParameterCollection();
        $filterParams->add(
            new FilterParameter(BoardSchema::ATTR_TITLE, Board::FIELD_TITLE, $value, false, null)
        );

        $errors = new ErrorCollection();
        $this->assertNotNull($builder = $this->repository->index(Board::class));
        $this->repository->applyFilters($errors, $builder, Board::class, $filterParams);
        $this->assertEmpty($errors);

        $expected =
            'SELECT `boards`.`id_board`, `boards`.`title`, `boards`.`created_at`, '.
            '`boards`.`updated_at`, `boards`.`deleted_at` ' .
            'FROM boards ' .
            'WHERE ' .
            '`boards`.`title` IN (:dcValue1, :dcValue2, :dcValue3)';

        $this->assertEquals($expected, $builder->getSQL());
        $this->assertEquals([
            'dcValue1'  => '1',
            'dcValue2'  => '3',
            'dcValue3'  => '2',
        ], $builder->getParameters());
    }

    /**
     * Test builder.
     */
    public function testIndexFilterWithDefaultOperationOnAttributeSingleValue()
    {
        $value        = '1';
        $filterParams = new FilterParameterCollection();
        $filterParams->add(
            new FilterParameter(BoardSchema::ATTR_TITLE, Board::FIELD_TITLE, $value, false, null)
        );

        $errors = new ErrorCollection();
        $this->assertNotNull($builder = $this->repository->index(Board::class));
        $this->repository->applyFilters($errors, $builder, Board::class, $filterParams);
        $this->assertEmpty($errors);

        $expected =
            'SELECT `boards`.`id_board`, `boards`.`title`, `boards`.`created_at`, '.
            '`boards`.`updated_at`, `boards`.`deleted_at` ' .
            'FROM boards ' .
            'WHERE ' .
            '`boards`.`title` = :dcValue1';

        $this->assertEquals($expected, $builder->getSQL());
        $this->assertEquals([
            'dcValue1'  => '1',
        ], $builder->getParameters());
    }

    /**
     * Test builder.
     */
    public function testIndexFilterWithDefaultOperationOnAttributeEmptyValue()
    {
        $value        = '';
        $filterParams = new FilterParameterCollection();
        $filterParams->add(
            new FilterParameter(BoardSchema::ATTR_TITLE, Board::FIELD_TITLE, $value, false, null)
        );

        $errors = new ErrorCollection();
        $this->assertNotNull($builder = $this->repository->index(Board::class));
        $this->repository->applyFilters($errors, $builder, Board::class, $filterParams);
        $this->assertEmpty($errors);

        $expected =
            'SELECT `boards`.`id_board`, `boards`.`title`, `boards`.`created_at`, '.
            '`boards`.`updated_at`, `boards`.`deleted_at` ' .
            'FROM boards ' .
            'WHERE ' .
            '`boards`.`title` IS NULL';

        $this->assertEquals($expected, $builder->getSQL());
        $this->assertEmpty($builder->getParameters());
    }

    /**
     * Test builder.
     */
    public function testIndexFilterWithDefaultOperationOnRelationshipMultiValue()
    {
        $value        = '1,3,2';
        $filterParams = new FilterParameterCollection();
        $filterParams->add(
            new FilterParameter(BoardSchema::REL_POSTS, Board::REL_POSTS, $value, true, RelationshipTypes::HAS_MANY)
        );

        $errors = new ErrorCollection();
        $this->assertNotNull($builder = $this->repository->index(Board::class));
        $this->repository->applyFilters($errors, $builder, Board::class, $filterParams);
        $this->assertEmpty($errors);

        $expected =
            'SELECT `boards`.`id_board`, `boards`.`title`, `boards`.`created_at`, '.
            '`boards`.`updated_at`, `boards`.`deleted_at` ' .
            'FROM boards ' .
            'INNER JOIN posts posts1 ON `boards`.`id_board`=`posts1`.`id_board_fk` ' .
            'WHERE `posts1`.`id_post` IN (:dcValue1, :dcValue2, :dcValue3) ' .
            'GROUP BY `boards`.`id_board`';

        $this->assertEquals($expected, $builder->getSQL());
        $this->assertEquals([
            'dcValue1'  => '1',
            'dcValue2'  => '3',
            'dcValue3'  => '2',
        ], $builder->getParameters());
    }

    /**
     * Test builder.
     */
    public function testIndexWithSorting()
    {
        $sortingParams = [
            $this->createSortParameter(BoardSchema::ATTR_TITLE, Board::FIELD_TITLE, true),
            $this->createSortParameter(BoardSchema::RESOURCE_ID, Board::FIELD_ID, false),
            $this->createSortParameter(BoardSchema::ATTR_CREATED_AT, Board::FIELD_CREATED_AT, false),
        ];

        $this->assertNotNull($builder = $this->repository->index(Board::class));
        $this->repository->applySorting($builder, Board::class, $sortingParams);

        $expected =
            'SELECT `boards`.`id_board`, `boards`.`title`, `boards`.`created_at`, '.
            '`boards`.`updated_at`, `boards`.`deleted_at` ' .
            'FROM boards ' .
            'ORDER BY `boards`.`title` ASC, `boards`.`id_board` DESC, `boards`.`created_at` DESC';

        $this->assertEquals($expected, $builder->getSQL());
    }

    /**
     * Test builder.
     */
    public function testIndexWithSortingByRelationships()
    {
        $isRel         = true;
        $type1         = RelationshipTypes::BELONGS_TO;
        $type2         = RelationshipTypes::BELONGS_TO_MANY;
        $sortingParams = [
            $this->createSortParameter(CommentSchema::REL_USER, Comment::REL_USER, false, $isRel, $type1),
            $this->createSortParameter(CommentSchema::REL_EMOTIONS, 'should be ignored', true, $isRel, $type2),
        ];

        $this->assertNotNull($builder = $this->repository->index(Comment::class));
        $this->repository->applySorting($builder, Comment::class, $sortingParams);

        $expected =
            'SELECT `comments`.`id_comment`, `comments`.`id_post_fk`, `comments`.`id_user_fk`, ' .
                '`comments`.`text`, `comments`.`created_at`, `comments`.`updated_at`, `comments`.`deleted_at` ' .
            'FROM comments ' .
            'ORDER BY `comments`.`id_user_fk` DESC';

        $this->assertEquals($expected, $builder->getSQL());
    }

    /**
     * Test builder.
     */
    public function testIndexWithVariousParams()
    {
        $value        = [
            'eq' => 'aaa',
        ];
        $filterParams = new FilterParameterCollection();
        $filterParams->add(
            new FilterParameter(BoardSchema::ATTR_TITLE, Board::FIELD_TITLE, $value, false, null)
        );

        $sortingParams = [
            $this->createSortParameter(BoardSchema::ATTR_TITLE, Board::FIELD_TITLE, true),
            $this->createSortParameter(BoardSchema::RESOURCE_ID, Board::FIELD_ID, false),
        ];

        $errors = new ErrorCollection();
        $this->assertNotNull($builder = $this->repository->index(Board::class));
        $this->repository->applyFilters($errors, $builder, Board::class, $filterParams);
        $this->repository->applySorting($builder, Board::class, $sortingParams);
        $this->assertEmpty($errors);

        $expected =
            'SELECT `boards`.`id_board`, `boards`.`title`, `boards`.`created_at`, '.
            '`boards`.`updated_at`, `boards`.`deleted_at` ' .
            'FROM boards ' .
            'WHERE `boards`.`title` = :dcValue1 ' .
            'ORDER BY `boards`.`title` ASC, `boards`.`id_board` DESC';

        $this->assertEquals($expected, $builder->getSQL());
        $this->assertEquals([
            'dcValue1'  => 'aaa',
        ], $builder->getParameters());
    }

    /**
     * Test builder.
     */
    public function testIndexWithVariousInvalidParams()
    {
        $filterParams = new FilterParameterCollection();
        $filterParams
            // empty is valid
            ->add(new FilterParameter(BoardSchema::RESOURCE_ID, Board::FIELD_ID, '', false, null))

            // unknown operation is invalid
            ->add(
                new FilterParameter(BoardSchema::ATTR_TITLE, Board::FIELD_TITLE, ['unknown-op' => 'aaa'], false, null)
            );

        $errors = new ErrorCollection();
        $this->assertNotNull($builder = $this->repository->index(Board::class));
        $this->repository->applyFilters($errors, $builder, Board::class, $filterParams);
        $this->assertCount(1, $errors);
    }

    /**
     * Test builder.
     */
    public function testCreate()
    {
        $attributes = [
            Board::FIELD_ID    => 123,
            Board::FIELD_TITLE => 'aaa',
        ];

        $this->assertNotNull($builder = $this->repository->create(Board::class, $attributes));

        /** @noinspection SqlDialectInspection */
        $expected ='INSERT INTO boards (id_board, title) VALUES(:dcValue1, :dcValue2)';

        $this->assertEquals($expected, $builder->getSQL());
        $this->assertEquals([
            'dcValue1'  => '123',
            'dcValue2'  => 'aaa',
        ], $builder->getParameters());
    }

    /**
     * Test builder.
     */
    public function testUpdate()
    {
        $updated = [
            Board::FIELD_TITLE      => 'bbb',
            Board::FIELD_UPDATED_AT => '2000-01-02', // in real app it will be read-only and auto set
        ];

        $this->assertNotNull($builder = $this->repository->update(Board::class, 123, $updated));

        /** @noinspection SqlDialectInspection */
        $expected ='UPDATE boards SET title = :dcValue1, updated_at = :dcValue2 WHERE `boards`.`id_board`=:dcValue3';

        $this->assertEquals($expected, $builder->getSQL());
        $this->assertEquals([
            'dcValue1'  => 'bbb',
            'dcValue2'  => '2000-01-02',
            'dcValue3'  => '123',
        ], $builder->getParameters());
    }

    /**
     * Test builder.
     */
    public function testDelete()
    {
        $indexBind = ':index';
        $this->assertNotNull($builder = $this->repository->delete(Board::class, $indexBind));

        /** @noinspection SqlDialectInspection */
        $expected ='DELETE FROM boards WHERE `boards`.`id_board`=' . $indexBind;

        $this->assertEquals($expected, $builder->getSQL());
    }

    /**
     * Test builder.
     */
    public function testSaveToMany()
    {
        $indexBind      = ':index';
        $otherIndexBind = ':otherIndex';

        $this->assertNotNull($builder = $this->repository->createToManyRelationship(
            Comment::class,
            $indexBind,
            Comment::REL_EMOTIONS,
            $otherIndexBind
        ));

        /** @noinspection SqlDialectInspection */
        $expected = "INSERT INTO comments_emotions (id_comment_fk, id_emotion_fk) VALUES($indexBind, $otherIndexBind)";

        $this->assertEquals($expected, $builder->getSQL());
    }

    /**
     * Test builder.
     */
    public function testCleanToMany()
    {
        $indexBind = ':index';

        $this->assertNotNull($builder = $this->repository->cleanToManyRelationship(
            Comment::class,
            $indexBind,
            Comment::REL_EMOTIONS
        ));

        /** @noinspection SqlDialectInspection */
        $expected = "DELETE FROM comments_emotions WHERE `comments_emotions`.`id_comment_fk`=$indexBind";

        $this->assertEquals($expected, $builder->getSQL());
    }

    /**
     * Test builder.
     */
    public function testReadBelongsTo()
    {
        $indexBind = ':index';

        /** @var QueryBuilder $builder */
        list($builder, $targetClass, $relType) = $this->repository->readRelationship(
            Comment::class,
            $indexBind,
            Comment::REL_POST
        );
        $this->assertNotNull($builder);
        $this->assertEquals(Post::class, $targetClass);
        $this->assertEquals(RelationshipTypes::BELONGS_TO, $relType);

        $expected = 'SELECT `posts`.`id_post`, `posts`.`id_board_fk`, `posts`.`id_user_fk`, ' .
            '`posts`.`title`, `posts`.`text`, `posts`.`created_at`, `posts`.`updated_at`, `posts`.`deleted_at` '.
            'FROM posts INNER JOIN comments  ON `comments`.`id_post_fk`=`posts`.`id_post` ' .
            "WHERE `comments`.`id_comment`=$indexBind";

        $this->assertEquals($expected, $builder->getSQL());
    }

    /**
     * Test builder.
     */
    public function testReadHasMany()
    {
        $indexBind = ':index';

        /** @var QueryBuilder $builder */
        list($builder, $targetClass, $relType) = $this->repository->readRelationship(
            Post::class,
            $indexBind,
            Post::REL_COMMENTS
        );
        $this->assertNotNull($builder);
        $this->assertEquals(Comment::class, $targetClass);
        $this->assertEquals(RelationshipTypes::HAS_MANY, $relType);

        $expected = 'SELECT `comments`.`id_comment`, `comments`.`id_post_fk`, `comments`.`id_user_fk`, ' .
            '`comments`.`text`, `comments`.`created_at`, `comments`.`updated_at`, `comments`.`deleted_at` '.
            'FROM comments ' .
            "WHERE `comments`.`id_post_fk`=$indexBind";

        $this->assertEquals($expected, $builder->getSQL());
    }

    /**
     * Test builder.
     */
    public function testBelongsToMany()
    {
        $indexBind = ':index';

        /** @var QueryBuilder $builder */
        list($builder, $targetClass, $relType) = $this->repository->readRelationship(
            Comment::class,
            $indexBind,
            Comment::REL_EMOTIONS
        );
        $this->assertNotNull($builder);
        $this->assertEquals(Emotion::class, $targetClass);
        $this->assertEquals(RelationshipTypes::BELONGS_TO_MANY, $relType);

        $expected =
            'SELECT `emotions`.`id_emotion`, `emotions`.`name`, `emotions`.`created_at`, `emotions`.`updated_at` ' .
            'FROM emotions ' .
            'INNER JOIN comments_emotions  ON `emotions`.`id_emotion`=`comments_emotions`.`id_emotion_fk` ' .
            "WHERE `comments_emotions`.`id_comment_fk`=$indexBind";

        $this->assertEquals($expected, $builder->getSQL());
    }

    /**
     * Test mix of named and non-named parameters.
     */
    public function testReadRelationshipWithMixedParams()
    {
        $this->migrateDatabase($this->connection);

        $value        = [
            'like' => '%ss%',
        ];
        $filterParams = new FilterParameterCollection();
        $filterParams->add(
            new FilterParameter(EmotionSchema::ATTR_NAME, Emotion::FIELD_NAME, $value, false, null)
        );

        $sortingParams = [
            $this->createSortParameter(EmotionSchema::RESOURCE_ID, Emotion::FIELD_ID, false),
        ];

        $indexBind = ':index';
        $errors    = new ErrorCollection();
        /** @var QueryBuilder $builder */
        $this->assertNotNull(list($builder) = $this->repository->readRelationship(
            Comment::class,
            $indexBind,
            Comment::REL_EMOTIONS
        ));
        $this->repository->applyFilters($errors, $builder, Emotion::class, $filterParams);
        $this->repository->applySorting($builder, Emotion::class, $sortingParams);
        $this->assertEmpty($errors);

        $this->assertNotEmpty($emotions = $builder->setParameter($indexBind, 2)->execute()->fetchAll());
        $this->assertCount(2, $emotions);
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
