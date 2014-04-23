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
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Utility\Schwag;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Enums\HttpMethod;
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
    /**
     * @const string The default extension for swagger config files
     */
    const SWAGGER_DEFAULT_EXTENSION_PATTERN = '.swagger.php';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array The core DSP services that are built-in
     */
    protected static $_builtInServices = array(
        array( 'api_name' => 'user', 'type_id' => 0, 'description' => 'User Login' ),
        array( 'api_name' => 'system', 'type_id' => 0, 'description' => 'System Configuration' )
    );
    /**
     * @var int The default encoding options
     */
    protected static $_jsonEncodeOptions = JSON_UNESCAPED_SLASHES;

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
     * @param $apiName
     * @param $path
     * @param $customPath
     *
     * @return bool|string
     */
    protected static function _loadSwaggerFile( $apiName, $path, $customPath, $baseSwagger )
    {
        $_paths = array(
            $path,
            $customPath . '/' . $apiName . '.json',
            $customPath . '/' . $apiName . '.raml',
        );

        foreach ( $_paths as $_path )
        {
            if ( file_exists( $_path ) && is_readable( $_path ) )
            {
                if ( 'php' == strtolower( pathinfo( $_path, PATHINFO_EXTENSION ) ) )
                {
                    $_data = require( $_path );
                    $_eventCube = $_eventMap = array();

                    //@todo do we NOT want to return an empty array if one is in the file?
                    if ( is_array( $_data ) && !empty( $_data ) )
                    {
                        //  Fix up the event arrays into to a string
                        foreach ( Option::get( $_data, 'apis', array() ) as $_ixApi => $_api )
                        {
                            foreach ( Option::get( $_api, 'operations', array() ) as $_ixOps => $_op )
                            {
                                if ( null !== ( $_events = Option::get( $_op, 'event_name' ) ) )
                                {
                                    //  Build up the event map
                                    if ( !isset( $_eventMap[ $apiName ] ) || !is_array( $_eventMap[ $apiName ] ) || empty( $_eventMap[ $apiName ] ) )
                                    {
                                        $_eventMap[ $apiName ] = array();
                                    }

                                    //	Parse the events while we get the chance...
                                    $_serviceEvents = Schwag::parseSwaggerEvents( $apiName, $_data );
                                    $_cube = Option::get( $_serviceEvents, '.cubed', array(), true );

                                    //	Parse the events while we get the chance...
                                    $_eventMap[ $apiName ] = array_merge(
                                        Option::clean( $_eventMap[ $apiName ] ),
                                        $_serviceEvents
                                    );

                                    if ( !empty( $_cube ) )
                                    {
                                        $_eventCube[] = $_cube;
                                        unset( $_cube );
                                    }

                                    if ( is_array( $_events ) )
                                    {
                                        $_data['apis'][ $_ixApi ]['operations'][ $_ixOps ]['event_name'] = implode( ',', $_events );
                                    }
                                }
                            }
                        }

                        //  Save events
                        Schwag::saveEventCache( Platform::getSwaggerPath( static::SWAGGER_CACHE_DIR ), $_eventMap, $_eventCube );

                        $_json = json_encode( array_merge( $baseSwagger, $_data ), static::$_jsonEncodeOptions );

                        if ( false !== $_json && JSON_ERROR_NONE == json_last_error() )
                        {
                            return $_json;
                        }
                    }
                }

                return file_get_contents( $_path );
            }
        }

        return false;
    }

    protected static function _buildSwaggerServices()
    {
        //  Our base
        $_baseSwagger = array(
            'swaggerVersion' => static::SWAGGER_VERSION,
            'apiVersion'     => API_VERSION,
            'basePath'       => Pii::request()->getHostInfo() . '/rest',
        );

        //	Create cache & custom directories
        $_cachePath = Platform::getSwaggerPath( static::SWAGGER_CACHE_DIR );
        $_customPath = Platform::getSwaggerPath( static::SWAGGER_CUSTOM_DIR );

        $_platformServices = static::_getRegisteredServices();

        //	Spin through services and pull the configs
        $_services = array();

        foreach ( $_platformServices as $_service )
        {
            $_apiName = Option::get( $_service, 'api_name' );
            $_typeId = (int)Option::get( $_service, 'type_id', PlatformServiceTypes::SYSTEM_SERVICE );
            $_fileName = PlatformServiceTypes::getFileName( $_typeId, $_apiName );
            $_filePath = __DIR__ . '/' . $_fileName . static::SWAGGER_DEFAULT_EXTENSION_PATTERN;

            $_content = static::_loadSwaggerFile( $_apiName, $_filePath, $_customPath, $_baseSwagger );

            if ( empty( $_content ) )
            {
                Log::debug( '    ! Resource "' . $_apiName . '" not available. No Swagger definition.' );
                continue;
            }

            // replace service type placeholder with api name for this service instance
            $_content = str_replace( '/{api_name}', '/' . $_apiName, $_content );

            // cache it to a file for later access
            $_filePath = $_cachePath . '/' . $_apiName . '.json';

            if ( false === file_put_contents( $_filePath, $_content ) )
            {
                Log::error( '  * File system error creating swagger cache file: ' . $_filePath );
                continue;
            }

            // build main services list
            $_services[] = array(
                'path'        => '/' . $_apiName,
                'description' => Option::get( $_service, 'description', 'Service' )
            );

            unset( $_content, $_filePath, $_service );
        }

        return $_services;
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

        //  Generate swagger output from file annotations
        $_services = static::_buildSwaggerServices();

        //  Cache main API listing file
        $_main = __DIR__ . static::SWAGGER_BASE_API_FILE;

        /** @noinspection PhpIncludeInspection */
        $_resourceListing = require( $_main );
        $_out = array_merge( $_resourceListing, array( 'apis' => $_services ) );

        $_filePath = $_cachePath . static::SWAGGER_CACHE_FILE;

        if ( false === file_put_contents( $_filePath, json_encode( $_out, JSON_UNESCAPED_SLASHES + JSON_PRETTY_PRINT ) ) )
        {
            Log::error( '  * File system error creating swagger cache file: ' . $_filePath );
        }

        //	Create example file
        if ( !file_exists( $_customPath . static::SWAGGER_CUSTOM_EXAMPLE_FILE ) && file_exists( $_templatePath . static::SWAGGER_CUSTOM_EXAMPLE_FILE ) )
        {
            file_put_contents( $_customPath . static::SWAGGER_CUSTOM_EXAMPLE_FILE, file_get_contents( $_templatePath . static::SWAGGER_CUSTOM_EXAMPLE_FILE ) );
        }

        //  Write out the event cache last...
        Schwag::saveEventCache( $_cachePath );

        Log::info( 'Swagger cache build process complete' );

        Pii::app()->trigger( SwaggerEvents::CACHE_REBUILT );

        return $_out;
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
            $files = array_diff( scandir( $_swaggerPath ), array( '.', '..' ) );
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
     * @param array $onlyTheseCodes Array of response codes to return. If empty, all are returned.
     *
     * @return array
     */
    public static function getCommonResponses( array $onlyTheseCodes = array() )
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

        if ( !empty( $onlyTheseCodes ) )
        {
            foreach ( $onlyTheseCodes as $_code )
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

    /**
     * Requests a rebuild of the Swagger cache.
     *
     * @todo Currently, only ($immediately == true) is supported. Eventually, it will be handed off to the process server.
     *
     * @param bool $immediately Rebuild now or queue for later?
     *
     * @return array
     */
    public static function requestRebuild( $immediately = true )
    {
        return static::_buildSwagger();
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
