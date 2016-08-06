<?php namespace Limoncello\JsonApi\Http\Cors;

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

use Limoncello\JsonApi\Contracts\Http\Cors\CorsStorageInterface;

/**
 * @package App
 */
class CorsStorage implements CorsStorageInterface
{
    /**
     * @var null|array
     */
    private $headers = null;

    /**
     * @inheritdoc
     */
    public function hasHeaders()
    {
        return $this->headers !== null;
    }

    /**
     * @inheritdoc
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @inheritdoc
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }
}
