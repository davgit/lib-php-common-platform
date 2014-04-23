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

use DreamFactory\Platform\Enums\DbFilterOperators;
use DreamFactory\Platform\Exceptions\BadRequestException;
use DreamFactory\Platform\Exceptions\ForbiddenException;
use DreamFactory\Platform\Exceptions\InternalServerErrorException;
use DreamFactory\Platform\Exceptions\RestException;
use DreamFactory\Platform\Resources\User\Session;
use DreamFactory\Platform\Utility\RestData;
use DreamFactory\Platform\Utility\Utilities;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\FilterInput;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;

/**
 * BaseDbSvc
 *
 * A base service class to handle generic db services accessed through the REST API.
 */
abstract class BaseDbSvc extends BasePlatformRestService
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * Default maximum records returned on filter request
     */
    const DB_MAX_RECORDS_RETURNED = 1000;

    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var int|string
     */
    protected $_resourceId = null;
    /**
     * @var array
     */
    protected $_requestData = null;
    /**
     * @var boolean
     */
    protected $_useBlendFormat = true;
    /**
     * @var string
     */
    protected $_transactionTable = null;
    /**
     * @var array
     */
    protected $_batchIds = array();
    /**
     * @var array
     */
    protected $_batchRecords = array();
    /**
     * @var array
     */
    protected $_rollbackRecords = array();

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @param array $settings
     */
    public function __construct( $settings = array() )
    {
        if ( null === Option::get( $settings, 'verb_aliases' ) )
        {
            //	Default verb aliases
            $settings['verb_aliases'] = array(
                static::PATCH => static::MERGE,
            );
        }

        parent::__construct( $settings );
    }

    /**
     *
     */
    protected function _detectResourceMembers( $resourcePath = null )
    {
        parent::_detectResourceMembers( $resourcePath );

        $this->_resourceId = ( isset( $this->_resourceArray, $this->_resourceArray[1] ) ) ? $this->_resourceArray[1] : '';
        $this->_requestData = RestData::getPostedData( true, true );
    }

    /**
     * @param null|array $post_data
     *
     * @return array
     */
    protected function _gatherExtrasFromRequest( $post_data = null )
    {
        // most DBs support the following filter extras from url or posted data
        $_extras = array();

        // Most requests contain 'returned fields' parameter
        $_fields = static::getFromPostedData( $post_data, 'fields', FilterInput::request( 'fields' ) );
        $_fieldsDefault = ( static::GET == $this->_action ) ? '*' : null;
        $_extras['fields'] = ( empty( $_fields ) ) ? $_fieldsDefault : $_fields;

        // means to override the default identifier fields for a table
        // or supply one when there is no default designated
        $_extras['id_field'] = static::getFromPostedData( $post_data, 'id_field', FilterInput::request( 'id_field' ) );

        // means to override the default identifier type for a table
        // or supply one when there is no default designated
        $_extras['id_type'] = static::getFromPostedData( $post_data, 'id_type', FilterInput::request( 'id_type' ) );

        if ( null != $_ssFilters = Session::getServiceFilters( $this->_apiName, $this->_resource ) )
        {
            $_extras['ss_filters'] = $_ssFilters;
        }

        // rollback all db changes in a transaction, if applicable
        $_extras['rollback'] = FilterInput::request(
            'rollback',
            Option::getBool( $post_data, 'rollback' ),
            FILTER_VALIDATE_BOOLEAN
        );

        // continue batch processing if an error occurs, if applicable
        $_extras['continue'] = FilterInput::request(
            'continue',
            Option::getBool( $post_data, 'continue' ),
            FILTER_VALIDATE_BOOLEAN
        );

        // look for limit, accept top as well as limit
        $_limit = FilterInput::request(
            'limit',
            FilterInput::request(
                'top',
                Option::get( $post_data, 'limit', Option::get( $post_data, 'top' ) ),
                FILTER_VALIDATE_INT
            ),
            FILTER_VALIDATE_INT
        );
        $_extras['limit'] = $_limit;

        // accept skip as well as offset
        $_offset = FilterInput::request(
            'offset',
            FilterInput::request(
                'skip',
                Option::get( $post_data, 'offset', Option::get( $post_data, 'skip' ) ),
                FILTER_VALIDATE_INT
            ),
            FILTER_VALIDATE_INT
        );
        $_extras['offset'] = $_offset;

        // accept sort as well as order
        $_order = FilterInput::request(
            'order',
            FilterInput::request(
                'sort',
                Option::get( $post_data, 'order', Option::get( $post_data, 'sort' ) )
            )
        );
        $_extras['order'] = $_order;

        // include count in metadata tag
        $_includeCount = FilterInput::request(
            'include_count',
            Option::getBool( $post_data, 'include_count', false ),
            FILTER_VALIDATE_BOOLEAN
        );
        $_extras['include_count'] = $_includeCount;

        // trigger action events...should be called somewhere more generic, but this is called by every action here...
        if ( static::GET != $this->_action )
        {
            $this->_triggerActionEvent( $post_data, null, null, array('{table_name}' => $this->_resource) );
        }

        return $_extras;
    }

    /**
     * @param string $table
     * @param string $action
     *
     * @throws BadRequestException
     */
    protected function validateTableAccess( &$table, $action = null )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table name can not be empty.' );
        }

        $_action = ( empty( $action ) ) ? $this->getRequestedAction() : $action;

        // finally check that the current user has privileges to access this table
        $this->checkPermission( $_action, $table );
    }

    /**
     * {@InheritDoc}
     */
    protected function _preProcess()
    {
        parent::_preProcess();

        //	Do validation here
        if ( !empty( $this->_resource ) )
        {
            $this->validateTableAccess( $this->_resource );
        }
        else
        {
            // listing and getting table properties are checked by table
            if ( static::GET != $_action = $this->getRequestedAction() )
            {
                $this->checkPermission( $_action );
            }
        }
    }

    /**
     * @return array|bool
     */
    protected function _handleResource()
    {
        if ( empty( $this->_resource ) )
        {
            return $this->_handleAdmin();
        }

        return parent::_handleResource();
    }

    /**
     * @return array|bool
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleAdmin()
    {
        $_result = false;

        switch ( $this->_action )
        {
            case static::GET:
                $_ids = static::getFromPostedData( $this->_requestData, 'names', FilterInput::request( 'names' ) );
                if ( empty( $_ids ) )
                {
                    return $this->_listResources();
                }

                $_result = $this->getTables( $_ids );
                $_result = array('table' => $_result);
                break;

            case static::POST:
                $_tables = static::getFromPostedData( $this->_requestData, 'table' );

                if ( empty( $_tables ) )
                {
                    $_result = $this->createTable( $this->_requestData );
                }
                else
                {
                    $_result = $this->createTables( $_tables );
                    $_result = array('table' => $_result);
                }
                break;

            case static::PUT:
            case static::PATCH:
            case static::MERGE:
                $_tables = static::getFromPostedData( $this->_requestData, 'table' );

                if ( empty( $_tables ) )
                {
                    $_result = $this->updateTable( $this->_requestData );
                }
                else
                {
                    $_result = $this->updateTables( $_tables );
                    $_result = array('table' => $_result);
                }
                break;

            case static::DELETE:
                $_ids = static::getFromPostedData( $this->_requestData, 'names', FilterInput::request( 'names' ) );
                if ( !empty( $_ids ) )
                {
                    $_result = $this->deleteTables( $_ids );
                    $_result = array('table' => $_result);
                }
                else
                {
                    $_tables = static::getFromPostedData( $this->_requestData, 'table' );

                    if ( empty( $_tables ) )
                    {
                        $_result = $this->deleteTable( $this->_requestData );
                    }
                    else
                    {
                        $_result = $this->deleteTables( $_tables );
                        $_result = array('table' => $_result);
                    }
                }
                break;
        }

        return $_result;
    }

    /**
     * @return array
     */
    protected function _handleGet()
    {
        $_extras = $this->_gatherExtrasFromRequest( $this->_requestData );

        if ( empty( $this->_resourceId ) )
        {
            $_ids = static::getFromPostedData( $this->_requestData, 'ids', FilterInput::request( 'ids' ) );
            if ( !empty( $_ids ) )
            {
                $_result = $this->retrieveRecordsByIds( $this->_resource, $_ids, $_extras );
                $_result = array('record' => $_result);
            }
            else
            {
                $_records = static::getFromPostedData( $this->_requestData, 'record' );

                if ( !empty( $_records ) )
                {
                    // passing records to have them updated with new or more values, id field required
                    $_result = $this->retrieveRecords( $this->_resource, $_records, $_extras );
                    $_result = array('record' => $_result);
                }
                else
                {
                    $_filter = Option::get( $this->_requestData, 'filter', FilterInput::request( 'filter' ) );
                    if ( empty( $_filter ) && !empty( $this->_requestData ) )
                    {
                        // query by record map
                        $_result = $this->retrieveRecord( $this->_resource, $this->_requestData, $_extras );
                    }
                    else
                    {
                        $_params = Option::get( $this->_requestData, 'params', array() );
                        $_result = $this->retrieveRecordsByFilter( $this->_resource, $_filter, $_params, $_extras );
                        if ( isset( $_result['meta'] ) )
                        {
                            $_meta = $_result['meta'];
                            unset( $_result['meta'] );
                            $_result = array('record' => $_result, 'meta' => $_meta);
                        }
                        else
                        {
                            $_result = array('record' => $_result);
                        }
                    }
                }
            }
        }
        else
        {
            // single entity by id
            $_result = $this->retrieveRecordById( $this->_resource, $this->_resourceId, $_extras );
        }

        return $_result;
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handlePost()
    {
        if ( empty( $this->_requestData ) )
        {
            throw new BadRequestException( 'No record(s) in create request.' );
        }

        $_extras = $this->_gatherExtrasFromRequest( $this->_requestData );
        $_records = static::getFromPostedData( $this->_requestData, 'record' );

        if ( empty( $_records ) )
        {
            $_result = $this->createRecord( $this->_resource, $this->_requestData, $_extras );
        }
        else
        {
            $_result = $this->createRecords( $this->_resource, $_records, $_extras );
            $_result = array('record' => $_result);
        }

        return $_result;
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handlePut()
    {
        if ( empty( $this->_requestData ) )
        {
            throw new BadRequestException( 'No record(s) in update request.' );
        }

        $_extras = $this->_gatherExtrasFromRequest( $this->_requestData );
        if ( empty( $this->_resourceId ) )
        {
            $_records = static::getFromPostedData( $this->_requestData, 'record' );

            $_ids = static::getFromPostedData( $this->_requestData, 'ids', FilterInput::request( 'ids' ) );
            if ( !empty( $_ids ) )
            {
                $_result = $this->updateRecordsByIds( $this->_resource, $_records, $_ids, $_extras );
                $_result = array('record' => $_result);
            }
            else
            {
                $_filter = Option::get( $this->_requestData, 'filter', FilterInput::request( 'filter' ) );
                if ( !empty( $_filter ) )
                {
                    $_params = Option::get( $this->_requestData, 'params', array() );
                    $_result = $this->updateRecordsByFilter( $this->_resource, $_records, $_filter, $_params, $_extras );
                    $_result = array('record' => $_result);
                }
                else
                {
                    if ( !empty( $_records ) )
                    {
                        $_result = $this->updateRecords( $this->_resource, $_records, $_extras );
                        $_result = array('record' => $_result);
                    }
                    else
                    {
                        $_result = $this->updateRecord( $this->_resource, $this->_requestData, $_extras );
                    }
                }
            }
        }
        else
        {
            $_result = $this->updateRecordById( $this->_resource, $this->_requestData, $this->_resourceId, $_extras );
        }

        return $_result;
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleMerge()
    {
        if ( empty( $this->_requestData ) )
        {
            throw new BadRequestException( 'No record(s) in merge request.' );
        }

        $_extras = $this->_gatherExtrasFromRequest( $this->_requestData );
        if ( empty( $this->_resourceId ) )
        {
            $_records = static::getFromPostedData( $this->_requestData, 'record' );

            $_ids = static::getFromPostedData( $this->_requestData, 'ids', FilterInput::request( 'ids' ) );
            if ( !empty( $_ids ) )
            {
                $_result = $this->mergeRecordsByIds( $this->_resource, $_records, $_ids, $_extras );
                $_result = array('record' => $_result);
            }
            else
            {
                $_filter = Option::get( $this->_requestData, 'filter', FilterInput::request( 'filter' ) );
                if ( !empty( $_filter ) )
                {
                    $_params = Option::get( $this->_requestData, 'params', array() );
                    $_result = $this->mergeRecordsByFilter( $this->_resource, $_records, $_filter, $_params, $_extras );
                    $_result = array('record' => $_result);
                }
                else
                {
                    if ( !empty( $_records ) )
                    {
                        $_result = $this->mergeRecords( $this->_resource, $_records, $_extras );
                        $_result = array('record' => $_result);
                    }
                    else
                    {
                        $_result = $this->mergeRecord( $this->_resource, $this->_requestData, $_extras );
                    }
                }
            }
        }
        else
        {
            $_result = $this->mergeRecordById( $this->_resource, $this->_requestData, $this->_resourceId, $_extras );
        }

        return $_result;
    }

    /**
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function _handleDelete()
    {
        $_extras = $this->_gatherExtrasFromRequest( $this->_requestData );
        if ( empty( $this->_resourceId ) )
        {
            $_ids = static::getFromPostedData( $this->_requestData, 'ids', FilterInput::request( 'ids' ) );
            if ( !empty( $_ids ) )
            {
                $_result = $this->deleteRecordsByIds( $this->_resource, $_ids, $_extras );
                $_result = array('record' => $_result);
            }
            else
            {
                $_records = static::getFromPostedData( $this->_requestData, 'record' );
                if ( !empty( $_records ) )
                {
                    $_result = $this->deleteRecords( $this->_resource, $_records, $_extras );
                    $_result = array('record' => $_result);
                }
                else
                {
                    $_filter = Option::get( $this->_requestData, 'filter', FilterInput::request( 'filter' ) );
                    if ( !empty( $_filter ) )
                    {
                        $_params = Option::get( $this->_requestData, 'params', array() );
                        $_result = $this->deleteRecordsByFilter( $this->_resource, $_filter, $_params, $_extras );
                        $_result = array('record' => $_result);
                    }
                    else
                    {
                        if ( empty( $this->_requestData ) )
                        {
                            if ( !FilterInput::request( 'force', false, FILTER_VALIDATE_BOOLEAN ) )
                            {
                                throw new BadRequestException( 'No filter or records given for delete request.' );
                            }

                            $_result = $this->truncateTable( $this->_resource, $_extras );
                        }
                        else
                        {
                            $_result = $this->deleteRecord( $this->_resource, $this->_requestData, $_extras );
                        }
                    }
                }
            }
        }
        else
        {
            $_result = $this->deleteRecordById( $this->_resource, $this->_resourceId, $_extras );
        }

        return $_result;
    }

    // Handle administrative options, table add, delete, etc

    /**
     * Get multiple tables and their properties
     *
     * @param string | array $tables Table names comma-delimited string or array
     *
     * @return array
     * @throws \Exception
     */
    public function getTables( $tables = array() )
    {
        $tables = static::validateAsArray( $tables, ',', true, 'The request contains no valid table names or properties.' );

        $_out = array();
        foreach ( $tables as $_table )
        {
            $_name = ( is_array( $_table ) ) ? Option::get( $_table, 'name' ) : $_table;
            $this->validateTableAccess( $_name );

            $_out[] = $this->getTable( $_table );
        }

        return $_out;
    }

    /**
     * Get any properties related to the table
     *
     * @param string | array $table Table name or defining properties
     *
     * @return array
     * @throws \Exception
     */
    abstract public function getTable( $table );

    /**
     * Create one or more tables by array of table properties
     *
     * @param array $tables
     *
     * @return array
     * @throws \Exception
     */
    public function createTables( $tables = array() )
    {
        $tables = static::validateAsArray( $tables, ',', true, 'The request contains no valid table names or properties.' );

        $_out = array();
        foreach ( $tables as $_table )
        {
            $_out[] = $this->createTable( $_table );
        }

        return $_out;
    }

    /**
     * Create a single table by name and additional properties
     *
     * @param array $properties
     *
     * @throws \Exception
     */
    abstract public function createTable( $properties = array() );

    /**
     * Update one or more tables by array of table properties
     *
     * @param array $tables
     *
     * @return array
     * @throws \Exception
     */
    public function updateTables( $tables = array() )
    {
        $tables = static::validateAsArray( $tables, ',', true, 'The request contains no valid table names or properties.' );

        $_out = array();
        foreach ( $tables as $_table )
        {
            $_name = ( is_array( $_table ) ) ? Option::get( $_table, 'name' ) : $_table;
            $this->validateTableAccess( $_name );
            $_out[] = $this->updateTable( $_table );
        }

        return $_out;
    }

    /**
     * Update properties related to the table
     *
     * @param array $properties
     *
     * @return array
     * @throws \Exception
     */
    abstract public function updateTable( $properties = array() );

    /**
     * Delete multiple tables and all of their contents
     *
     * @param array $tables
     * @param bool  $check_empty
     *
     * @return array
     * @throws \Exception
     */
    public function deleteTables( $tables = array(), $check_empty = false )
    {
        $tables = static::validateAsArray( $tables, ',', true, 'The request contains no valid table names or properties.' );

        $_out = array();
        foreach ( $tables as $_table )
        {
            $_name = ( is_array( $_table ) ) ? Option::get( $_table, 'name' ) : $_table;
            $this->validateTableAccess( $_name );
            $_out[] = $this->deleteTable( $_table, $check_empty );
        }

        return $_out;
    }

    /**
     * Delete a table and all of its contents by name
     *
     * @param string $table
     * @param bool   $check_empty
     *
     * @throws \Exception
     * @return array
     */
    abstract public function deleteTable( $table, $check_empty = false );

    /**
     * Delete all table entries but keep the table
     *
     * @param string $table
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function truncateTable( $table, $extras = array() )
    {
        // todo faster way?
        $_records = $this->retrieveRecordsByFilter( $table, null, null, $extras );

        if ( !empty( $_records ) )
        {
            $this->deleteRecords( $table, $_records, $extras );
        }

        return array('success' => true);
    }

    // Handle table record operations

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function createRecords( $table, $records, $extras = array() )
    {
        $records = static::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_isSingle = ( 1 == count( $records ) );
        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_rollback = ( $_isSingle ) ? false : Option::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : Option::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = array();
        $_errors = array();
        try
        {
            foreach ( $records as $_index => $_record )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_record, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not found in record $_index: " . print_r( $_record, true ) );
                    }

                    $_result = $this->addToTransaction( $_record, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[ $_index ] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[ $_index ] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[ $_index ] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                // operation performed, take output, override earlier
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array('error' => $_errors, 'record' => $_out);
                $_msg = 'Batch Error: Not all records could be created.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to create records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function createRecord( $table, $record, $extras = array() )
    {
        $_records = static::validateAsArray( $record, null, true, 'The request contains no valid record fields.' );

        $_results = $this->createRecords( $table, $_records, $extras );

        return $_results[0];
    }

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecords( $table, $records, $extras = array() )
    {
        $records = static::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $records ) );
        $_rollback = ( $_isSingle ) ? false : Option::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : Option::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = array();
        $_errors = array();
        try
        {
            foreach ( $records as $_index => $_record )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_record, $_idsInfo, $extras ) )
                    {
                        throw new BadRequestException( "Required id field(s) not found in record $_index: " . print_r( $_record, true ) );
                    }

                    $_result = $this->addToTransaction( $_record, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[ $_index ] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[ $_index ] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[ $_index ] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array('error' => $_errors, 'record' => $_out);
                $_msg = 'Batch Error: Not all records could be updated.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to update records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecord( $table, $record, $extras = array() )
    {
        $_records = static::validateAsArray( $record, null, true, 'The request contains no valid record fields.' );

        $_results = $this->updateRecords( $table, $_records, $extras );

        return Option::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'There are no fields in the record.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );

        // slow, but workable for now, maybe faster than merging individuals
        $extras['fields'] = '';
        $_records = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );
        unset( $_records['meta'] );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = static::recordsAsIds( $_records, $_idsInfo );
        $extras['fields'] = $_fields;

        return $this->updateRecordsByIds( $table, $record, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @throws \DreamFactory\Platform\Exceptions\RestException
     * @return array
     */
    public function updateRecordsByIds( $table, $record, $ids, $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $ids = static::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = ( $_isSingle ) ? false : Option::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : Option::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        static::removeIds( $record, $_idFields );
        $extras['updates'] = $record;

        $_out = array();
        $_errors = array();
        try
        {
            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_id, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not valid in request $_index: " . print_r( $_id, true ) );
                    }

                    $_result = $this->addToTransaction( null, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[ $_index ] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[ $_index ] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[ $_index ] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array('error' => $_errors, 'record' => $_out);
                $_msg = 'Batch Error: Not all records could be updated.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to update records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param string $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function updateRecordById( $table, $record, $id, $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->updateRecordsByIds( $table, $record, $id, $extras );

        return Option::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function mergeRecords( $table, $records, $extras = array() )
    {
        $records = static::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $records ) );
        $_rollback = ( $_isSingle ) ? false : Option::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : Option::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = array();
        $_errors = array();
        try
        {
            foreach ( $records as $_index => $_record )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_record, $_idsInfo, $extras ) )
                    {
                        throw new BadRequestException( "Required id field(s) not found in record $_index: " . print_r( $_record, true ) );
                    }

                    $_result = $this->addToTransaction( $_record, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[ $_index ] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[ $_index ] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[ $_index ] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array('error' => $_errors, 'record' => $_out);
                $_msg = 'Batch Error: Not all records could be patched.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to patch records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function mergeRecord( $table, $record, $extras = array() )
    {
        $_records = static::validateAsArray( $record, null, true, 'The request contains no valid record fields.' );

        $_results = $this->mergeRecords( $table, $_records, $extras );

        return Option::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param  array $record
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function mergeRecordsByFilter( $table, $record, $filter = null, $params = array(), $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'There are no fields in the record.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );

        // slow, but workable for now, maybe faster than merging individuals
        $extras['fields'] = '';
        $_records = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );
        unset( $_records['meta'] );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = static::recordsAsIds( $_records, $_idsInfo );
        $extras['fields'] = $_fields;

        return $this->mergeRecordsByIds( $table, $record, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function mergeRecordsByIds( $table, $record, $ids, $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'There are no fields in the record.' );
        $ids = static::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = ( $_isSingle ) ? false : Option::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : Option::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        static::removeIds( $record, $_idFields );
        $extras['updates'] = $record;

        $_out = array();
        $_errors = array();
        try
        {
            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_id, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not valid in request $_index: " . print_r( $_id, true ) );
                    }

                    $_result = $this->addToTransaction( null, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[ $_index ] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[ $_index ] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[ $_index ] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array('error' => $_errors, 'record' => $_out);
                $_msg = 'Batch Error: Not all records could be patched.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to patch records in '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param array  $record
     * @param string $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function mergeRecordById( $table, $record, $id, $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->mergeRecordsByIds( $table, $record, $id, $extras );

        return Option::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecords( $table, $records, $extras = array() )
    {
        $records = static::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = array();
        foreach ( $records as $_record )
        {
            $_ids[] = static::checkForIds( $_record, $_idsInfo, $extras );
        }

        return $this->deleteRecordsByIds( $table, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecord( $table, $record, $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->deleteRecords( $table, array($record), $extras );

        return Option::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecordsByFilter( $table, $filter, $params = array(), $extras = array() )
    {
        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );

        // slow, but workable for now, maybe faster than deleting individuals
        $extras['fields'] = '';
        $_records = $this->retrieveRecordsByFilter( $table, $filter, $params, $extras );
        unset( $_records['meta'] );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = static::recordsAsIds( $_records, $_idsInfo, $extras );
        $extras['fields'] = $_fields;

        return $this->deleteRecordsByIds( $table, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecordsByIds( $table, $ids, $extras = array() )
    {
        $ids = static::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $ids ) );
        $_rollback = ( $_isSingle ) ? false : Option::getBool( $extras, 'rollback', false );
        $_continue = ( $_isSingle ) ? false : Option::getBool( $extras, 'continue', false );
        if ( $_rollback && $_continue )
        {
            throw new BadRequestException( 'Rollback and continue operations can not be requested at the same time.' );
        }

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = array();
        $_errors = array();
        try
        {
            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_id, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not valid in request $_index: " . print_r( $_id, true ) );
                    }

                    $_result = $this->addToTransaction( null, $_id, $extras, $_rollback, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[ $_index ] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || $_rollback || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[ $_index ] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[ $_index ] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array('error' => $_errors, 'record' => $_out);
                $_msg = 'Batch Error: Not all records could be deleted.';
            }

            if ( $_rollback )
            {
                $this->rollbackTransaction();

                $_msg .= " All changes rolled back.";
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to delete records from '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param string $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function deleteRecordById( $table, $id, $extras = array() )
    {
        $_results = $this->deleteRecordsByIds( $table, $id, $extras );

        return Option::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param mixed  $filter
     * @param array  $params
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    abstract public function retrieveRecordsByFilter( $table, $filter = null, $params = array(), $extras = array() );

    /**
     * @param string $table
     * @param array  $records
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecords( $table, $records, $extras = array() )
    {
        $records = static::validateAsArray( $records, null, true, 'The request contains no valid record sets.' );

        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $_ids = array();
        foreach ( $records as $_record )
        {
            $_ids[] = static::checkForIds( $_record, $_idsInfo, $extras );
        }

        return $this->retrieveRecordsByIds( $table, $_ids, $extras );
    }

    /**
     * @param string $table
     * @param array  $record
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecord( $table, $record, $extras = array() )
    {
        $record = static::validateAsArray( $record, null, false, 'The request contains no valid record fields.' );

        $_results = $this->retrieveRecords( $table, array($record), $extras );

        return Option::get( $_results, 0, array() );
    }

    /**
     * @param string $table
     * @param mixed  $ids - array or comma-delimited list of record identifiers
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecordsByIds( $table, $ids, $extras = array() )
    {
        $ids = static::validateAsArray( $ids, ',', true, 'The request contains no valid identifiers.' );

        $_fields = Option::get( $extras, 'fields' );
        $_idFields = Option::get( $extras, 'id_field' );
        $_idTypes = Option::get( $extras, 'id_type' );
        $_isSingle = ( 1 == count( $ids ) );
        $_continue = ( $_isSingle ) ? false : Option::getBool( $extras, 'continue', false );

        $this->initTransaction( $table );

        $_fieldsInfo = $this->getFieldsInfo( $table );
        $_idsInfo = $this->getIdsInfo( $table, $_fieldsInfo, $_idFields, $_idTypes );
        if ( empty( $_idsInfo ) )
        {
            throw new InternalServerErrorException( "Identifying field(s) could not be determined." );
        }

        $extras['single'] = $_isSingle;
        $extras['ids_info'] = $_idsInfo;
        $extras['id_fields'] = $_idFields;
        $extras['fields_info'] = $_fieldsInfo;
        $extras['require_more'] = static::_requireMoreFields( $_fields, $_idFields );

        $_out = array();
        $_errors = array();
        try
        {
            foreach ( $ids as $_index => $_id )
            {
                try
                {
                    if ( false === $_id = $this->checkForIds( $_id, $_idsInfo, $extras, true ) )
                    {
                        throw new BadRequestException( "Required id field(s) not valid in request $_index: " . print_r( $_id, true ) );
                    }

                    $_result = $this->addToTransaction( null, $_id, $extras, false, $_continue, $_isSingle );
                    if ( isset( $_result ) )
                    {
                        // operation performed, take output
                        $_out[ $_index ] = $_result;
                    }
                }
                catch ( \Exception $_ex )
                {
                    if ( $_isSingle || !$_continue )
                    {
                        if ( 0 !== $_index )
                        {
                            // first error, don't worry about batch just throw it
                            // mark last error and index for batch results
                            $_errors[] = $_index;
                            $_out[ $_index ] = $_ex->getMessage();
                        }

                        throw $_ex;
                    }

                    // mark error and index for batch results
                    $_errors[] = $_index;
                    $_out[ $_index ] = $_ex->getMessage();
                }
            }

            if ( !empty( $_errors ) )
            {
                throw new BadRequestException();
            }

            $_result = $this->commitTransaction( $extras );
            if ( isset( $_result ) )
            {
                $_out = $_result;
            }

            return $_out;
        }
        catch ( \Exception $_ex )
        {
            $_msg = $_ex->getMessage();

            $_context = null;
            if ( !empty( $_errors ) )
            {
                $_context = array('error' => $_errors, 'record' => $_out);
                $_msg = 'Batch Error: Not all records could be retrieved.';
            }

            if ( $_ex instanceof RestException )
            {
                throw new RestException( $_ex->getStatusCode(), $_msg, $_ex->getCode(), $_ex->getPrevious(), $_context );
            }

            throw new InternalServerErrorException( "Failed to retrieve records from '$table'.\n$_msg", null, null, $_context );
        }
    }

    /**
     * @param string $table
     * @param mixed  $id
     * @param array  $extras
     *
     * @throws \Exception
     * @return array
     */
    public function retrieveRecordById( $table, $id, $extras = array() )
    {
        $_results = $this->retrieveRecordsByIds( $table, $id, $extras );

        return Option::get( $_results, 0, array() );
    }

    // Helper function for record usage

    /**
     * @param $table
     *
     * @return array
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function getFieldsInfo( $table )
    {
        if ( empty( $table ) )
        {
            throw new BadRequestException( 'Table can not be empty.' );
        }

        return array();
    }

    /**
     * @param      $table
     * @param null $fields_info
     * @param null $requested_fields
     * @param null $requested_types
     *
     * @return mixed
     */
    abstract protected function getIdsInfo( $table, $fields_info = null, &$requested_fields = null, $requested_types = null );

    /**
     * @param $id_info
     *
     * @return array
     */
    protected function getIdFieldsFromInfo( $id_info )
    {
        $_fields = array();
        foreach ( $id_info as $_info )
        {
            $_fields[] = Option::get( $_info, 'name' );
        }

        return $_fields;
    }

    /**
     * @param      $record
     * @param      $ids_info
     * @param null $extras
     * @param bool $on_create
     * @param bool $remove
     *
     * @return array|bool|int|mixed|null|string
     */
    protected function checkForIds( &$record, $ids_info, $extras = null, $on_create = false, $remove = false )
    {
        $_id = null;
        if ( !empty( $ids_info ) )
        {
            if ( 1 == count( $ids_info ) )
            {
                $_info = $ids_info[0];
                $_name = Option::get( $_info, 'name' );
                $_value = ( is_array( $record ) ) ? Option::get( $record, $_name, null, $remove ) : $record;
                if ( !empty( $_value ) )
                {
                    $_type = Option::get( $_info, 'type' );
                    switch ( $_type )
                    {
                        case 'int':
                            $_value = intval( $_value );
                            break;
                        case 'string':
                            $_value = strval( $_value );
                            break;
                    }
                    $_id = $_value;
                }
                else
                {
                    $_required = Option::getBool( $_info, 'required' );
                    // could be passed in as a parameter affecting all records
                    $_param = Option::get( $extras, $_name );
                    if ( $on_create && $_required && empty( $_param ) )
                    {
                        return false;
                    }
                }
            }
            else
            {
                $_id = array();
                foreach ( $ids_info as $_info )
                {
                    $_name = Option::get( $_info, 'name' );
                    $_value = Option::get( $record, $_name, null, $remove );
                    if ( !empty( $_value ) )
                    {
                        $_type = Option::get( $_info, 'type' );
                        switch ( $_type )
                        {
                            case 'int':
                                $_value = intval( $_value );
                                break;
                            case 'string':
                                $_value = strval( $_value );
                                break;
                        }
                        $_id[ $_name ] = $_value;
                    }
                    else
                    {
                        $_required = Option::getBool( $_info, 'required' );
                        // could be passed in as a parameter affecting all records
                        $_param = Option::get( $extras, $_name );
                        if ( $on_create && $_required && empty( $_param ) )
                        {
                            return false;
                        }
                    }
                }
            }
        }

        if ( !empty( $_id ) )
        {
            return $_id;
        }
        elseif ( $on_create )
        {
            return array();
        }

        return false;
    }

    /**
     * @param array $record
     * @param array $fields_info
     * @param array $filter_info
     * @param bool  $for_update
     * @param array $old_record
     *
     * @return array
     * @throws \Exception
     */
    protected function parseRecord( $record, $fields_info, $filter_info = null, $for_update = false, $old_record = null )
    {
//        $record = DataFormat::arrayKeyLower( $record );
        $_parsed = ( empty( $fields_info ) ) ? $record : array();
        if ( !empty( $fields_info ) )
        {
            $_keys = array_keys( $record );
            $_values = array_values( $record );
            foreach ( $fields_info as $_fieldInfo )
            {
//            $name = strtolower( Option::get( $field_info, 'name', '' ) );
                $_name = Option::get( $_fieldInfo, 'name', '' );
                $_type = Option::get( $_fieldInfo, 'type' );
                $_pos = array_search( $_name, $_keys );
                if ( false !== $_pos )
                {
                    $_fieldVal = Option::get( $_values, $_pos );
                    // due to conversion from XML to array, null or empty xml elements have the array value of an empty array
                    if ( is_array( $_fieldVal ) && empty( $_fieldVal ) )
                    {
                        $_fieldVal = null;
                    }

                    /** validations **/

                    $_validations = Option::get( $_fieldInfo, 'validation' );

                    if ( !static::validateFieldValue( $_name, $_fieldVal, $_validations, $for_update, $_fieldInfo ) )
                    {
                        unset( $_keys[ $_pos ] );
                        unset( $_values[ $_pos ] );
                        continue;
                    }

                    $_parsed[ $_name ] = $_fieldVal;
                    unset( $_keys[ $_pos ] );
                    unset( $_values[ $_pos ] );
                }

                // add or override for specific fields
                switch ( $_type )
                {
                    case 'timestamp_on_create':
                        if ( !$for_update )
                        {
                            $_parsed[ $_name ] = time();
                        }
                        break;
                    case 'timestamp_on_update':
                        $_parsed[ $_name ] = time();
                        break;
                    case 'user_id_on_create':
                        if ( !$for_update )
                        {
                            $userId = Session::getCurrentUserId();
                            if ( isset( $userId ) )
                            {
                                $_parsed[ $_name ] = $userId;
                            }
                        }
                        break;
                    case 'user_id_on_update':
                        $userId = Session::getCurrentUserId();
                        if ( isset( $userId ) )
                        {
                            $_parsed[ $_name ] = $userId;
                        }
                        break;
                }
            }
        }

        if ( !empty( $filter_info ) )
        {
            $this->validateRecord( $_parsed, $filter_info, $for_update, $old_record );
        }

        return $_parsed;
    }

    /**
     * @param array $record
     * @param array $filter_info
     * @param bool  $for_update
     * @param array $old_record
     *
     * @throws \Exception
     */
    protected function validateRecord( $record, $filter_info, $for_update = false, $old_record = null )
    {
        $_filters = Option::get( $filter_info, 'filters' );

        if ( empty( $_filters ) || empty( $record ) )
        {
            return;
        }

        $_combiner = Option::get( $filter_info, 'filter_op', 'and' );
        foreach ( $_filters as $_filter )
        {
            $_filterField = Option::get( $_filter, 'name' );
            $_operator = Option::get( $_filter, 'operator' );
            $_filterValue = Option::get( $_filter, 'value' );
            $_filterValue = static::interpretFilterValue( $_filterValue );
            $_foundInRecord = ( is_array( $record ) ) ? array_key_exists( $_filterField, $record ) : false;
            $_recordValue = Option::get( $record, $_filterField );
            $_foundInOld = ( is_array( $old_record ) ) ? array_key_exists( $_filterField, $old_record ) : false;
            $_oldValue = Option::get( $old_record, $_filterField );
            $_compareFound = ( $_foundInRecord || ( $for_update && $_foundInOld ) );
            $_compareValue = $_foundInRecord ? $_recordValue : ( $for_update ? $_oldValue : null );

            $_reason = null;
            if ( $for_update && !$_compareFound )
            {
                // not being set, filter on update will check old record
                continue;
            }

            if ( !static::compareByOperator( $_operator, $_compareFound, $_compareValue, $_filterValue ) )
            {
                $_reason = "Denied access to some of the requested fields.";
            }

            switch ( strtolower( $_combiner ) )
            {
                case 'and':
                    if ( !empty( $_reason ) )
                    {
                        // any reason is a good reason to bail
                        throw new ForbiddenException( $_reason );
                    }
                    break;
                case 'or':
                    if ( empty( $_reason ) )
                    {
                        // at least one was successful
                        return;
                    }
                    break;
                default:
                    throw new InternalServerErrorException( 'Invalid server configuration detected.' );

            }
        }
    }

    /**
     * @param $operator
     * @param $left_found
     * @param $left
     * @param $right
     *
     * @return bool
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     */
    public static function compareByOperator( $operator, $left_found, $left, $right )
    {
        switch ( $operator )
        {
            case DbFilterOperators::EQ:
                return ( $left == $right );
            case DbFilterOperators::NE:
                return ( $left != $right );
            case DbFilterOperators::GT:
                return ( $left > $right );
            case DbFilterOperators::LT:
                return ( $left < $right );
            case DbFilterOperators::GE:
                return ( $left >= $right );
            case DbFilterOperators::LE:
                return ( $left <= $right );
            case DbFilterOperators::STARTS_WITH:
                return static::startsWith( $left, $right );
            case DbFilterOperators::ENDS_WITH:
                return static::endswith( $left, $right );
            case DbFilterOperators::CONTAINS:
                return ( false !== strpos( $left, $right ) );
            case DbFilterOperators::IN:
                return Utilities::isInList( $right, $left );
            case DbFilterOperators::NOT_IN:
                return !Utilities::isInList( $right, $left );
            case DbFilterOperators::IS_NULL:
                return is_null( $left );
            case DbFilterOperators::IS_NOT_NULL:
                return !is_null( $left );
            case DbFilterOperators::DOES_EXIST:
                return ( $left_found );
            case DbFilterOperators::DOES_NOT_EXIST:
                return ( !$left_found );
            default:
                throw new InternalServerErrorException( 'Invalid server configuration detected.' );
        }
    }

    /**
     * @param      $name
     * @param      $value
     * @param      $validations
     * @param bool $for_update
     * @param null $field_info
     *
     * @return bool
     * @throws \DreamFactory\Platform\Exceptions\InternalServerErrorException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     */
    protected function validateFieldValue( $name, $value, $validations, $for_update = false, $field_info = null )
    {
        if ( is_array( $validations ) )
        {
            foreach ( $validations as $_key => $_config )
            {
                $_onFail = Option::get( $_config, 'on_fail' );
                $_throw = true;
                $_msg = null;
                if ( !empty( $_onFail ) )
                {
                    if ( 0 == strcasecmp( $_onFail, 'ignore_field' ) )
                    {
                        $_throw = false;
                    }
                    else
                    {
                        $_msg = $_onFail;
                    }
                }

                switch ( $_key )
                {
                    case 'api_read_only':
                        if ( $_throw )
                        {
                            $_msg = ( !empty( $_msg ) ) ? : "Field '$name' is read only.";
                            throw new BadRequestException( $_msg );
                        }

                        return false;
                        break;
                    case 'create_only':
                        if ( $for_update )
                        {
                            if ( $_throw )
                            {
                                $_msg = ( !empty( $_msg ) ) ? : "Field '$name' can only be set during record creation.";
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'not_null':
                        if ( is_null( $value ) )
                        {
                            if ( $_throw )
                            {
                                $_msg = ( !empty( $_msg ) ) ? : "Field '$name' value can not be null.";
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'not_empty':
                        if ( !is_null( $value ) && empty( $value ) )
                        {
                            if ( $_throw )
                            {
                                $_msg = ( !empty( $_msg ) ) ? : "Field '$name' value can not be empty.";
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'not_zero':
                        if ( !is_null( $value ) && empty( $value ) )
                        {
                            if ( $_throw )
                            {
                                $_msg = ( !empty( $_msg ) ) ? : "Field '$name' value can not be empty.";
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'email':
                        if ( !empty( $value ) && !filter_var( $value, FILTER_VALIDATE_EMAIL ) )
                        {
                            if ( $_throw )
                            {
                                $_msg = ( !empty( $_msg ) ) ? : "Field '$name' value must be a valid email address.";
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'url':
                        $_sections = Option::clean( Option::get( $_config, 'sections' ) );
                        $_flags = 0;
                        foreach ( $_sections as $_format )
                        {
                            switch ( strtolower( $_format ) )
                            {
                                case 'path':
                                    $_flags &= FILTER_FLAG_PATH_REQUIRED;
                                    break;
                                case 'query':
                                    $_flags &= FILTER_FLAG_QUERY_REQUIRED;
                                    break;
                            }
                        }
                        if ( !empty( $value ) && !filter_var( $value, FILTER_VALIDATE_URL, $_flags ) )
                        {
                            if ( $_throw )
                            {
                                $_msg = ( !empty( $_msg ) ) ? : "Field '$name' value must be a valid URL.";
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'int':
                        $_min = Option::getDeep( $_config, 'range', 'min' );
                        $_max = Option::getDeep( $_config, 'range', 'max' );
                        $_formats = Option::clean( Option::get( $_config, 'formats' ) );

                        $_options = array();
                        if ( is_int( $_min ) )
                        {
                            $_options['min_range'] = $_min;
                        }
                        if ( is_int( $_max ) )
                        {
                            $_options['max_range'] = $_max;
                        }
                        $_flags = 0;
                        foreach ( $_formats as $_format )
                        {
                            switch ( strtolower( $_format ) )
                            {
                                case 'hex':
                                    $_flags &= FILTER_FLAG_ALLOW_HEX;
                                    break;
                                case 'octal':
                                    $_flags &= FILTER_FLAG_ALLOW_OCTAL;
                                    break;
                            }
                        }
                        $_options = array('options' => $_options, 'flags' => $_flags);
                        if ( !is_null( $value ) && !filter_var( $value, FILTER_VALIDATE_REGEXP, $_options ) )
                        {
                            if ( $_throw )
                            {
                                $_msg = ( !empty( $_msg ) ) ? : "Field '$name' value is not in the valid range.";
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'match':
                        $_regex = Option::get( $_config, 'regexp' );
                        if ( empty( $_regex ) )
                        {
                            throw new InternalServerErrorException( "Invalid validation configuration: Field '$name' has no 'regexp'." );
                        }

                        $_regex = base64_decode( $_regex );
                        $_options = array('regexp' => $_regex);
                        if ( !empty( $value ) && !filter_var( $value, FILTER_VALIDATE_REGEXP, $_options ) )
                        {
                            if ( $_throw )
                            {
                                $_msg = ( !empty( $_msg ) ) ? : "Field '$name' value is invalid.";
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'picklist':
                        $_values = Option::get( $field_info, 'value' );
                        if ( empty( $_values ) )
                        {
                            throw new InternalServerErrorException( "Invalid validation configuration: Field '$name' has no 'value' in schema settings." );
                        }

                        if ( !empty( $value ) && ( false === array_search( $value, $_values ) ) )
                        {
                            if ( $_throw )
                            {
                                $_msg = ( !empty( $_msg ) ) ? : "Field '$name' value is invalid.";
                                throw new BadRequestException( $_msg );
                            }

                            return false;
                        }
                        break;
                    case 'multi_picklist':
                        $_values = Option::get( $field_info, 'value' );
                        if ( empty( $_values ) )
                        {
                            throw new InternalServerErrorException( "Invalid validation configuration: Field '$name' has no 'value' in schema settings." );
                        }

                        if ( !empty( $value ) )
                        {
                            $_delimiter = Option::get( $_config, 'delimiter', ',' );
                            $_min = Option::get( $_config, 'min', 1 );
                            $_max = Option::get( $_config, 'max' );
                            $value = static::validateAsArray( $value, $_delimiter, true );
                            $_count = count( $value );
                            if ( $_count < $_min )
                            {
                                $_msg = ( !empty( $_msg ) ) ? : "Field '$name' value does not contain enough selections.";
                                throw new BadRequestException( $_msg );
                            }
                            if ( !empty( $_max ) && ( $_count > $_max ) )
                            {
                                $_msg = ( !empty( $_msg ) ) ? : "Field '$name' value contains too many selections.";
                                throw new BadRequestException( $_msg );
                            }
                            foreach ( $value as $_item )
                            {
                                if ( false === array_search( $_item, $_values ) )
                                {
                                    if ( $_throw )
                                    {
                                        $_msg = ( !empty( $_msg ) ) ? : "Field '$name' value is invalid.";
                                        throw new BadRequestException( $_msg );
                                    }

                                    return false;
                                }
                            }
                        }
                        break;
                }
            }
        }

        return true;
    }

    /**
     * @return int
     */
    protected static function getMaxRecordsReturnedLimit()
    {
        return intval( Pii::getParam( 'dsp.db_max_records_returned', static::DB_MAX_RECORDS_RETURNED ) );
    }

    /**
     * @param mixed $handle
     *
     * @return bool
     */
    protected function initTransaction( $handle = null )
    {
        $this->_transactionTable = $handle;
        $this->_batchRecords = array();
        $this->_batchIds = array();
        $this->_rollbackRecords = array();

        return true;
    }

    /**
     * @param mixed      $record
     * @param mixed      $id
     * @param null|array $extras Additional items needed to complete the transaction
     * @param bool       $rollback
     * @param bool       $continue
     * @param bool       $single
     *
     * @throws \DreamFactory\Platform\Exceptions\NotImplementedException
     * @return null|array Array of output fields
     */
    protected function addToTransaction( $record = null, $id = null, $extras = null, $rollback = false, $continue = false, $single = false )
    {
        if ( !empty( $record ) )
        {
            $this->_batchRecords[] = $record;
        }
        if ( !empty( $id ) )
        {
            $this->_batchIds[] = $id;
        }

        return null;
    }

    /**
     * @param null|array $extras Additional items needed to complete the transaction
     *
     * @throws \DreamFactory\Platform\Exceptions\NotFoundException
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array Array of output records
     */
    abstract protected function commitTransaction( $extras = null );

    /**
     * @param mixed $record
     *
     * @return bool
     */
    protected function addToRollback( $record )
    {
        if ( !empty( $record ) )
        {
            $this->_rollbackRecords[] = $record;
        }

        return true;
    }

    /**
     * @return bool
     */
    abstract protected function rollbackTransaction();

    /**
     * @param array        $record
     * @param string|array $include  List of keys to include in the output record
     * @param string|array $id_field Single or list of identifier fields
     *
     * @return array
     */
    protected static function cleanRecord( $record, $include = '*', $id_field = null )
    {
        if ( '*' !== $include )
        {
            if ( !empty( $id_field ) && !is_array( $id_field ) )
            {
                $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
            }
            $id_field = Option::clean( $id_field );

            if ( !empty( $include ) && !is_array( $include ) )
            {
                $include = array_map( 'trim', explode( ',', trim( $include, ',' ) ) );
            }
            $include = Option::clean( $include );

            // make sure we always include identifier fields
            foreach ( $id_field as $id )
            {
                if ( false === array_search( $id, $include ) )
                {
                    $include[] = $id;
                }
            }

            // glean desired fields from record
            $_out = array();
            foreach ( $include as $_key )
            {
                $_out[ $_key ] = Option::get( $record, $_key );
            }

            return $_out;
        }

        return $record;
    }

    /**
     * @param array $records
     * @param mixed $include
     * @param mixed $id_field
     *
     * @return array
     */
    protected static function cleanRecords( $records, $include = '*', $id_field = null )
    {
        $_out = array();
        foreach ( $records as $_record )
        {
            $_out[] = static::cleanRecord( $_record, $include, $id_field );
        }

        return $_out;
    }

    /**
     * @param array $records
     * @param       $ids_info
     * @param null  $extras
     * @param bool  $on_create
     * @param bool  $remove
     *
     * @internal param string $id_field
     * @internal param bool $include_field
     *
     * @return array
     */
    protected static function recordsAsIds( $records, $ids_info, $extras = null, $on_create = false, $remove = false )
    {
        $_out = array();
        if ( !empty( $records ) )
        {
            foreach ( $records as $_record )
            {
                $_out[] = static::checkForIds( $_record, $ids_info, $extras, $on_create, $remove );
            }
        }

        return $_out;
    }

    /**
     * @param array  $record
     * @param string $id_field
     * @param bool   $include_field
     * @param bool   $remove
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array
     */
    protected static function recordAsId( &$record, $id_field = null, $include_field = false, $remove = false )
    {
        if ( empty( $id_field ) )
        {
            return array();
        }

        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }

        if ( count( $id_field ) > 1 )
        {
            $_ids = array();
            foreach ( $id_field as $_field )
            {
                $_id = Option::get( $record, $_field, null, $remove );
                if ( empty( $_id ) )
                {
                    throw new BadRequestException( "Identifying field '$_field' can not be empty for record." );
                }
                $_ids[ $_field ] = $_id;
            }

            return $_ids;
        }
        else
        {
            $_field = $id_field[0];
            $_id = Option::get( $record, $_field, null, $remove );
            if ( empty( $_id ) )
            {
                throw new BadRequestException( "Identifying field '$_field' can not be empty for record." );
            }

            return ( $include_field ) ? array($_field => $_id) : $_id;
        }
    }

    /**
     * @param        $ids
     * @param string $id_field
     * @param bool   $field_included
     *
     * @return array
     */
    protected static function idsAsRecords( $ids, $id_field, $field_included = false )
    {
        if ( empty( $id_field ) )
        {
            return array();
        }

        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }

        $_out = array();
        foreach ( $ids as $_id )
        {
            $_ids = array();
            if ( ( count( $id_field ) > 1 ) && ( count( $_id ) > 1 ) )
            {
                foreach ( $id_field as $_index => $_field )
                {
                    $_search = ( $field_included ) ? $_field : $_index;
                    $_ids[ $_field ] = Option::get( $_id, $_search );
                }
            }
            else
            {
                $_field = $id_field[0];
                $_ids[ $_field ] = $_id;
            }

            $_out[] = $_ids;
        }

        return $_out;
    }

    /**
     * @param array $record
     * @param array $id_field
     */
    protected static function removeIds( &$record, $id_field )
    {
        if ( !empty( $id_field ) )
        {

            if ( !is_array( $id_field ) )
            {
                $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
            }

            foreach ( $id_field as $_name )
            {
                unset( $record[ $_name ] );
            }
        }
    }

    /**
     * @param      $record
     * @param null $id_field
     *
     * @return bool
     */
    protected static function _containsIdFields( $record, $id_field = null )
    {
        if ( empty( $id_field ) )
        {
            return false;
        }

        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }

        foreach ( $id_field as $_field )
        {
            $_temp = Option::get( $record, $_field );
            if ( empty( $_temp ) )
            {
                return false;
            }
        }

        return true;
    }

    /**
     * @param        $fields
     * @param string $id_field
     *
     * @return bool
     */
    protected static function _requireMoreFields( $fields, $id_field = null )
    {
        if ( ( '*' == $fields ) || empty( $id_field ) )
        {
            return true;
        }

        if ( false === $fields = static::validateAsArray( $fields, ',' ) )
        {
            return false;
        }

        if ( !is_array( $id_field ) )
        {
            $id_field = array_map( 'trim', explode( ',', trim( $id_field, ',' ) ) );
        }

        foreach ( $id_field as $_key => $_name )
        {
            if ( false !== array_search( $_name, $fields ) )
            {
                unset( $fields[ $_key ] );
            }
        }

        return !empty( $fields );
    }

    /**
     * @param        $first_array
     * @param        $second_array
     * @param string $id_field
     *
     * @return mixed
     */
    protected static function recordArrayMerge( $first_array, $second_array, $id_field = null )
    {
        if ( empty( $id_field ) )
        {
            return array();
        }

        foreach ( $first_array as $_key => $_first )
        {
            $_firstId = Option::get( $_first, $id_field );
            foreach ( $second_array as $_second )
            {
                $_secondId = Option::get( $_second, $id_field );
                if ( $_firstId == $_secondId )
                {
                    $first_array[ $_key ] = array_merge( $_first, $_second );
                }
            }
        }

        return $first_array;
    }

    /**
     * @param $value
     *
     * @return bool|int|null|string
     */
    public static function interpretFilterValue( $value )
    {
        // all other data types besides strings, just return
        if ( !is_string( $value ) || empty( $value ) )
        {
            return $value;
        }

        $_end = strlen( $value ) - 1;
        // filter string values should be wrapped in matching quotes
        if ( ( ( 0 === strpos( $value, '"' ) ) && ( $_end === strrpos( $value, '"' ) ) ) ||
             ( ( 0 === strpos( $value, "'" ) ) && ( $_end === strrpos( $value, "'" ) ) )
        )
        {
            return substr( $value, 1, $_end - 1 );
        }

        // check for boolean or null values
        switch ( strtolower( $value ) )
        {
            case 'true':
                return true;
            case 'false':
                return false;
            case 'null':
                return null;
        }

        if ( is_numeric( $value ) )
        {
            return $value + 0; // trick to get int or float
        }

        // the rest should be lookup keys, or plain strings
        return Session::replaceLookup( $value );
    }

    /**
     * @param array | string $data          Array to check or comma-delimited string to convert
     * @param string | null  $str_delimiter Delimiter to check for string to array mapping, no op if null
     * @param boolean        $check_single  Check if single (associative) needs to be made multiple (numeric)
     * @param string | null  $on_fail       Error string to deliver in thrown exception
     *
     * @throws \DreamFactory\Platform\Exceptions\BadRequestException
     * @return array | boolean If requirements not met then throws exception if
     * $on_fail string given, or returns false. Otherwise returns valid array
     */
    public static function validateAsArray( $data, $str_delimiter = null, $check_single = false, $on_fail = null )
    {
        if ( !empty( $data ) && !is_array( $data ) && ( is_string( $str_delimiter ) && !empty( $str_delimiter ) ) )
        {
            $data = array_map( 'trim', explode( $str_delimiter, trim( $data, $str_delimiter ) ) );
        }

        if ( !is_array( $data ) || empty( $data ) )
        {
            if ( !is_string( $on_fail ) || empty( $on_fail ) )
            {
                return false;
            }

            throw new BadRequestException( $on_fail );
        }

        if ( $check_single )
        {
            if ( !isset( $data[0] ) )
            {
                // single record possibly passed in without wrapper array
                $data = array($data);
            }
        }

        return $data;
    }

    public static function getFromPostedData( $data, $marker, $default = null )
    {
        $_found = Option::get( $data, $marker );
        if ( empty( $_found ) )
        {
            // xml to array conversion leaves them in plural wrapper
            $_found = Option::getDeep( $data, Inflector::pluralize( $marker ), $marker );
        }

        return ( empty( $_found ) ) ? $default : $_found;
    }

    public static function startsWith( $haystack, $needle )
    {
        return ( substr( $haystack, 0, strlen( $needle ) ) === $needle );
    }

    public static function endsWith( $haystack, $needle )
    {
        return ( substr( $haystack, -strlen( $needle ) ) === $needle );
    }
}
