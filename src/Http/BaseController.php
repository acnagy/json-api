<?php namespace Limoncello\JsonApi\Http;

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
use Limoncello\JsonApi\Contracts\Api\CrudInterface;
use Limoncello\JsonApi\Contracts\Document\ParserInterface;
use Limoncello\JsonApi\Contracts\Document\ResourceInterface;
use Limoncello\JsonApi\Contracts\Document\TransformerInterface;
use Limoncello\JsonApi\Contracts\FactoryInterface;
use Limoncello\JsonApi\Contracts\Http\ControllerInterface;
use Limoncello\JsonApi\Contracts\Schema\ContainerInterface as SchemesContainerInterface;
use Limoncello\JsonApi\Contracts\Schema\SchemaInterface;
use Limoncello\JsonApi\Transformer\BaseQueryTransformer;
use Limoncello\Models\Contracts\SchemaStorageInterface;
use Neomerx\JsonApi\Contracts\Encoder\Parameters\EncodingParametersInterface;
use Neomerx\JsonApi\Contracts\Http\Query\QueryParametersParserInterface;
use Neomerx\JsonApi\Exceptions\JsonApiException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @package Limoncello\JsonApi
 */
abstract class BaseController implements ControllerInterface
{
    /** API class name */
    const API_CLASS = null;

    /** JSON API Schema class name */
    const SCHEMA_CLASS = null;

    /** Transformer class name */
    const CREATE_TRANSFORMER_CLASS = null;

    /** Transformer class name */
    const UPDATE_TRANSFORMER_CLASS = null;

    /** URI key used in routing table */
    const ROUTE_KEY_INDEX = null;

    /**
     * @inheritdoc
     */
    public static function index(array $routeParams, ContainerInterface $container, ServerRequestInterface $request)
    {
        /** @var QueryParametersParserInterface $queryParser */
        $queryParser    = $container->get(QueryParametersParserInterface::class);
        $encodingParams = $queryParser->parse($request);

        list ($filters, $sorts, $includes, $paging) =
            static::mapQueryParameters($container, $encodingParams, static::SCHEMA_CLASS);

        $modelData = self::createApi($container)->index($filters, $sorts, $includes, $paging);
        $responses = static::createResponses($container, $request, $encodingParams);
        $response  = $modelData->getPaginatedData()->getData() === null ?
            $responses->getCodeResponse(404) : $responses->getContentResponse($modelData);

        return $response;
    }

    /**
     * @inheritdoc
     */
    public static function create(array $routeParams, ContainerInterface $container, ServerRequestInterface $request)
    {
        $resource = self::parseInputOnCreate($container, $request);
        $index    = self::createApi($container)->create($resource);
        $data     = self::createApi($container)->read($index);
        $response = static::createResponses($container, $request)->getCreatedResponse($data);

        return $response;
    }

    /**
     * @inheritdoc
     */
    public static function read(array $routeParams, ContainerInterface $container, ServerRequestInterface $request)
    {
        /** @var QueryParametersParserInterface $queryParser */
        $queryParser    = $container->get(QueryParametersParserInterface::class);
        $encodingParams = $queryParser->parse($request);

        list ($filters, , $includes) = static::mapQueryParameters($container, $encodingParams, static::SCHEMA_CLASS);

        $index     = $routeParams[static::ROUTE_KEY_INDEX];
        $modelData = self::createApi($container)->read($index, $filters, $includes);
        $responses = static::createResponses($container, $request, $encodingParams);
        $response  = $modelData->getPaginatedData()->getData() === null ?
            $responses->getCodeResponse(404) : $responses->getContentResponse($modelData);

        return $response;
    }

    /**
     * @inheritdoc
     */
    public static function update(array $routeParams, ContainerInterface $container, ServerRequestInterface $request)
    {
        $resource = self::parseInputOnUpdate($container, $request);
        $index    = $routeParams[static::ROUTE_KEY_INDEX];
        self::createApi($container)->update($index, $resource);
        $data     = self::createApi($container)->read($index);
        $response = static::createResponses($container, $request)->getContentResponse($data);

        return $response;
    }

    /**
     * @inheritdoc
     */
    public static function delete(array $routeParams, ContainerInterface $container, ServerRequestInterface $request)
    {
        self::createApi($container)->delete($routeParams[static::ROUTE_KEY_INDEX]);
        $response = static::createResponses($container, $request)->getCodeResponse(204);

        return $response;
    }

    /**
     * @param string                 $index
     * @param string                 $relationshipName
     * @param ContainerInterface     $container
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    protected static function readRelationship(
        $index,
        $relationshipName,
        ContainerInterface $container,
        ServerRequestInterface $request
    ) {
        /** @var QueryParametersParserInterface $queryParser */
        $queryParser    = $container->get(QueryParametersParserInterface::class);
        $encodingParams = $queryParser->parse($request);

        /** @var SchemaStorageInterface $modelSchemaStorage */
        $modelSchemaStorage = $container->get(SchemaStorageInterface::class);
        /** @var SchemesContainerInterface $jsonSchemaStorage */
        $jsonSchemaStorage  = $container->get(SchemesContainerInterface::class);
        /** @var SchemaInterface $schemaClass */
        $schemaClass  = static::SCHEMA_CLASS;
        $modelRelName = $schemaClass::getMappings()[$schemaClass::SCHEMA_RELATIONSHIPS][$relationshipName];
        list ($reverseModelClass) = $modelSchemaStorage->getReverseRelationship($schemaClass::MODEL, $modelRelName);

        $targetSchema      = $jsonSchemaStorage->getSchemaByType($reverseModelClass);
        $targetSchemaClass = get_class($targetSchema);
        list ($filters, $sorts, , $paging) =
            static::mapQueryParameters($container, $encodingParams, $targetSchemaClass);

        $relData = self::createApi($container)->readRelationship($index, $modelRelName, $filters, $sorts, $paging);

        $responses = static::createResponses($container, $request, $encodingParams);
        $response  = $relData->getData() === null ?
            $responses->getCodeResponse(404) : $responses->getContentResponse($relData);

        return $response;
    }

    /**
     * @param ContainerInterface $container
     *
     * @return CrudInterface
     */
    protected static function createApi(ContainerInterface $container)
    {
        $apiClass = static::API_CLASS;
        $api      = new $apiClass($container);

        return $api;
    }

    /**
     * @param ContainerInterface     $container
     * @param ServerRequestInterface $request
     *
     * @return ResourceInterface|null
     */
    protected static function parseInputOnCreate(ContainerInterface $container, ServerRequestInterface $request)
    {
        $transformerClass = static::CREATE_TRANSFORMER_CLASS;
        /** @var TransformerInterface $transformer */
        $transformer      = new $transformerClass($container);

        /** @var FactoryInterface $factory */
        $factory    = $container->get(FactoryInterface::class);
        $translator = $factory->createTranslator();
        $parsed     = self::parseBody($factory->createParser($transformer, $translator), $request);

        return $parsed;
    }

    /**
     * @param ContainerInterface     $container
     * @param ServerRequestInterface $request
     *
     * @return ResourceInterface|null
     */
    protected static function parseInputOnUpdate(ContainerInterface $container, ServerRequestInterface $request)
    {
        $transformerClass = static::UPDATE_TRANSFORMER_CLASS;
        /** @var TransformerInterface $transformer */
        $transformer      = new $transformerClass($container);

        /** @var FactoryInterface $factory */
        $factory    = $container->get(FactoryInterface::class);
        $translator = $factory->createTranslator();
        $parsed     = self::parseBody($factory->createParser($transformer, $translator), $request);

        return $parsed;
    }

    /**
     * @param ContainerInterface          $container
     * @param EncodingParametersInterface $parameters
     * @param string                      $schemaClass
     *
     * @return array
     */
    protected static function mapQueryParameters(
        ContainerInterface $container,
        EncodingParametersInterface $parameters,
        $schemaClass
    ) {
        /** @var FactoryInterface $factory */
        $factory          = $container->get(FactoryInterface::class);
        $errors           = $factory->createErrorCollection();
        $queryTransformer = new BaseQueryTransformer(
            $container->get(SchemaStorageInterface::class),
            $container->get(SchemesContainerInterface::class),
            $factory->createTranslator(),
            $schemaClass
        );

        $result = $queryTransformer->mapParameters($errors, $parameters);
        if ($errors->count() > 0) {
            throw new JsonApiException($errors);
        }

        return $result;
    }

    /**
     * @param ParserInterface        $parser
     * @param ServerRequestInterface $request
     *
     * @return ResourceInterface|null
     */
    private static function parseBody(ParserInterface $parser, ServerRequestInterface $request)
    {
        $text   = (string)$request->getBody();
        $parsed = $parser->parse($text);

        if ($parser->getErrors()->count() > 0 || $parsed === null) {
            throw new JsonApiException($parser->getErrors());
        }

        return $parsed;
    }
}
