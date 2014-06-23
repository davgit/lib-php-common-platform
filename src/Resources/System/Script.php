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
namespace DreamFactory\Platform\Resources\System;

use DreamFactory\Platform\Enums\DataFormats;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Resources\BaseSystemRestResource;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Scripting\Api;
use DreamFactory\Platform\Scripting\ScriptEngine;
use DreamFactory\Platform\Services\SwaggerManager;
use DreamFactory\Platform\Utility\Fabric;
use DreamFactory\Platform\Utility\Platform;
use Kisma\Core\Enums\GlobFlags;
use Kisma\Core\Interfaces\HttpResponse;
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Script.php
 * Script service
 */
class Script extends BaseSystemRestResource
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /** @type string */
    const DEFAULT_SCRIPT_PATH = '/scripts';
    /** @type string */
    const DEFAULT_SCRIPT_PATTERN = '/*.js';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string The path to script storage area
     */
    protected $_scriptPath = null;
    /**
     * @var int The maximum time (in ms) to allow scripts to run
     */
    protected static $_scriptTimeout = 60000;
    /**
     * @var ScriptEngine
     */
    protected static $_scriptEngine = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param \DreamFactory\Platform\Interfaces\RestResourceLike|\DreamFactory\Platform\Interfaces\RestServiceLike $consumer
     * @param array                                                                                                $resources
     *
     * @throws \Kisma\Core\Exceptions\FileSystemException
     * @throws \InvalidArgumentException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @internal param array $settings
     *
     */
    public function __construct( $consumer, $resources = array() )
    {
        //	Pull out our settings before calling daddy
        $_config = array(
            'name'          => 'Script',
            'description'   => 'A sandboxed script manager endpoint',
            'api_name'      => 'script',
            'service_name'  => 'system',
            'type'          => 'System',
            'type_id'       => PlatformServiceTypes::SYSTEM_SERVICE,
            'is_active'     => true,
            'native_format' => DataFormats::NATIVE,
        );

        parent::__construct( $consumer, $_config, $resources );

        $this->checkAvailability();
    }

    /**
     * Checks to make sure all our ducks are in a row...
     *
     * @return bool
     * @throws \DreamFactory\Platform\Exceptions\ForbiddenException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     */
    public function checkAvailability()
    {
        $_message = false;

        if ( Fabric::fabricHosted() )
        {
            throw new ForbiddenException( 'This resource is not available on a free-hosted DSP.' );
        }

        //  User scripts are stored here
        $this->_scriptPath = Platform::getPrivatePath( static::DEFAULT_SCRIPT_PATH );

        if ( empty( $this->_scriptPath ) )
        {
            $_message = 'Empty script path';
        }
        else
        {
            if ( !is_dir( $this->_scriptPath ) )
            {
                if ( false === @mkdir( $this->_scriptPath, 0777, true ) )
                {
                    $_message = 'File system error creating scripts path: ' . $this->_scriptPath;
                }
            }
            else if ( !is_writable( $this->_scriptPath ) )
            {
                $_message = 'Scripts path not writeable: ' . $this->_scriptPath;
            }
        }

        if ( $_message )
        {
            Log::error( $_message );

            throw new RestException(
                HttpResponse::ServiceUnavailable,
                'This service is not available. Storage path, area, and/or required libraries are missing.'
            );
        }

        return true;
    }

    /**
     * LIST all scripts
     *
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _listResources()
    {
        $_scripts = FileSystem::glob( $this->_scriptPath . static::DEFAULT_SCRIPT_PATTERN, GlobFlags::GLOB_NODOTS );
        $_response = array();

        if ( !empty( $_scripts ) )
        {
            foreach ( $_scripts as $_script )
            {
                $_resource = array(
                    'event_name' => str_ireplace( '.js', null, $_script ),
                    'script'     => $_script,
                );

                $_response[] = $_resource;
                unset( $_resource );
            }
        }

        return array( 'resource' => $_response );
    }

    /**
     * GET a script
     *
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleGet()
    {
        if ( empty( $this->_resourceId ) )
        {
            return $this->_listResources();
        }

        $_path = $this->_getScriptPath();

        if ( !file_exists( $_path ) )
        {
            throw new NotFoundException( '"' . $this->_resourceId . '" was not found.' );
        }

        $_body = @file_get_contents( $_path );

        return array( 'script_id' => $this->_resourceId, 'script_body' => $_body );
    }

    /**
     * WRITE a script
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \LogicException
     * @throws \InvalidArgumentException
     * @throws \Exception
     * @return array|bool
     */
    protected function _handlePut()
    {
        if ( empty( $this->_resourceId ) )
        {
            throw new BadRequestException( 'No resource id specified.' );
        }

        $_path = $this->_scriptPath . '/' . trim( $this->_resourceId, '/ ' ) . '.js';

        $_scriptBody = Option::get( $this->_requestPayload, 'record' );

        if ( is_array( $_scriptBody ) )
        {
            $_scriptBody = current( $_scriptBody );
        }

        if ( empty( $_scriptBody ) )
        {
            throw new BadRequestException( 'You must supply a "script_body".' );
        }

        if ( false === $_bytes = @file_put_contents( $_path, $_scriptBody ) )
        {
            throw new InternalServerErrorException( 'Error writing file to storage area.' );
        }

        //  Clear the swagger cache...
        SwaggerManager::clearCache();

        return array( 'script_id' => $this->_resourceId, 'script_body' => $_scriptBody, 'bytes_written' => $_bytes );
    }

    /**
     * DELETE an existing script
     * This is a permanent/destructive action.
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \Exception
     * @return bool|void
     */
    protected function _handleDelete()
    {
        if ( empty( $this->_resourceId ) )
        {
            throw new BadRequestException( 'No script ID specified.' );
        }

        $_path = $this->_scriptPath . '/' . trim( $this->_resourceId, '/ ' ) . '.js';

        if ( !file_exists( $_path ) )
        {
            throw new NotFoundException();
        }

        $_body = @file_get_contents( $_path );

        if ( false === @unlink( $_path ) )
        {
            throw new InternalServerErrorException( 'Unable to delete script ID "' . $this->_resourceId . '"' );
        }

        //  Clear the swagger cache...
        SwaggerManager::clearCache();

        return array( 'script_id' => $this->_resourceId, 'script_body' => $_body );
    }

    /**
     * RUN a script
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @return array
     */
    protected function _handlePost()
    {
        if ( empty( $this->_resourceId ) )
        {
            throw new BadRequestException();
        }

        if ( !extension_loaded( 'v8js' ) )
        {
            throw new RestException(
                HttpResponse::ServiceUnavailable,
                'This DSP cannot run server-side javascript scripts. The "v8js" is not available.'
            );
        }

        $_api = array(
            'api'     => Api::getScriptingObject(),
            'config'  => Config::getCurrentConfig(),
            'session' => Session::generateSessionDataFromUser( Session::getCurrentUserId() )
        );

        return ScriptEngine::runScript(
            $this->_getScriptPath(),
            $this->_resource . '.' . $this->_resourceId,
            $this->_requestPayload,
            $_api
        );
    }

    /**
     * @param string $eventName
     *
     * @return bool
     */
    public static function existsForEvent( $eventName )
    {
        $_scripts = FileSystem::glob(
            Platform::getPrivatePath( static::DEFAULT_SCRIPT_PATH ) . static::DEFAULT_SCRIPT_PATTERN,
            GlobFlags::GLOB_NODOTS
        );

        foreach ( $_scripts as $_script )
        {
            if ( $eventName == str_replace( '.js', null, $_script ) )
            {
                return $_script;
            }
        }

        return false;
    }

    /**
     * @param array $payload
     *
     * @return \stdClass
     */
    protected static function _preparePayload( $payload = array() )
    {
        //  Quick and dirty object conversion
        return json_decode( json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
    }

    /**
     * Constructs the full path to a server-side script
     *
     * @param string $scriptName The script name or null if $this->_resourceId is to be used
     *
     * @return string
     */
    protected function _getScriptPath( $scriptName = null )
    {
        return $this->_scriptPath . '/' . trim( $scriptName ? : $this->_resourceId, '/ ' ) . '.js';
    }

    /**
     * @param int $scriptTimeout
     */
    public static function setScriptTimeout( $scriptTimeout )
    {
        script::$_scriptTimeout = $scriptTimeout;
    }

    /**
     * @return int
     */
    public static function getScriptTimeout()
    {
        return script::$_scriptTimeout;
    }

    /**
     * @param string $scriptPath
     *
     * @return Script
     */
    public function setScriptPath( $scriptPath )
    {
        $this->_scriptPath = $scriptPath;

        return $this;
    }

    /**
     * @return string
     */
    public function getScriptPath()
    {
        return $this->_scriptPath;
    }

}
