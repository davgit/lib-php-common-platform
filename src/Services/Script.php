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

use DreamFactory\Platform\Enums\DataFormats;
use DreamFactory\Platform\Enums\PlatformServiceTypes;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\NotFoundException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Platform\Utility\RestData;
use Kisma\Core\Enums\GlobFlags;
use Kisma\Core\Interfaces\HttpResponse;
use Kisma\Core\Utility\FileSystem;
use Kisma\Core\Utility\Log;
use Kisma\Core\Utility\Option;

/**
 * Script.php
 * Script service
 */
class Script extends BasePlatformRestService
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /** @type string */
    const DEFAULT_SCRIPT_PATH = '/config/scripts';
    /** @type string */
    const DEFAULT_SCRIPT_PATTERN = '/*.js';

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var string The path to script storage area
     */
    protected $_scriptPath = null;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     */
    public function __construct( $settings = array() )
    {
        //	Pull out our settings before calling daddy
        $_settings = array_merge(
            array(
                'name'          => 'Script',
                'description'   => 'A sandboxed script management service.',
                'api_name'      => 'script',
                'type_id'       => PlatformServiceTypes::SYSTEM_SERVICE,
                'is_active'     => true,
                'native_format' => DataFormats::NATIVE,
            ),
            $settings
        );

        parent::__construct( $_settings );

        $this->_scriptPath = Platform::getPrivatePath( static::DEFAULT_SCRIPT_PATH );

        if ( empty( $this->_scriptPath ) || !extension_loaded( 'v8js' ) )
        {
            throw new RestException(
                HttpResponse::ServiceUnavailable, 'This service is not available. Storage path and/or required libraries not available.'
            );
        }
    }

    /**
     * LIST all scripts
     *
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _listResources()
    {
        return FileSystem::glob( $this->_scriptPath . static::DEFAULT_SCRIPT_PATTERN, GlobFlags::GLOB_NODOTS );
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
        if ( empty( $this->_resource ) )
        {
            return $this->_listResources();
        }

        $_path = $this->_scriptPath . '/' . trim( $this->_resource, '/ ' ) . '.js';

        if ( !file_exists( $_path ) )
        {
            throw new NotFoundException( 'A script with ID "' . $this->_resource . '" was not found.' );
        }

        $_body = @file_get_contents( $_path );

        return array( 'script_id' => $this->_resource, 'script_body' => $_body );
    }

    /**
     * WRITE a script
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array|bool
     */
    protected function _handlePut()
    {
        if ( empty( $this->_resource ) )
        {
            return $this->_listResources();
        }

        $_path = $this->_scriptPath . '/' . trim( $this->_resource, '/ ' ) . '.js';
        $_scriptBody = RestData::getPostedData();

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

        return array( 'script_id' => $this->_resource, 'script_body' => $_scriptBody, 'bytes_written' => $_bytes );
    }

    /**
     * RUN a script
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array
     */
    protected function _handlePost()
    {
        if ( empty( $this->_resource ) )
        {
            throw new BadRequestException();
        }

        return static::runScript( $this->_getScriptPath() );
    }

    /**
     * @param string $scriptName
     * @param string $scriptId
     * @param array  $data Bi-directional data to/from function
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @return array
     */
    public static function runScript( $scriptName, $scriptId = null, array &$data = array() )
    {
        $scriptId = $scriptId ? : $scriptName;

        if ( !is_file( $scriptName ) || !is_readable( $scriptName ) )
        {
            throw new InternalServerErrorException( 'The script ID "' . $scriptId . '" is not valid or unreadable.' );
        }

        if ( false === ( $_script = @file_get_contents( $scriptName ) ) )
        {
            throw new InternalServerErrorException( 'The script ID "' . $scriptId . '" cannot be retrieved at this time.' );
        }

        try
        {
            $_runner = new \V8Js();

            /** @noinspection PhpUndefinedFieldInspection */
            $_runner->event_data = $data;

            //  Don't show output
            ob_start();

            /** @noinspection PhpUndefinedMethodInspection */
            $_lastVariable = $_runner->executeString( $_script, $scriptId );

            /** @noinspection PhpUndefinedFieldInspection */
            $data = $_runner->event_data;

            $_result = ob_get_clean();

            return array( 'script_output' => $_result, 'script_last_variable' => $_lastVariable );
        }
        catch ( \V8JsException $_ex )
        {
            ob_end_clean();
            Log::error( 'Exception executing javascript: ' . $_ex->getMessage() );
            throw new InternalServerErrorException( $_ex->getMessage() );
        }

    }

    /**
     * Constructs the full path to a server-side script
     *
     * @param string $scriptName The script name or null if $this->_resource is to be used
     *
     * @return string
     */
    protected function _getScriptPath( $scriptName = null )
    {
        return $this->_scriptPath . '/' . trim( $scriptName ? : $this->_resource, '/ ' ) . '.js';

    }
}
