<?php namespace Limoncello\Tests\JsonApi\Data\Http;

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

use Interop\Container\ContainerInterface;
use Limoncello\Tests\JsonApi\Data\Api\CommentsApi as Api;
use Limoncello\Tests\JsonApi\Data\Schemes\CommentSchema as Schema;
use Limoncello\Tests\JsonApi\Data\Validation\AppValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @package Limoncello\Tests\JsonApi
 */
class CommentsController extends BaseController
{
    /** @inheritdoc */
    const API_CLASS = Api::class;

    /** @inheritdoc */
    const SCHEMA_CLASS = Schema::class;

    /**
     * @inheritdoc
     */
    public static function parseInputOnCreate(ContainerInterface $container, ServerRequestInterface $request)
    {
        $json   = static::parseJson($container, $request);
        $schema = static::getSchema($container);

        /** @var AppValidator $validator */
        $validator = $container->get(AppValidator::class);

        $idRule         = $validator->absentOrNull();
        $attributeRules = [
            Schema::ATTR_TEXT => $validator->requiredText(),
        ];
        $toOneRules     = [
            Schema::REL_POST => $validator->requiredPostId(),
        ];
        $toManyRules    = [
            Schema::REL_EMOTIONS => $validator->optionalEmotionId(),
        ];

        list (, $attrCaptures, $toManyCaptures) =
            $validator->assert($schema, $json, $idRule, $attributeRules, $toOneRules, $toManyRules);

        return [$attrCaptures, $toManyCaptures];
    }

    /**
     * @inheritdoc
     */
    public static function parseInputOnUpdate($index, ContainerInterface $container, ServerRequestInterface $request)
    {
        $json   = static::parseJson($container, $request);
        $schema = static::getSchema($container);

        /** @var AppValidator $validator */
        $validator = $container->get(AppValidator::class);

        $idRule         = $validator->idEquals($index);
        $attributeRules = [
            Schema::ATTR_TEXT => $validator->optionalText(),
        ];
        $toOneRules     = [
            Schema::REL_POST => $validator->optionalPostId(),
        ];
        $toManyRules    = [
            Schema::REL_EMOTIONS => $validator->optionalEmotionId(),
        ];

        list (, $attrCaptures, $toManyCaptures) =
            $validator->assert($schema, $json, $idRule, $attributeRules, $toOneRules, $toManyRules);

        return [$attrCaptures, $toManyCaptures];
    }

    /**
     * @param array                  $routeParams
     * @param ContainerInterface     $container
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public static function readEmotions(
        array $routeParams,
        ContainerInterface $container,
        ServerRequestInterface $request
    ) {
        $index = $routeParams[static::ROUTE_KEY_INDEX];

        return static::readRelationship($index, Schema::REL_EMOTIONS, $container, $request);
    }
}
