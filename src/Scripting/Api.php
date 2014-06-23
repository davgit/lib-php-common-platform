<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) SDK For PHP
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2014 DreamFactory Software, Inc. <support@dreamfactory.com>
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
namespace DreamFactory\Platform\Scripting;

use DreamFactory\Platform\Resources\System\Config;
use DreamFactory\Platform\Resources\System\User;
use DreamFactory\Platform\Services\SwaggerManager;
use DreamFactory\Platform\Utility\Platform;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Converts a Swagger configuration file into an API object consumable by server-side scripts
 */
class Api
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var \stdClass
     */
    protected static $_apiObject = null;
    /**
     * @var string The Swagger cache
     */
    protected $_cachePath;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param string $swaggerPath If specified, used as Swagger base path
     */
    public function __construct( $swaggerPath = null )
    {
        $swaggerPath = $swaggerPath ? : Platform::getSwaggerPath();

        $this->_cachePath = $swaggerPath . '/cache';
        static::$_apiObject = Platform::mcGet( 'scripting.api_object' );
    }

    /**
     * Convenience method to instantiate and return an API object
     *
     * @param bool $force If true, bust cache and rebuild
     *
     * @return \stdClass
     */
    public static function getScriptingObject( $force = false )
    {
        $_parser = new static();

        return $_parser->buildApi( true );
    }

    /**
     * Reads the Swagger configuration and rebuilds the server-side scripting API
     *
     * @param bool $force If true, rebuild regardless of cached state
     *
     * @return \stdClass
     */
    public function buildApi( $force = false )
    {
        if ( !empty( static::$_apiObject ) && !$force )
        {
            return static::$_apiObject;
        }

        if ( false === ( $_base = $this->_loadCacheFile() ) )
        {
            return false;
        }

        $_apiObject = new \stdClass();

        if ( isset( $_base['apis'] ) )
        {
            foreach ( $_base['apis'] as $_service )
            {
                $_apiObject->{$_resourcePath} = $this->_buildServiceApi( $_service, $_resourcePath );
                unset( $_service );
            }
        }
        else
        {
            Log::error( 'Error parsing swagger, no APIs defined: ' . print_r( $_base, true ) );
        }

        //	Store it
        Platform::mcSet( 'scripting.api_object', static::$_apiObject = $_apiObject );

        unset( $_apiObject );

        return static::$_apiObject;
    }

    /**
     * @param array  $service
     * @param string $resourcePath Will return the resource_path of the parsed service
     *
     * @return \stdClass
     */
    protected function _buildServiceApi( $service, &$resourcePath )
    {
        $_path = str_replace( '/', null, $service['path'] );

        if ( false === ( $_cacheFile = $this->_loadCacheFile( $_path ) ) )
        {
            return false;
        }

        if ( false !== ( strpos( $resourcePath = Option::get( $_cacheFile, 'resourcePath', $_path ), '/', 0 ) ) )
        {
            $resourcePath = ltrim( $resourcePath, '/' );
        }

        return $this->_buildServiceOperations( $_cacheFile['apis'], $resourcePath );
    }

    /**
     * Parses the operations of a service
     *
     * @param array  $serviceApis The list of APIs with operations to parse
     * @param string $resourcePath
     *
     * @return \stdClass
     */
    protected function _buildServiceOperations( array $serviceApis = array(), $resourcePath )
    {
        $_service = new \stdClass();

        foreach ( $serviceApis as $_api )
        {
            if ( !isset( $_api['operations'] ) )
            {
                continue;
            }

            foreach ( $_api['operations'] as $_operation )
            {
                if ( !isset( $_operation['nickname'] ) ||
                     !isset( $_operation['method'] ) ||
                     !isset( $_api['path'] ) ||
                     false === strpos( $_api['path'], '{table_name}' )
                )
                {
                    continue;
                }

                //	Function data
                $_nickname = $_operation['nickname'];

                $_arguments = array(
                    'method' => $_operation['method'],
                    'path'   => ltrim( $_api['path'], '/' ),
                );

                $_service->{$_nickname} = null;

                unset( $_operation, $_arguments );
            }

            unset( $_api );
        }

        return $_service;
    }

    /**
     * Loads and returns the Swagger cache
     *
     * @param string $cacheFile The name of the cache to load, or null for the base
     *
     * @return bool
     */
    protected function _loadCacheFile( $cacheFile = null )
    {
        $_json = null;
        $_file = $this->_cachePath . ( $cacheFile ? '/' . $cacheFile . '.json' : SwaggerManager::SWAGGER_CACHE_FILE );

        if ( !file_exists( $_file ) )
        {
            SwaggerManager::getSwagger();
        }

        if ( false === ( $_json = file_get_contents( $_file ) ) || empty( $_json ) )
        {
            Log::error( 'Unable to open Swagger cache file: ' . $_file );
        }

        $_cache = json_decode( $_json, true );

        if ( empty( $_cache ) || JSON_ERROR_NONE !== json_last_error() )
        {
            Log::error( 'No Swagger cache or invalid JSON detected.' );

            return false;
        }

        return $_cache;
    }

}
