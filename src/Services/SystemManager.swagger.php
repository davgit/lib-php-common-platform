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
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

$_base = require( __DIR__ . '/BasePlatformRestSvc.swagger.php' );

$_base['apis'] = array(
    array(
        'path'        => '/{api_name}',
        'operations'  => array(
            0 => array(
                'method'     => 'GET',
                'summary'    => 'getResources() - List resources available for system management.',
                'nickname'   => 'getResources',
                'type'       => 'Resources',
                'notes'      => 'See listed operations for each resource available.',
                'event_name' => '{api_name}.list',
            ),
        ),
        'description' => 'Operations available for system management.',
    ),
);

$_base['models'] = array(
    'Metadata' => array(
        'id'         => 'Metadata',
        'properties' => array(
            'schema' => array(
                'type'        => 'Array',
                'description' => 'Array of table schema.',
                'items'       => array(
                    'type' => 'string',
                ),
            ),
            'count'  => array(
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Record count returned for GET requests.',
            ),
        ),
    ),
);

//  Load resources
$_namespaces = array('DreamFactory\\Platform\\Resources\\System'); //Pii::app()->getResourceNamespaces();
Log::debug( '  * Discovering resources' );

foreach ( $_namespaces as $_namespace )
{
    $_resourcePath = __DIR__ . '/../' . str_replace( 'DreamFactory/Platform', null, str_replace( '\\', '/', $_namespace ) );

    foreach ( FileSystem::glob( $_resourcePath . '/*.swagger.php' ) as $_file )
    {
        $_load = array();
        $_key = strtolower( str_replace( '.swagger.php', null, $_file ) );

        /** @noinspection PhpIncludeInspection */
        $_load[ $_key ] = require( $_resourcePath . '/' . $_file );
        $_base['apis'] = array_merge( $_base['apis'], Option::get( $_load[ $_key ], 'apis', array() ) );
        $_base['models'] = array_merge( $_base['models'], Option::get( $_load[ $_key ], 'models', array() ) );

        Log::debug( '    * Found ' . $_file );
        unset( $_load );
    }
}

unset( $_resourcePath, $_namespaces );

return $_base;
