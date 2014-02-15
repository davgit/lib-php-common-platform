<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
 *
 * DreamFactory Services Platform(tm) <http://github.com/dreamfactorysoftware/dsp-core>
 * Copyright 2012-2013 DreamFactory Software, Inc. <developer-support@dreamfactory.com>
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

$_constant = array();

$_constant['apis'] = array(
	array(
		'path'        => '/{api_name}/constant',
		'operations'  => array(
			array(
				'method'           => 'GET',
				'summary'          => 'getConstants() - Retrieve all platform enumerated constants.',
				'nickname'         => 'getConstants',
				'type'             => 'Constants',
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Returns an object containing every enumerated type and its constant values',
				'event_name'       => 'constant.list',
			),
		),
		'description' => 'Operations for retrieving platform constants.',
	),
	array(
		'path'        => '/{api_name}/constant/{type}',
		'operations'  => array(
			array(
				'method'           => 'GET',
				'summary'          => 'getConstant() - Retrieve one constant type enumeration.',
				'nickname'         => 'getConstant',
				'type'             => 'Constant',
				'parameters'       => array(
					array(
						'name'          => 'type',
						'description'   => 'Identifier of the enumeration type to retrieve.',
						'allowMultiple' => false,
						'type'          => 'string',
						'paramType'     => 'path',
						'required'      => true,
					),
				),
				'responseMessages' => array(
					array(
						'message' => 'Bad Request - Request does not have a valid format, all required parameters, etc.',
						'code'    => 400,
					),
					array(
						'message' => 'Unauthorized Access - No currently valid session available.',
						'code'    => 401,
					),
					array(
						'message' => 'System Error - Specific reason is included in the error message.',
						'code'    => 500,
					),
				),
				'notes'            => 'Returns , all fields and no relations are returned.',
				'event_name'       => 'constant.get',
			),
		),
		'description' => 'Operations for retrieval individual platform constant enumerations.',
	),
);

$_constant['models'] = array(
	'Constants' => array(
		'id'         => 'Constants',
		'properties' => array(
			'type_name' => array(
				'type'  => 'Array',
				'items' => array(
					'$ref' => 'Constant',
				),
			),
		),
	),
	'Constant'  => array(
		'id'         => 'Constant',
		'properties' => array(
			'name' => array(
				'type'  => 'Array',
				'items' => array(
					'$ref' => 'string',
				),
			),
		),
	),
);

return $_constant;
