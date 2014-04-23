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

use DreamFactory\Platform\Services\SwaggerManager;

$_base = require( __DIR__ . '/BasePlatformRestSvc.swagger.php' );
$_commonResponses = SwaggerManager::getCommonResponses( array(400, 401, 500) );

$_base['apis'] = array(
    array(
        'path'        => '/{api_name}',
        'description' => 'Operations available for SQL DB Schemas.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'getResources() - List resources available for database schema.',
                'nickname'         => 'getResources',
                'type'             => 'Resources',
                'event_name'       => '{api_name}.list',
                'responseMessages' => $_commonResponses,
                'notes'            => 'See listed operations for each resource available.',
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'createTables() - Create one or more tables.',
                'nickname'         => 'createTables',
                'type'             => 'Resources',
                'event_name'       => '{api_name}.create',
                'parameters'       => array(
                    array(
                        'name'          => 'tables',
                        'description'   => 'Array of table definitions.',
                        'allowMultiple' => false,
                        'type'          => 'Tables',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be a single table definition or an array of table definitions.',
            ),
            array(
                'method'           => 'PUT',
                'summary'          => 'updateTables() - Update one or more tables.',
                'nickname'         => 'updateTables',
                'event_name'       => '{api_name}.alter',
                'type'             => 'Resources',
                'parameters'       => array(
                    array(
                        array(
                            'name'          => 'tables',
                            'description'   => 'Array of table definitions.',
                            'allowMultiple' => false,
                            'type'          => 'Tables',
                            'paramType'     => 'body',
                            'required'      => true,
                        ),
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be a single table definition or an array of table definitions.',
            ),
        ),
    ),
    array(
        'path'        => '/{api_name}/{table_name}',
        'description' => 'Operations for per table administration.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'describeTable() - Retrieve table definition for the given table.',
                'nickname'         => 'describeTable',
                'event_name'       => array('{api_name}.{table_name}.describe', '{api_name}.table_described'),
                'type'             => 'TableSchema',
                'parameters'       => array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'This describes the table, its fields and relations to other tables.',
            ),
            array(
                'method'           => 'POST',
                'summary'          => 'createFields() - Create one or more fields in the given table.',
                'nickname'         => 'createFields',
                'type'             => 'Success',
                'event_name'       => array('{api_name}.{table_name}.fields.create', '{api_name}.tables.fields_created'),
                'parameters'       => array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Array of field definitions.',
                        'allowMultiple' => false,
                        'type'          => 'Fields',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be an array of field properties for a single record or an array of fields.',
            ),
            array(
                'method'           => 'PUT',
                'summary'          => 'updateFields() - Update one or more fields in the given table.',
                'nickname'         => 'updateFields',
                'type'             => 'Success',
                'event_name'       => array('{api_name}.{table_name}.fields.alter', '{api_name}.tables.fields_altered'),
                'parameters'       => array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'fields',
                        'description'   => 'Array of field definitions.',
                        'allowMultiple' => false,
                        'type'          => 'Fields',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be an array of field properties for a single record or an array of fields.',
            ),
            array(
                'method'           => 'DELETE',
                'summary'          => 'deleteTable() - Delete (aka drop) the given table.',
                'nickname'         => 'deleteTable',
                'type'             => 'Success',
                'event_name'       => array('{api_name}.{table_name}.drop', '{api_name}.table_dropped'),
                'parameters'       => array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Careful, this drops the database table and all of its contents.',
            ),
        ),
    ),
    array(
        'path'        => '/{api_name}/{table_name}/{field_name}',
        'description' => 'Operations for single field administration.',
        'operations'  => array(
            array(
                'method'           => 'GET',
                'summary'          => 'describeField() - Retrieve the definition of the given field for the given table.',
                'nickname'         => 'describeField',
                'type'             => 'FieldSchema',
                'event_name'       => array('{api_name}.{table_name}.{field_name}.describe', '{api_name}.{table_name}.field_described'),
                'parameters'       => array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'field_name',
                        'description'   => 'Name of the field to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'This describes the field and its properties.',
            ),
            array(
                'method'           => 'PUT',
                'summary'          => 'updateField() - Update one record by identifier.',
                'nickname'         => 'updateField',
                'type'             => 'Success',
                'event_name'       => array('{api_name}.{table_name}.{field_name}.alter', '{api_name}.{table_name}.field_altered'),
                'parameters'       => array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'field_name',
                        'description'   => 'Name of the field to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'field_props',
                        'description'   => 'Array of field properties.',
                        'allowMultiple' => false,
                        'type'          => 'FieldSchema',
                        'paramType'     => 'body',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Post data should be an array of field properties for the given field.',
            ),
            array(
                'method'           => 'DELETE',
                'summary'          => 'deleteField() - Remove the given field from the given table.',
                'nickname'         => 'deleteField',
                'type'             => 'Success',
                'event_name'       => array('{api_name}.{table_name}.{field_name}.drop', '{api_name}.{table_name}.field_dropped'),
                'parameters'       => array(
                    array(
                        'name'          => 'table_name',
                        'description'   => 'Name of the table to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                    array(
                        'name'          => 'field_name',
                        'description'   => 'Name of the field to perform operations on.',
                        'allowMultiple' => false,
                        'type'          => 'string',
                        'paramType'     => 'path',
                        'required'      => true,
                    ),
                ),
                'responseMessages' => $_commonResponses,
                'notes'            => 'Careful, this drops the database table field/column and all of its contents.',
            ),
        ),
    ),
);

$_models = array(
    'Tables'        => array(
        'id'         => 'Tables',
        'properties' => array(
            'field' => array(
                'type'        => 'Array',
                'description' => 'An array of table definitions.',
                'items'       => array(
                    '$ref' => 'TableSchema',
                ),
            ),
        ),
    ),
    'TableSchema'   => array(
        'id'         => 'TableSchema',
        'properties' => array(
            'name'        => array(
                'type'        => 'string',
                'description' => 'Identifier/Name for the table.',
            ),
            'label'       => array(
                'type'        => 'string',
                'description' => 'Displayable singular name for the table.',
            ),
            'plural'      => array(
                'type'        => 'string',
                'description' => 'Displayable plural name for the table.',
            ),
            'primary_key' => array(
                'type'        => 'string',
                'description' => 'Field(s), if any, that represent the primary key of each record.',
            ),
            'name_field'  => array(
                'type'        => 'string',
                'description' => 'Field(s), if any, that represent the name of each record.',
            ),
            'field'       => array(
                'type'        => 'Array',
                'description' => 'An array of available fields in each record.',
                'items'       => array(
                    '$ref' => 'FieldSchema',
                ),
            ),
            'related'     => array(
                'type'        => 'Array',
                'description' => 'An array of available relationships to other tables.',
                'items'       => array(
                    '$ref' => 'RelatedSchema',
                ),
            ),
        ),
    ),
    'Fields'        => array(
        'id'         => 'Fields',
        'properties' => array(
            'field' => array(
                'type'        => 'Array',
                'description' => 'An array of field definitions.',
                'items'       => array(
                    '$ref' => 'FieldSchema',
                ),
            ),
        ),
    ),
    'FieldSchema'   => array(
        'id'         => 'FieldSchema',
        'properties' => array(
            'name'               => array(
                'type'        => 'string',
                'description' => 'The API name of the field.',
            ),
            'label'              => array(
                'type'        => 'string',
                'description' => 'The displayable label for the field.',
            ),
            'type'               => array(
                'type'        => 'string',
                'description' => 'The DSP abstract data type for this field.',
            ),
            'db_type'            => array(
                'type'        => 'string',
                'description' => 'The native database type used for this field.',
            ),
            'length'             => array(
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'The maximum length allowed (in characters for string, displayed for numbers).',
            ),
            'precision'          => array(
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Total number of places for numbers.',
            ),
            'scale'              => array(
                'type'        => 'integer',
                'format'      => 'int32',
                'description' => 'Number of decimal places allowed for numbers.',
            ),
            'default_value'      => array(
                'type'        => 'string',
                'description' => 'Default value for this field.',
            ),
            'required'           => array(
                'type'        => 'boolean',
                'description' => 'Is a value required for record creation.',
            ),
            'allow_null'         => array(
                'type'        => 'boolean',
                'description' => 'Is null allowed as a value.',
            ),
            'fixed_length'       => array(
                'type'        => 'boolean',
                'description' => 'Is the length fixed (not variable).',
            ),
            'supports_multibyte' => array(
                'type'        => 'boolean',
                'description' => 'Does the data type support multibyte characters.',
            ),
            'auto_increment'     => array(
                'type'        => 'boolean',
                'description' => 'Does the integer field value increment upon new record creation.',
            ),
            'is_primary_key'     => array(
                'type'        => 'boolean',
                'description' => 'Is this field used as/part of the primary key.',
            ),
            'is_foreign_key'     => array(
                'type'        => 'boolean',
                'description' => 'Is this field used as a foreign key.',
            ),
            'ref_table'          => array(
                'type'        => 'string',
                'description' => 'For foreign keys, the referenced table name.',
            ),
            'ref_fields'         => array(
                'type'        => 'string',
                'description' => 'For foreign keys, the referenced table field name.',
            ),
            'validation'         => array(
                'type'        => 'Array',
                'description' => 'validations to be performed on this field.',
                'items'       => array(
                    'type' => 'string',
                ),
            ),
            'value'              => array(
                'type'        => 'Array',
                'description' => 'Selectable string values for client menus and picklist validation.',
                'items'       => array(
                    'type' => 'string',
                ),
            ),
        ),
    ),
    'RelatedSchema' => array(
        'id'         => 'RelatedSchema',
        'properties' => array(
            'name'      => array(
                'type'        => 'string',
                'description' => 'Name of the relationship.',
            ),
            'type'      => array(
                'type'        => 'string',
                'description' => 'Relationship type - belongs_to, has_many, many_many.',
            ),
            'ref_table' => array(
                'type'        => 'string',
                'description' => 'The table name that is referenced by the relationship.',
            ),
            'ref_field' => array(
                'type'        => 'string',
                'description' => 'The field name that is referenced by the relationship.',
            ),
            'join'      => array(
                'type'        => 'string',
                'description' => 'The intermediate joining table used for many_many relationships.',
            ),
            'field'     => array(
                'type'        => 'string',
                'description' => 'The current table field that is used in the relationship.',
            ),
        ),
    ),
);

$_base['models'] = array_merge( $_base['models'], $_models );

unset( $_commonResponses, $_models );

return $_base;