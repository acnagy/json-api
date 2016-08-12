<?php namespace Limoncello\Tests\JsonApi;

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
use Doctrine\DBAL\Types\Type;
use Limoncello\JsonApi\Contracts\Models\ModelSchemesInterface;
use Limoncello\JsonApi\Contracts\Models\RelationshipStorageInterface;
use Limoncello\JsonApi\Contracts\Schema\JsonSchemesInterface;
use Limoncello\JsonApi\Factory;
use Limoncello\JsonApi\Models\ModelSchemes;
use Limoncello\JsonApi\Models\RelationshipTypes;
use Limoncello\Tests\JsonApi\Data\Migrations\Runner as MigrationRunner;
use Limoncello\Tests\JsonApi\Data\Models\Board;
use Limoncello\Tests\JsonApi\Data\Models\Category;
use Limoncello\Tests\JsonApi\Data\Models\Comment;
use Limoncello\Tests\JsonApi\Data\Models\Emotion;
use Limoncello\Tests\JsonApi\Data\Models\Model;
use Limoncello\Tests\JsonApi\Data\Models\Post;
use Limoncello\Tests\JsonApi\Data\Models\Role;
use Limoncello\Tests\JsonApi\Data\Models\User;
use Limoncello\Tests\JsonApi\Data\Schemes\BoardSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\CategorySchema;
use Limoncello\Tests\JsonApi\Data\Schemes\CommentSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\EmotionSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\PostSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\RoleSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\UserSchema;
use Limoncello\Tests\JsonApi\Data\Seeds\Runner as SeedRunner;
use Limoncello\Tests\JsonApi\Data\Types\SystemDateTimeType;
use Mockery;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;

/**
 * @package Limoncello\Tests\JsonApi
 */
class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * @inheritdoc
     */
    protected function setUp()
    {
        parent::setUp();

        if (Type::hasType(SystemDateTimeType::NAME) === false) {
            Type::addType(SystemDateTimeType::NAME, SystemDateTimeType::class);
        }
    }

    /**
     * @inheritdoc
     */
    protected function tearDown()
    {
        parent::tearDown();
        Mockery::close();
    }

    /**
     * @return Connection
     */
    protected function createConnection()
    {
        $connection = DriverManager::getConnection(['url' => 'sqlite:///', 'memory' => true]);
        $this->assertNotSame(false, $connection->exec('PRAGMA foreign_keys = ON;'));

        return $connection;
    }

    /**
     * @param Connection $connection
     */
    protected function migrateDatabase(Connection $connection)
    {
        (new MigrationRunner())->migrate($connection->getSchemaManager());
        (new SeedRunner())->run($connection);
    }

    /**
     * @return Connection
     */
    protected function initDb()
    {
        $connection = $this->createConnection();
        $this->migrateDatabase($connection);

        return $connection;
    }

    /**
     * @return ModelSchemesInterface
     */
    protected function getModelSchemes()
    {
        $registered    = [];
        $modelSchemes  = new ModelSchemes();
        $registerModel = function ($modelClass) use ($modelSchemes, &$registered) {
            /** @var Model $modelClass */
            $modelSchemes->registerClass(
                $modelClass,
                $modelClass::TABLE_NAME,
                $modelClass::FIELD_ID,
                $modelClass::getAttributeTypes(),
                $modelClass::getAttributeLengths()
            );

            $relationships = $modelClass::getRelationships();

            if (array_key_exists(RelationshipTypes::BELONGS_TO, $relationships) === true) {
                foreach ($relationships[RelationshipTypes::BELONGS_TO] as $relName => list($rClass, $fKey, $rRel)) {
                    if (isset($registered[(string)$modelClass][$relName]) === true) {
                        continue;
                    }
                    $modelSchemes->registerBelongsToOneRelationship($modelClass, $relName, $fKey, $rClass, $rRel);
                    $registered[(string)$modelClass][$relName] = true;
                    $registered[$rClass][$rRel]                = true;
                }
            }

            if (array_key_exists(RelationshipTypes::BELONGS_TO_MANY, $relationships) === true) {
                foreach ($relationships[RelationshipTypes::BELONGS_TO_MANY] as $relName => $data) {
                    if (isset($registered[(string)$modelClass][$relName]) === true) {
                        continue;
                    }
                    list($rClass, $iTable, $fKeyPrimary, $fKeySecondary, $rRel) = $data;
                    $modelSchemes->registerBelongsToManyRelationship(
                        $modelClass,
                        $relName,
                        $iTable,
                        $fKeyPrimary,
                        $fKeySecondary,
                        $rClass,
                        $rRel
                    );
                    $registered[(string)$modelClass][$relName] = true;
                    $registered[$rClass][$rRel]                = true;
                }
            }
        };

        array_map($registerModel, [
            Board::class,
            Comment::class,
            Emotion::class,
            Post::class,
            Role::class,
            User::class,
            Category::class,
        ]);

        return $modelSchemes;
    }

    /**
     * @param ModelSchemesInterface             $modelSchemes
     * @param RelationshipStorageInterface|null $storage
     *
     * @return JsonSchemesInterface
     */
    protected function getJsonSchemes(
        ModelSchemesInterface $modelSchemes,
        RelationshipStorageInterface $storage = null
    ) {
        $factory = new Factory();
        $schemes = $factory->createJsonSchemes($this->getSchemeMap(), $modelSchemes);

        $storage === null ?: $schemes->setRelationshipStorage($storage);

        return $schemes;
    }

    /**
     * @return array
     */
    protected function getSchemeMap()
    {
        return [
            Board::class   => function (
                FactoryInterface $factory,
                JsonSchemesInterface $container,
                ModelSchemesInterface $modelSchemes
            ) {
                return new BoardSchema($factory, $container, $modelSchemes);
            },
            Comment::class  => CommentSchema::class,
            Emotion::class  => EmotionSchema::class,
            Post::class     => PostSchema::class,
            Role::class     => RoleSchema::class,
            User::class     => UserSchema::class,
            Category::class => CategorySchema::class,
        ];
    }
}
