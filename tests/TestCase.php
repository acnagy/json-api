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

use Limoncello\JsonApi\Contracts\Schema\ContainerInterface;
use Limoncello\JsonApi\Factory;
use Limoncello\Models\Contracts\RelationshipStorageInterface;
use Limoncello\Models\Contracts\SchemaStorageInterface;
use Limoncello\Models\RelationshipTypes;
use Limoncello\Models\SchemaStorage;
use Limoncello\Tests\JsonApi\Data\Models\Board;
use Limoncello\Tests\JsonApi\Data\Models\Comment;
use Limoncello\Tests\JsonApi\Data\Models\Emotion;
use Limoncello\Tests\JsonApi\Data\Models\Model;
use Limoncello\Tests\JsonApi\Data\Models\Post;
use Limoncello\Tests\JsonApi\Data\Models\Role;
use Limoncello\Tests\JsonApi\Data\Models\User;
use Limoncello\Tests\JsonApi\Data\Schemes\BoardSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\CommentSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\EmotionSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\PostSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\RoleSchema;
use Limoncello\Tests\JsonApi\Data\Schemes\UserSchema;
use Mockery;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;

/**
 * @package Limoncello\Tests\JsonApi
 */
class TestCase extends \PHPUnit_Framework_TestCase
{
    protected function tearDown()
    {
        parent::tearDown();
        Mockery::close();
    }

    /**
     * @return SchemaStorageInterface
     */
    protected function getModelSchemes()
    {
        $registered    = [];
        $storage       = new SchemaStorage();
        $registerModel = function ($modelClass) use ($storage, &$registered) {
            /** @var Model $modelClass */
            $storage->registerClass(
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
                    $storage->registerBelongsToOneRelationship($modelClass, $relName, $fKey, $rClass, $rRel);
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
                    $storage->registerBelongsToManyRelationship(
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
        ]);

        return $storage;
    }

    /**
     * @param SchemaStorageInterface            $modelSchemes
     * @param RelationshipStorageInterface|null $storage
     *
     * @return ContainerInterface
     */
    protected function getJsonSchemes(
        SchemaStorageInterface $modelSchemes,
        RelationshipStorageInterface $storage = null
    ) {
        $factory = new Factory();
        $schemes = $factory->createContainer([
            Board::class   => function (
                FactoryInterface $factory,
                ContainerInterface $container,
                SchemaStorageInterface $schemaStorage
            ) {
                return new BoardSchema($factory, $container, $schemaStorage);
            },
            Comment::class => CommentSchema::class,
            Emotion::class => EmotionSchema::class,
            Post::class    => PostSchema::class,
            Role::class    => RoleSchema::class,
            User::class    => UserSchema::class,
        ], $modelSchemes);

        $storage === null ?: $schemes->setRelationshipStorage($storage);

        return $schemes;
    }
}
