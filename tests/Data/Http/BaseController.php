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

/**
 * @package Limoncello\Tests\JsonApi
 */
abstract class BaseController extends \Limoncello\JsonApi\Http\BaseController
{
    /** URI key used in routing table */
    const ROUTE_KEY_INDEX = 'idx';

    /** API URI prefix */
    const API_URI_PREFIX = '/api/v1';
}
