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
namespace DreamFactory\Platform\Utility;

use DreamFactory\Platform\Resources\System\Script;
use DreamFactory\Platform\Services\BasePlatformRestService;
use DreamFactory\Platform\Services\SwaggerManager;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Interfaces\HttpMethod;
use Kisma\Core\SeedUtility;
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Schwag
 * General purpose utility methods for Swagger
 */
class Schwag extends SeedUtility
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @const string A cached events list derived from Swagger
     */
    const SWAGGER_EVENT_CACHE_FILE = '/_events.json';
    /**
     * @const string A cached events list derived from Swagger
     */
    const SWAGGER_EVENT_CUBE_FILE = '/_events.cubed.json';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array The events mapped by route then method
     */
    protected static $_eventMap = false;
    /**
     * @var array Events mapped by name
     */
    protected static $_eventCube = array();
    /**
     * @var array The default cube structure
     */
    protected static $_cubeDefaults = array( 'triggers' => array(), );
    /**
     * @var int The default options for json_encode
     */
    protected static $_jsonEncodeOptions = JSON_UNESCAPED_SLASHES;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Rebuilds the event cache from the Swagger definitions
     */
    public static function rebuildEventCache()
    {
        //	Initialize the event map
        static::$_eventMap = static::$_eventMap ? : array();
        static::$_eventCube = static::$_eventCube ? : array();

    }

    /**
     * @param string $cachePath
     * @param array  $map
     * @param array  $cube
     */
    public static function saveEventCache( $cachePath, $map = null, $cube = null )
    {
        if ( is_array( $map ) )
        {
            static::$_eventMap = array_merge( static::$_eventMap, $map );
        }

        if ( is_array( $cube ) )
        {
            static::$_eventCube = array_merge( static::$_eventCube, $cube );
        }

        //	Write event cache file
        if ( !empty( static::$_eventMap ) )
        {
            ksort( static::$_eventMap );

            if ( false ===
                 file_put_contents( $cachePath . static::SWAGGER_EVENT_CACHE_FILE, json_encode( static::$_eventMap, JSON_UNESCAPED_SLASHES ) )
            )
            {
                Log::error( '  * File system error writing events cache file: ' . $cachePath . static::SWAGGER_EVENT_CACHE_FILE );
            }

            //	Write event cube file
            ksort( static::$_eventCube );

            if ( false ===
                 file_put_contents( $cachePath . static::SWAGGER_EVENT_CUBE_FILE, json_encode( static::$_eventCube, JSON_UNESCAPED_SLASHES ) )
            )
            {
                Log::error( '  * File system error writing events cache file: ' . $cachePath . static::SWAGGER_EVENT_CUBE_FILE );
            }
        }
    }

    /**
     * @param string $apiName
     * @param string $content
     */
    public static function buildServiceEvents( $apiName, $content )
    {
        if ( !isset( static::$_eventMap[ $apiName ] ) || !is_array( static::$_eventMap[ $apiName ] ) || empty( static::$_eventMap[ $apiName ] ) )
        {
            static::$_eventMap[ $apiName ] = array();
        }

        //	Parse the events while we get the chance...
        $_serviceEvents = static::parseSwaggerEvents( $apiName, json_decode( $content, true ) );
        $_cube = Option::get( $_serviceEvents, '.cubed', array(), true );

        //	Parse the events while we get the chance...
        static::$_eventMap[ $apiName ] = array_merge(
            Option::clean( static::$_eventMap[ $apiName ] ),
            $_serviceEvents
        );

        if ( !empty( $_cube ) )
        {
            static::$_eventCube[] = $_cube;
            unset( $_cube );
        }
    }

    /**
     * Returns a list of scripts that can response to specified events
     *
     * @param string $apiName
     * @param string $method
     * @param string $eventName Optional event name to try
     *
     * @return array|bool
     */
    protected static function _findScripts( $apiName, $method = HttpMethod::GET, $eventName = null )
    {
        static $_scriptPath;

        if ( empty( $_scriptPath ) )
        {
            $_scriptPath = Platform::getPrivatePath( Script::DEFAULT_SCRIPT_PATH );
        }

//        //  Look for $apiName.$method.*.js
//        $_scriptPattern = strtolower( $apiName ) . '.' . strtolower( $method ) . '.*.js';
//        $_scripts = FileSystem::glob( $_scriptPath . '/' . $_scriptPattern );
//
//        //  Look for $apiName*.js (i.e. {table.list}.js)
//        if ( empty( $_scripts ) && strpos( $apiName, '.' ) )
//        {
//            $_scriptPattern = strtolower( preg_replace( '#\{(.*)+\}#', '#*#', $apiName ) ) . '.js';
//            $_scripts = FileSystem::glob( $_scriptPath . '/' . $_scriptPattern );
//        }

        //  Look for specific REST-mapped, or "named", event scripts (api_name.method.*.js, i.e. user.get.pre_process.js)
        $_scriptPattern = strtolower( $apiName ) . '.' . strtolower( $method ) . '.*.js';
        $_scripts = FileSystem::glob( $_scriptPath . '/' . $_scriptPattern );

        if ( null !== $eventName && array() !== ( $_namedScripts = FileSystem::glob( $_scriptPath . '/' . $eventName . '.js' ) ) )
        {
            $_scripts = array_merge( $_scripts, FileSystem::glob( $_scriptPath . '/' . $eventName . '.js' ), $_namedScripts );
        }

        if ( empty( $_scripts ) )
        {
            return array();
        }

        $_response = array();
        $_eventPattern = '/^' . str_replace( array( '.*.js', '.' ), array( null, '\\.' ), $_scriptPattern ) . '\\.(\w)\\.js$/i';

        foreach ( $_scripts as $_script )
        {
            if ( 0 === preg_match( $_eventPattern, $_script ) )
            {
                $_response[] = $_script;
            }
        }

        return $_response;
    }

    /**
     * @param BasePlatformRestService $service
     * @param string                  $method
     * @param string                  $eventName Global search for event name
     *
     * @return string
     */
    public static function findEvent( BasePlatformRestService $service, $method, $eventName = null )
    {
        static $_cache = array();

        $_map = static::getEventMap();

        $_hash = sha1( ( $service ? get_class( $service ) : '*' ) . ( $method = strtolower( $method ) ) );

        if ( isset( $_cache[ $_hash ] ) )
        {
            return $_cache[ $_hash ];
        }

        //  Global search by name
        if ( null !== $eventName )
        {
            foreach ( $_map as $_path )
            {
                foreach ( $_path as $_method => $_info )
                {
                    if ( $_method != $method )
                    {
                        continue;
                    }

                    if ( $eventName == ( $_eventName = Option::get( $_info, 'event' ) ) )
                    {
                        $_cache[ $_hash ] = $_eventName;

                        return true;
                    }
                }
            }

            return false;
        }

        $_apiName = $service->getApiName();
        $_resource = $service->getResource() ? : $_apiName;

        if ( empty( $_resource ) )
        {
            $_resource = $service->getApiName();
        }

        if ( null === ( $_resources = Option::get( $_map, $_resource ) ) )
        {
            if ( !method_exists( $service, 'getServiceName' ) || null === ( $_resources = Option::get( $_map, $service->getServiceName() ) ) )
            {
                if ( null === ( $_resources = Option::get( $_map, 'system' ) ) )
                {
                    return null;
                }
            }
        }

        $_path = str_replace( 'rest', null, trim( !Pii::cli() ? Pii::app()->getRequestObject()->getPathInfo() : $service->getResourcePath(), '/' ) );

        if ( empty( $_path ) )
        {
            return null;
        }

//        //  Look for $apiName.$method.*.js
//        $_scriptPattern = strtolower( $apiName ) . '.' . strtolower( $method ) . '.*.js';
//        $_scripts = FileSystem::glob( $_scriptPath . '/' . $_scriptPattern );
//
//        //  Look for $apiName*.js (i.e. {table.list}.js)
//        if ( empty( $_scripts ) && strpos( $apiName, '.' ) )
//        {
//            $_scriptPattern = strtolower( preg_replace( '#\{(.*)+\}#', '#*#', $apiName ) ) . '.js';
//            $_scripts = FileSystem::glob( $_scriptPath . '/' . $_scriptPattern );
//        }

        $_pattern = '@^' . preg_replace( '/\\\:[a-zA-Z0-9\_\-]+/', '([a-zA-Z0-9\-\_]+)', preg_quote( $_path ) ) . '$@D';

        $_matches = preg_grep( $_pattern, array_keys( $_resources ) );

        if ( empty( $_matches ) )
        {
            //	See if there is an event with /system at the front...
            $_pattern = '@^' . preg_replace( '/\\\:[a-zA-Z0-9\_\-]+/', '([a-zA-Z0-9\-\_]+)', preg_quote( $_path ) ) . '$@D';
            $_matches = preg_grep( $_pattern, array_keys( $_resources ) );

            if ( empty( $_matches ) )
            {
                return null;
            }
        }

        foreach ( $_matches as $_match )
        {
            $_methodInfo = Option::getDeep( $_resources, $_match, $method );

            if ( null !== ( $_eventName = Option::get( $_methodInfo, 'event' ) ) )
            {
                return $_cache[ $_hash ] = $_eventName;
            }
        }

        return null;
    }

    /**
     * Retrieves the cached event map or triggers a rebuild
     *
     * @return array
     */
    public static function getEventMap()
    {
        if ( !empty( static::$_eventMap ) )
        {
            return static::$_eventMap;
        }

        $_cachePath = Platform::getSwaggerPath( SwaggerManager::SWAGGER_CACHE_DIR );
        $_encoded = @file_get_contents( $_cachePath . static::SWAGGER_EVENT_CACHE_FILE );

        if ( !empty( $_encoded ) )
        {
            if ( false === ( static::$_eventMap = json_decode( $_encoded, true ) ) )
            {
                Log::error( '  * Event cache file appears corrupt, or cannot be read.' );
            }
        }

        //	If we still have no event map, build it.
        if ( empty( static::$_eventMap ) )
        {
            static::rebuildEventCache();
        }

        return static::$_eventMap;
    }

    /**
     * @param string $apiName
     * @param array  $data
     *
     * @return array
     */
    public static function parseSwaggerEvents( $apiName, $data )
    {
        $_eventCube = $_eventMap = array();

        foreach ( Option::get( $data, 'apis', array() ) as $_api )
        {
            $_scripts = $_events = array();

            $_path = str_replace(
                array( '{api_name}', '/' ),
                array( $apiName, '.' ),
                trim( Option::get( $_api, 'path' ), '/' )
            );

            foreach ( Option::get( $_api, 'operations', array() ) as $_operation )
            {
                if ( null !== ( $_swaggerEvents = Option::get( $_operation, 'event_name' ) ) )
                {
                    $_method = strtolower( Option::get( $_operation, 'method', HttpMethod::GET ) );

                    $_scripts = array();
                    $_eventsThrown = array();
                    $_eventName = null;

                    foreach ( Option::clean( $_swaggerEvents ) as $_eventName )
                    {
                        $_scripts += static::_findScripts( $_path, $_method, $_eventName );

                        $_eventsThrown[] = str_ireplace(
                            array( '{api_name}', '{action}', '{request.method}' ),
                            array( $apiName, $_method, $_method ),
                            $_eventName
                        );
                    }

                    $_events[ $_method ] = array(
                        'event'   => $_eventsThrown,
                        'scripts' => $_scripts,
                    );

                    //  Set defaults
                    if ( !in_array( $_eventName, $_eventCube ) )
                    {
                        $_eventCube[ $_eventName ] = static::$_cubeDefaults;
                    }

                    $_eventCube[ $_eventName ]['triggers'][ $_path ][] = $_scripts;
                }

            }

            $_eventMap[ str_ireplace( '{api_name}', $apiName, $_api['path'] ) ] = $_events;

            unset( $_scripts, $_events, $_api );
        }

        return $_eventMap;
    }

    /**
     * @return array
     */
    public static function getCubeDefaults()
    {
        return static::$_cubeDefaults;
    }

    /**
     * @param array $cubeDefaults
     */
    public static function setCubeDefaults( $cubeDefaults )
    {
        static::$_cubeDefaults = $cubeDefaults;
    }

    /**
     * @return array
     */
    public static function getEventCube()
    {
        return static::$_eventCube;
    }

    /**
     * @return int
     */
    public static function getJsonEncodeOptions()
    {
        return static::$_jsonEncodeOptions;
    }

    /**
     * @param int $jsonEncodeOptions
     */
    public static function setJsonEncodeOptions( $jsonEncodeOptions )
    {
        static::$_jsonEncodeOptions = $jsonEncodeOptions;
    }

}
