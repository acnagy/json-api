<?php namespace Limoncello\JsonApi\Contracts\Models;

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

/**
 * @package Limoncello\Models
 */
interface TagStorageInterface
{
    /**
     * @param mixed  $item
     * @param string $tag
     *
     * @return void
     */
    public function register($item, $tag);

    /**
     * @param mixed    $item
     * @param string[] $tags
     *
     * @return void
     */
    public function registerArray($item, array $tags);

    /**
     * @param string $tag
     *
     * @return array
     */
    public function get($tag);
}
