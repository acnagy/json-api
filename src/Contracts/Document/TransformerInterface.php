<?php namespace Limoncello\JsonApi\Contracts\Document;

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
use Neomerx\JsonApi\Exceptions\ErrorCollection;

/**
 * @package Limoncello\JsonApi
 */
interface TransformerInterface
{
    /**
     * Indicate transformations to start.
     *
     * @return void
     */
    public function transformationsToStart();

    /**
     * Indicate transformations completed.
     *
     * @param ErrorCollection $errors
     *
     * @return void
     */
    public function transformationsFinished(ErrorCollection $errors);

    /**
     * @param string $type
     *
     * @return bool
     */
    public function isValidType($type);

    /**
     * @param string|int|null $index
     *
     * @return bool
     */
    public function isValidId($index);

    /**
     * @param ErrorCollection $errors
     * @param array           $jsonAttributes
     *
     * @return array
     */
    public function transformAttributes(ErrorCollection $errors, array $jsonAttributes);

    /**
     * @param ErrorCollection             $errors
     * @param string                      $jsonName
     * @param ResourceIdentifierInterface $identifier
     *
     * @return array|null
     */
    public function transformToOneRelationship(
        ErrorCollection $errors,
        $jsonName,
        ResourceIdentifierInterface $identifier = null
    );

    /**
     * @param ErrorCollection               $errors
     * @param string                        $jsonName
     * @param ResourceIdentifierInterface[] $identifiers
     *
     * @return array|null
     */
    public function transformToManyRelationship(
        ErrorCollection $errors,
        $jsonName,
        array $identifiers
    );
}
