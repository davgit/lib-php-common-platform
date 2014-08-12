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
//	Already loaded? Bail...
if ( defined( 'DSP_VERSION' ) )
{
	return true;
}

//*************************************************************************
//* Constants
//*************************************************************************

/**
 * @type string
 */
const DSP_VERSION = '1.5.x-dev';
/**
 * @type string
 */
const API_VERSION = '1.0';
/**
 * @type string
 */
const ALIASES_CONFIG_PATH = '/aliases.config.php';
/**
 * @type string
 */
const SERVICES_CONFIG_PATH = '/services.config.php';
/**
 * @type string
 */
const DEFAULT_CLOUD_API_ENDPOINT = 'http://api.cloud.dreamfactory.com';
/**
 * @type string
 */
const DEFAULT_INSTANCE_AUTH_ENDPOINT = 'http://cerberus.fabric.dreamfactory.com/api/instance/credentials';
/**
 * @type string
 */
const DEFAULT_SUPPORT_EMAIL = 'support@dreamfactory.com';
