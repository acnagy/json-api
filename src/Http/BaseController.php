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
use Limoncello\JsonApi\Contracts\Adapters\PaginationStrategyInterface;
use Limoncello\JsonApi\Contracts\Adapters\RepositoryInterface;
use Limoncello\JsonApi\Contracts\Api\CrudInterface;
use Limoncello\JsonApi\Contracts\Config\JsonApiConfigInterface;
use Limoncello\JsonApi\Contracts\Encoder\EncoderInterface;
use Limoncello\JsonApi\Contracts\FactoryInterface;
use Limoncello\JsonApi\Contracts\Http\ControllerInterface;
use Limoncello\JsonApi\Contracts\I18n\TranslatorInterface;
use Limoncello\JsonApi\Contracts\Models\ModelSchemesInterface;
use Limoncello\JsonApi\Contracts\Models\PaginatedDataInterface;
use Limoncello\JsonApi\Contracts\Schema\JsonSchemesInterface;
use Limoncello\JsonApi\Contracts\Schema\SchemaInterface;
use Neomerx\JsonApi\Contracts\Encoder\Parameters\EncodingParametersInterface;
use Neomerx\JsonApi\Contracts\Http\Headers\MediaTypeInterface;
use Neomerx\JsonApi\Contracts\Http\Query\QueryParametersParserInterface;
use Neomerx\JsonApi\Contracts\Http\ResponsesInterface;
use Neomerx\JsonApi\Exceptions\JsonApiException;
use Neomerx\JsonApi\Http\Headers\MediaType;
use Neomerx\JsonApi\Http\Headers\SupportedExtensions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @package Limoncello\JsonApi
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class BaseController implements ControllerInterface
{
    /** API class name */
    const API_CLASS = null;

    /** JSON API Schema class name */
    const SCHEMA_CLASS = null;

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

        $modelData = static::createApi($container)->index($filters, $sorts, $includes, $paging);
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
        list ($attributes, $toMany) = static::parseInputOnCreate($container, $request);

        $index = self::createApi($container)->create($attributes, $toMany);
        $data  = self::createApi($container)->read($index);

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
        $index    = $routeParams[static::ROUTE_KEY_INDEX];
        list ($attributes, $toMany) = static::parseInputOnUpdate($index, $container, $request);
        $updated   = self::createApi($container)->update($index, $attributes, $toMany);
        $responses = static::createResponses($container, $request);

        if ($updated <= 0) {
            return $responses->getCodeResponse(404);
        }

        $modelData = self::createApi($container)->read($index);
        $response  = $responses->getContentResponse($modelData);

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
        /** @var PaginatedDataInterface $relData */
        /** @var EncodingParametersInterface $encodingParams */
        list ($relData, $encodingParams) = self::readRelationshipData($index, $relationshipName, $container, $request);

        $responses = static::createResponses($container, $request, $encodingParams);
        $response  = $relData->getData() === null ?
            $responses->getCodeResponse(404) : $responses->getContentResponse($relData);

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
    protected static function readRelationshipIdentifiers(
        $index,
        $relationshipName,
        ContainerInterface $container,
        ServerRequestInterface $request
    ) {
        /** @var PaginatedDataInterface $relData */
        /** @var EncodingParametersInterface $encodingParams */
        list ($relData, $encodingParams) = self::readRelationshipData($index, $relationshipName, $container, $request);

        $responses = static::createResponses($container, $request, $encodingParams);
        $response  = $relData->getData() === null ?
            $responses->getCodeResponse(404) : $responses->getIdentifiersResponse($relData);

        return $response;
    }

    /**
     * @param ContainerInterface $container
     *
     * @return CrudInterface
     */
    protected static function createApi(ContainerInterface $container)
    {
        $factory            = $container->get(FactoryInterface::class);
        $repository         = $container->get(RepositoryInterface::class);
        $modelSchemes       = $container->get(ModelSchemesInterface::class);
        $paginationStrategy = $container->get(PaginationStrategyInterface::class);

        $apiClass = static::API_CLASS;
        $api      = new $apiClass($factory, $repository, $modelSchemes, $paginationStrategy, $container);

        return $api;
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
        $factory = $container->get(FactoryInterface::class);
        $errors  = $factory->createErrorCollection();
        $queryTransformer = new QueryTransformer(
            $container->get(ModelSchemesInterface::class),
            $container->get(JsonSchemesInterface::class),
            $container->get(TranslatorInterface::class),
            $schemaClass
        );

        $result = $queryTransformer->mapParameters($errors, $parameters);
        if ($errors->count() > 0) {
            throw new JsonApiException($errors);
        }

        return $result;
    }

    /**
     * @param ContainerInterface               $container
     * @param ServerRequestInterface           $request
     * @param EncodingParametersInterface|null $parameters
     *
     * @return ResponsesInterface
     */
    protected static function createResponses(
        ContainerInterface $container,
        ServerRequestInterface $request,
        EncodingParametersInterface $parameters = null
    ) {
        /** @var EncoderInterface $encoder */
        $encoder = $container->get(EncoderInterface::class);
        $encoder->forOriginalUri($request->getUri());

        /** @var JsonApiConfigInterface $config */
        $config    = $container->get(JsonApiConfigInterface::class);
        $urlPrefix = $config
            ->getConfig()[JsonApiConfigInterface::KEY_JSON][JsonApiConfigInterface::KEY_JSON_URL_PREFIX];

        /** @var JsonSchemesInterface $jsonSchemes */
        $jsonSchemes = $container->get(JsonSchemesInterface::class);
        $responses   = new Responses(
            new MediaType(MediaTypeInterface::JSON_API_TYPE, MediaTypeInterface::JSON_API_SUB_TYPE),
            new SupportedExtensions(),
            $encoder,
            $jsonSchemes,
            $parameters,
            $urlPrefix
        );

        return $responses;
    }

    /**
     * @param ContainerInterface     $container
     * @param ServerRequestInterface $request
     *
     * @return array
     */
    protected static function parseJson(ContainerInterface $container, ServerRequestInterface $request)
    {
        $body = (string)$request->getBody();
        if (empty($body) === true || ($json = json_decode($body, true)) === null) {
            /** @var FactoryInterface $factory */
            $factory = $container->get(FactoryInterface::class);
            $errors  = $factory->createErrorCollection();
            /** @var TranslatorInterface $translator */
            $translator = $container->get(TranslatorInterface::class);
            $errors->addDataError($translator->get(TranslatorInterface::MSG_ERR_INVALID_ELEMENT));
            throw new JsonApiException($errors);
        }

        return $json;
    }

    /**
     * @param ContainerInterface $container
     *
     * @return SchemaInterface
     */
    protected static function getSchema(ContainerInterface $container)
    {
        /** @var SchemaInterface $schemaClass */
        $schemaClass = static::SCHEMA_CLASS;
        $modelClass  = $schemaClass::MODEL;
        /** @var JsonSchemesInterface $jsonSchemes */
        $jsonSchemes = $container->get(JsonSchemesInterface::class);
        $schema      = $jsonSchemes->getSchemaByType($modelClass);

        return $schema;
    }

    /**
     * @param string                 $index
     * @param string                 $relationshipName
     * @param ContainerInterface     $container
     * @param ServerRequestInterface $request
     *
     * @return array [PaginatedDataInterface, EncodingParametersInterface]
     */
    private static function readRelationshipData(
        $index,
        $relationshipName,
        ContainerInterface $container,
        ServerRequestInterface $request
    ) {
        /** @var QueryParametersParserInterface $queryParser */
        $queryParser    = $container->get(QueryParametersParserInterface::class);
        $encodingParams = $queryParser->parse($request);

        /** @var JsonSchemesInterface $jsonSchemes */
        $jsonSchemes  = $container->get(JsonSchemesInterface::class);
        $targetSchema = $jsonSchemes->getRelationshipSchema(static::SCHEMA_CLASS, $relationshipName);
        list ($filters, $sorts, , $paging) =
            static::mapQueryParameters($container, $encodingParams, get_class($targetSchema));

        /** @var SchemaInterface $schemaClass */
        $schemaClass  = static::SCHEMA_CLASS;
        $modelRelName = $schemaClass::getMappings()[SchemaInterface::SCHEMA_RELATIONSHIPS][$relationshipName];
        $relData = self::createApi($container)->readRelationship($index, $modelRelName, $filters, $sorts, $paging);

        return [$relData, $encodingParams];
    }
}
