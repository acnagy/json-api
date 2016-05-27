<?php namespace Limoncello\JsonApi\Encoder;

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

use Closure;
use Limoncello\JsonApi\Contracts\Adapters\PaginationStrategyInterface;
use Limoncello\JsonApi\Contracts\Encoder\EncoderInterface;
use Limoncello\JsonApi\Contracts\ModelsDataInterface;
use Limoncello\JsonApi\Contracts\Schema\ContainerInterface;
use Limoncello\Models\Contracts\PaginatedDataInterface;
use Neomerx\JsonApi\Contracts\Document\DocumentInterface;
use Neomerx\JsonApi\Contracts\Encoder\Parameters\EncodingParametersInterface;
use Neomerx\JsonApi\Contracts\Factories\FactoryInterface;
use Neomerx\JsonApi\Contracts\Http\Query\QueryParametersParserInterface;
use Neomerx\JsonApi\Encoder\EncoderOptions;
use Psr\Http\Message\UriInterface;

/**
 * @package Limoncello\JsonApi
 */
class Encoder extends \Neomerx\JsonApi\Encoder\Encoder implements EncoderInterface
{
    /**
     * @var ContainerInterface
     */
    private $schemesContainer;

    /**
     * @var UriInterface
     */
    private $originalUri;

    /**
     * @param FactoryInterface    $factory
     * @param ContainerInterface  $container
     * @param EncoderOptions|null $encoderOptions
     */
    public function __construct(
        FactoryInterface $factory,
        ContainerInterface $container,
        EncoderOptions $encoderOptions = null
    ) {
        parent::__construct($factory, $container, $encoderOptions);
        $this->schemesContainer = $container;
    }

    /**
     * @inheritdoc
     */
    public function forOriginalUri(UriInterface $uri)
    {
        $this->originalUri = $uri;
    }

    /**
     * @inheritdoc
     */
    public function encodeData($data, EncodingParametersInterface $parameters = null)
    {
        $data = $this->handleRelationshipStorageAndPagingData($data);
        return parent::encodeData($data, $parameters);
    }

    /**
     * @inheritdoc
     */
    public function encodeIdentifiers($data, EncodingParametersInterface $parameters = null)
    {
        $data = $this->handleRelationshipStorageAndPagingData($data);
        return parent::encodeIdentifiers($data, $parameters);
    }

    /**
     * @return ContainerInterface
     */
    protected function getSchemesContainer()
    {
        return $this->schemesContainer;
    }

    /**
     * @return UriInterface
     */
    protected function getOriginalUri()
    {
        return $this->originalUri;
    }

    /**
     * @param mixed $data
     *
     * @return mixed
     */
    private function handleRelationshipStorageAndPagingData($data)
    {
        if ($data instanceof ModelsDataInterface) {
            /** @var ModelsDataInterface $data */
            $storage = $data->getRelationshipStorage();
            $storage === null ?: $this->getSchemesContainer()->setRelationshipStorage($storage);
            $data = $data->getPaginatedData();
        }

        if ($data instanceof PaginatedDataInterface) {
            /** @var PaginatedDataInterface $data */
            $this->addPagingLinksIfNeeded($data);
            $data = $data->getData();
        }

        /** @var mixed $data */

        return $data;
    }

    /**
     * @param PaginatedDataInterface $data
     *
     * @return void
     */
    private function addPagingLinksIfNeeded(PaginatedDataInterface $data)
    {
        if ($data->isCollection() === true &&
            (0 < $data->getOffset() || $data->hasMoreItems() === true) &&
            $this->getOriginalUri() !== null
        ) {
            $links             = [];
            $createLinkClosure = $this->createLinkClosure($data->getSize());
            if (0 < $data->getOffset()) {
                $prevOffset = max(0, $data->getOffset() - $data->getSize());
                $links[DocumentInterface::KEYWORD_PREV] = $createLinkClosure($prevOffset);
            }

            if ($data->hasMoreItems() === true) {
                $nextOffset = $data->getOffset() + $data->getSize();
                $links[DocumentInterface::KEYWORD_NEXT] = $createLinkClosure($nextOffset);
            }

            $this->withLinks($links);
        }
    }

    /**
     * @param int $pageSize
     *
     * @return Closure
     */
    private function createLinkClosure($pageSize)
    {
        parse_str($this->getOriginalUri()->getQuery(), $queryParams);

        return function ($offset) use ($pageSize, $queryParams) {
            $paramsWithPaging = array_merge($queryParams, [
                QueryParametersParserInterface::PARAM_PAGE => [
                    PaginationStrategyInterface::PARAM_PAGING_SKIP => $offset,
                    PaginationStrategyInterface::PARAM_PAGING_SIZE => $pageSize,
                ]
            ]);
            $newUri  = $this->getOriginalUri()->withQuery(http_build_query($paramsWithPaging));
            $fullUrl = (string)$newUri;
            $link    = $this->getFactory()->createLink($fullUrl, null, true);

            return $link;
        };
    }
}
