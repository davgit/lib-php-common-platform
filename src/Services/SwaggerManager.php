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
namespace DreamFactory\Platform\Services;

use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Events\Enums\SwaggerEvents;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Resources\System\Script;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;
use Kisma\Core\Utility\Sql;

/**
 * SwaggerManager
 * DSP API Documentation manager
 *
 */
class SwaggerManager extends BasePlatformRestService
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @const string The Swagger version
     */
    const SWAGGER_VERSION = '1.2';
    /**
     * @const string The private caching directory
     */
    const SWAGGER_CACHE_DIR = '/cache';
    /**
     * @const string The private cache file
     */
    const SWAGGER_CACHE_FILE = '/_.json';
    /**
     * @const string A cached events list derived from Swagger
     */
    const SWAGGER_EVENT_CACHE_FILE = '/_events.json';
    /**
     * @const string A cached events list derived from Swagger
     */
    const SWAGGER_EVENT_CUBE_FILE = '/_events.cubed.json';
    /**
     * @const string The private storage directory for non-generated files
     */
    const SWAGGER_CUSTOM_DIR = '/custom';
    /**
     * @const string The name of the custom example file
     */
    const SWAGGER_CUSTOM_EXAMPLE_FILE = '/example_service_swagger.json';
    /**
     * @const string Our base API swagger file
     */
    const SWAGGER_BASE_API_FILE = '/SwaggerManager.swagger.php';
    /**
     * @const string When a swagger file is not found for a route, this will be used.
     */
    const SWAGGER_DEFAULT_BASE_FILE = '/BasePlatformRestSvc.swagger.php';

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
    protected static $_cubeDefaults = array('triggers' => array(),);
    /**
     * @var array The core DSP services that are built-in
     */
    protected static $_builtInServices = array(
        array('api_name' => 'user', 'type_id' => 0, 'description' => 'User Login'),
        array('api_name' => 'system', 'type_id' => 0, 'description' => 'System Configuration')
    );

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Create a new SwaggerManager
     */
    public function __construct()
    {
        parent::__construct(
            array(
                'name'          => 'Swagger Documentation Management',
                'apiName'       => 'api_docs',
                'type'          => 'Swagger',
                'type_id'       => PlatformServiceTypes::SYSTEM_SERVICE,
                'description'   => 'Service for a user to see the API documentation provided via Swagger.',
                'is_active'     => true,
                'native_format' => 'json',
            )
        );
    }

    /**
     * Returns a list of registered services
     *
     * @return array
     */
    protected static function _getRegisteredServices()
    {
        if ( null === ( $_services = Platform::storeGet( 'swagger.registered_services' ) ) )
        {
            $_sql = <<<MYSQL
SELECT
	api_name,
	type_id,
	storage_type_id,
	description
FROM
    df_sys_service	
ORDER BY
	api_name ASC
MYSQL;

            $_services = array_merge(
                static::$_builtInServices,
                $_rows = Sql::findAll( $_sql, null, Pii::pdo() )
            );

            Platform::storeSet( 'swagger.registered_services', $_services );
        }

        return $_services;
    }

    /**
     * @return array
     */
    protected function _listResources()
    {
        return static::getSwagger();
    }

    /**
     * @return array|string|bool
     */
    protected function _handleResource()
    {
        if ( HttpMethod::GET != $this->_action )
        {
            return false;
        }

        if ( empty( $this->_resource ) )
        {
            return static::getSwagger();
        }

        return static::getSwaggerForService( $this->_resource );
    }

    /**
     * Internal building method builds all static services and some dynamic
     * services from file annotations, otherwise swagger info is loaded from
     * database or storage files for each service, if it exists.
     *
     * @return array
     * @throws \Exception
     */
    protected static function _buildSwagger()
    {
        Log::info( 'Building Swagger cache' );

        //	Create cache & custom directories
        $_cachePath = Platform::getSwaggerPath( static::SWAGGER_CACHE_DIR );
        $_customPath = Platform::getSwaggerPath( static::SWAGGER_CUSTOM_DIR );
        $_templatePath = Platform::getLibraryTemplatePath( '/swagger' );

        //	Generate swagger output from file annotations
        $_scanPath = __DIR__;
        $_serviceApis = array();

        //	Initialize the event map
        static::$_eventMap = static::$_eventMap ? : array();
        static::$_eventCube = static::$_eventCube ? : array();

        //  Build the swagger construct
        $_baseSwagger = array(
            'swaggerVersion' => static::SWAGGER_VERSION,
            'apiVersion'     => API_VERSION,
            'basePath'       => Pii::request()->getHostInfo() . '/rest',
        );

        $_services = static::_getRegisteredServices();

        //	Spin through services and pull the configs
        foreach ( $_services as $_service )
        {
            $_content = null;
            $_apiName = Option::get( $_service, 'api_name' );
            $_typeId = (int)Option::get( $_service, 'type_id', PlatformServiceTypes::SYSTEM_SERVICE );
            $_fileName = PlatformServiceTypes::getFileName( $_typeId, $_apiName );

            $_filePath = $_scanPath . '/' . $_fileName . '.swagger.php';

            //	Check php file path, then custom...
            if ( file_exists( $_filePath ) )
            {
                /** @noinspection PhpIncludeInspection */
                $_fromFile = require( $_filePath );

                if ( is_array( $_fromFile ) && !empty( $_fromFile ) )
                {
                    $_content = json_encode( array_merge( $_baseSwagger, $_fromFile ), JSON_UNESCAPED_SLASHES );
                }
            }
            else //	Check custom path, uses api name
            {
                $_filePath = $_customPath . '/' . $_apiName . '.json';

                if ( file_exists( $_filePath ) )
                {
                    $_fromFile = file_get_contents( $_filePath );

                    if ( !empty( $_fromFile ) )
                    {
                        $_content = $_fromFile;
                    }
                }
                else
                {
                    $_filePath = $_customPath . '/' . $_apiName . '.raml';

                    if ( file_exists( $_filePath ) )
                    {
                        //@todo check for RAML and convert
                    }
                }
            }

            if ( empty( $_content ) )
            {
                Log::debug( '    ! Resource "' . $_apiName . '" not available. No Swagger definition.' );
                continue;
            }

            // replace service type placeholder with api name for this service instance
            $_tag = '/' . $_apiName;
            $_content = str_replace( '/{api_name}', $_tag, $_content );

            // cache it to a file for later access
            $_filePath = $_cachePath . $_tag . '.json';

            if ( false === file_put_contents( $_filePath, $_content ) )
            {
                Log::error( '  * File system error creating swagger cache file: ' . $_filePath );
                continue;
            }

            // build main services list
            $_serviceApis[] = array(
                'path'        => $_tag,
                'description' => Option::get( $_service, 'description', 'Service' )
            );

            if ( !isset( static::$_eventMap[ $_apiName ] ) || !is_array( static::$_eventMap[ $_apiName ] ) || empty( static::$_eventMap[ $_apiName ] ) )
            {
                static::$_eventMap[ $_apiName ] = array();
            }

            //	Parse the events while we get the chance...
            $_serviceEvents = static::_parseSwaggerEvents( $_apiName, json_decode( $_content, true ) );
            $_cube = Option::get( $_serviceEvents, '.cubed', array(), true );

            //	Parse the events while we get the chance...
            static::$_eventMap[ $_apiName ] = array_merge(
                Option::clean( static::$_eventMap[ $_apiName ] ),
                $_serviceEvents
            );

            if ( !empty( $_cube ) )
            {
                static::$_eventCube[] = $_cube;
                unset( $_cube );
            }

            unset( $_content, $_filePath, $_service, $_serviceEvents );
        }

        // cache main api listing file
        $_main = $_scanPath . static::SWAGGER_BASE_API_FILE;

        /** @noinspection PhpIncludeInspection */
        $_resourceListing = require( $_main );

        $_out = array_merge( $_resourceListing, array('apis' => $_serviceApis) );
        $_filePath = $_cachePath . static::SWAGGER_CACHE_FILE;

        if ( false === file_put_contents( $_filePath, json_encode( $_out, JSON_UNESCAPED_SLASHES ) ) )
        {
            Log::error( '  * File system error creating swagger cache file: ' . $_filePath );
        }

        //	Write event cache file
        ksort( static::$_eventMap );

        if ( false ===
             file_put_contents( $_cachePath . static::SWAGGER_EVENT_CACHE_FILE, json_encode( static::$_eventMap, JSON_UNESCAPED_SLASHES ) )
        )
        {
            Log::error( '  * File system error writing events cache file: ' . $_cachePath . static::SWAGGER_EVENT_CACHE_FILE );
        }

        //	Write event cube file
        ksort( static::$_eventCube );

        if ( false ===
             file_put_contents( $_cachePath . static::SWAGGER_EVENT_CUBE_FILE, json_encode( static::$_eventCube, JSON_UNESCAPED_SLASHES ) )
        )
        {
            Log::error( '  * File system error writing events cache file: ' . $_cachePath . static::SWAGGER_EVENT_CUBE_FILE );
        }

        //	Create example file
        if ( !file_exists( $_customPath . static::SWAGGER_CUSTOM_EXAMPLE_FILE ) && file_exists( $_templatePath . static::SWAGGER_CUSTOM_EXAMPLE_FILE ) )
        {
            file_put_contents( $_customPath . static::SWAGGER_CUSTOM_EXAMPLE_FILE, file_get_contents( $_templatePath . static::SWAGGER_CUSTOM_EXAMPLE_FILE ) );
        }

        Log::info( 'Swagger cache build process complete' );

        Pii::trigger( SwaggerEvents::CACHE_REBUILT );

        return $_out;
    }

    /**
     * @param string $apiName
     * @param array  $data
     *
     * @return array
     */
    protected static function _parseSwaggerEvents( $apiName, $data )
    {
        $_eventCube = $_eventMap = array();

        foreach ( Option::get( $data, 'apis', array() ) as $_api )
        {
            $_scripts = $_events = array();

            $_path = str_replace(
                array('{api_name}', '/'),
                array($apiName, '.'),
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
                            array('{api_name}', '{action}', '{request.method}'),
                            array($apiName, $_method, $_method),
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

        $_scriptPattern = strtolower( $apiName ) . '.' . strtolower( $method ) . '.*.js';
        $_scripts = FileSystem::glob( $_scriptPath . '/' . $_scriptPattern );

        if ( null !== $eventName && array() !== ( $_namedScripts = FileSystem::glob( $_scriptPath . '/' . $eventName . '.js' ) ) )
        {
            $_scripts = array_merge( $_scripts, $_namedScripts );
        }

        if ( empty( $_scripts ) )
        {
            if ( !empty( $eventName ) )
            {
                $_scripts = FileSystem::glob( $_scriptPath . '/' . $eventName . '.js' );
            }

            if ( empty( $_scripts ) )
            {
                return array();
            }
        }

        $_response = array();
        $_eventPattern = '/^' . str_replace( array('.*.js', '.'), array(null, '\\.'), $_scriptPattern ) . '\\.(\w)\\.js$/i';

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

        $_resourceMacros = array('{table_name}', '{container}', '{folder_path}', '{file_path}');

        if ( null === ( $_resources = Option::get( $_map, $_resource ) ) )
        {
            foreach ( $_resourceMacros as $_macro )
            {
                if ( null !== ( $_resources = Option::get( $_map, $_macro ) ) )
                {
                    break;
                }
            }

            //  Still fruitless?
            if ( null === $_resources )
            {
                if ( !method_exists( $service, 'getServiceName' ) || null === ( $_resources = Option::get( $_map, $service->getServiceName() ) ) )
                {
                    if ( null === ( $_resources = Option::get( $_map, 'system' ) ) )
                    {
                        return null;
                    }
                }
            }
        }

        $_path = str_replace( 'rest', null, trim( !Pii::cli() ? Pii::app()->getRequestObject()->getPathInfo() : $service->getResourcePath(), '/' ) );

        if ( empty( $_path ) )
        {
            return null;
        }

        $_pattern = '@^' . preg_replace( '/\\\:[a-zA-Z0-9\_\-]+/', '([a-zA-Z0-9\-\_]+)', preg_quote( $_path ) ) . '$@D';
        $_matches = preg_grep( $_pattern, array_keys( $_resources ) );

        if ( empty( $_matches ) )
        {
            //	See if there is an event with /system at the front...
            $_pattern = '@^' . preg_replace( '/\\\:[a-zA-Z0-9\_\-]+/', '([a-zA-Z0-9\-\_]+)', preg_quote( str_replace( 'system/', null, $_path ) ) ) . '$@D';
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

        $_cachePath = Platform::getSwaggerPath( static::SWAGGER_CACHE_DIR );
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
            static::_buildSwagger();
        }

        return static::$_eventMap;
    }

    /**
     * Main retrieve point for a list of swagger-able services
     * This builds the full swagger cache if it does not exist
     *
     * @return string The JSON contents of the swagger api listing.
     * @throws InternalServerErrorException
     */
    public static function getSwagger()
    {
        $_swaggerPath = Platform::getSwaggerPath( static::SWAGGER_CACHE_DIR );

        if ( !is_dir( $_swaggerPath ) )
        {
            if ( false === @mkdir( $_swaggerPath, 0777, true ) )
            {
                Log::error( 'File system error while creating swagger cache path: ' . $_swaggerPath );
            }
        }

        $_filePath = $_swaggerPath . static::SWAGGER_CACHE_FILE;

        if ( !file_exists( $_filePath ) )
        {
            static::_buildSwagger();

            if ( !file_exists( $_filePath ) )
            {
                throw new InternalServerErrorException( "Failed to create swagger cache." );
            }
        }

        if ( false === ( $_content = file_get_contents( $_filePath ) ) )
        {
            throw new InternalServerErrorException( "Failed to retrieve swagger cache." );
        }

        return $_content;
    }

    /**
     * Main retrieve point for each service
     *
     * @param string $service Which service (api_name) to retrieve.
     *
     * @throws InternalServerErrorException
     * @return string The JSON contents of the swagger service.
     */
    public static function getSwaggerForService( $service )
    {
        $_swaggerPath = Platform::getSwaggerPath( static::SWAGGER_CACHE_DIR );
        $_filePath = $_swaggerPath . '/' . $service . '.json';

        if ( !file_exists( $_filePath ) )
        {
            static::_buildSwagger();

            if ( !file_exists( $_filePath ) )
            {
                throw new InternalServerErrorException( 'File system error creating Swagger cache file for "' . $service . '"' );
            }
        }

        if ( false === ( $_content = file_get_contents( $_filePath ) ) )
        {
            throw new InternalServerErrorException( 'File system error reading Swagger cache: ' . $_filePath );
        }

        return $_content;
    }

    /**
     * Clears the cached files produced by the swagger annotations
     */
    public static function clearCache()
    {
        $_swaggerPath = Platform::getSwaggerPath( static::SWAGGER_CACHE_DIR );

        if ( file_exists( $_swaggerPath ) )
        {
            $files = array_diff( scandir( $_swaggerPath ), array('.', '..') );
            foreach ( $files as $file )
            {
                @unlink( $_swaggerPath . '/' . $file );
            }
        }

        //  Trigger a swagger.cache_cleared event
        return Pii::app()->trigger( SwaggerEvents::CACHE_CLEARED );
    }

    /**
     * Returns an array of common responses for merging into Swagger files.
     *
     * @param array $codes Array of response codes to return only. If empty, all are returned.
     *
     * @return array
     */
    public static function getCommonResponses( array $codes = array() )
    {
        static $_commonResponses = array(
            array(
                'code'    => 400,
                'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
            ),
            array(
                'code'    => 401,
                'message' => 'Unauthorized Access - No currently valid session available.',
            ),
            array(
                'code'    => 404,
                'message' => 'Not Found - Resource not found',
            ),
            array(
                'code'    => 500,
                'message' => 'System Error - Specific reason is included in the error message',
            ),
        );

        $_response = $_commonResponses;

        if ( !empty( $codes ) )
        {
            foreach ( $codes as $_code )
            {
                foreach ( $_commonResponses as $_commonResponse )
                {
                    if ( !isset( $_commonResponse['code'] ) || $_code != $_commonResponse['code'] )
                    {
                        unset( $_response[ $_commonResponse['code'] ] );
                    }
                }
            }
        }

        return $_response;
    }

}
