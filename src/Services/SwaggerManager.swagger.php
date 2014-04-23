<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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

use DreamFactory\Platform\Events\Enums\SwaggerEvents;
use DreamFactory\Platform\Services\SwaggerManager;

$_description = <<<HTML
HTML;

return array(
    'swaggerVersion' => SwaggerManager::SWAGGER_VERSION,
    'apiVersion'     => API_VERSION,
    'authorizations' => array('apiKey' => array('type' => 'apiKey', 'passAs' => 'header')),
    'info'           => array(
        'title'       => 'DreamFactory Live API Documentation',
        'description' => $_description,
        //'termsOfServiceUrl' => 'http://www.dreamfactory.com/terms/',
        'contact'     => 'support@dreamfactory.com',
        'license'     => 'Apache 2.0',
        'licenseUrl'  => 'http://www.apache.org/licenses/LICENSE-2.0.html'
    ),
    /**
     * The events thrown that are relevant to Swagger
     */
    'events'         => array(
        SwaggerEvents::CACHE_CLEARED,
        SwaggerEvents::CACHE_REBUILT,
    ),
);
