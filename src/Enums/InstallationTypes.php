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
namespace DreamFactory\Platform\Enums;

use DreamFactory\Platform\Utility\Fabric;
use Kisma\Core\Enums\SeedEnum;
use Kisma\Core\Utility\Inflector;
use Kisma\Core\Utility\Option;

/**
 * InstallationTypes
 * The different types of DSP installations
 */
class InstallationTypes extends SeedEnum
{
    //*************************************************************************
    //	Constants
    //*************************************************************************

    /**
     * @var string All packages have this doc root
     */
    const DEFAULT_PACKAGE_DOCUMENT_ROOT = '/opt/dreamfactory/platform/var/www/launchpad';

    /**
     * Package Types
     */

    /**
     * @var int
     */
    const FABRIC_HOSTED = 0;
    /**
     * @var int
     */
    const STANDALONE_PACKAGE = 1;
    /**
     * @var int
     */
    const BITNAMI_PACKAGE = 2;
    /**
     * @var int
     */
    const DEB_PACKAGE = 3;
    /**
     * @var int
     */
    const RPM_PACKAGE = 4;
    /**
     * @var int
     */
    const PAAS_DEPLOYMENT = 1000;
    /**
     * @var int
     */
    const BLUEMIX_PACKAGE = 1001;
    /**
     * @var int
     */
    const PIVOTAL_PACKAGE = 1002;

    /**
     * Package Markers
     */

    /**
     * @var string I realize that this is the same as pivotal. Only affects restricted verbs at this time.
     * @todo Find better bluemix marker
     */
    const BLUEMIX_PACKAGE_MARKER = '/home/vcap/app/web';
    /**
     * @var string I realize that this is the same as bluemix. Only affects restricted verbs at this time.
     * @todo Find better pivotal marker
     */
    const PIVOTAL_PACKAGE_MARKER = '/home/vcap/app/web';
    /**
     * @var string
     */
    const BITNAMI_PACKAGE_MARKER = '/apps/dreamfactory/htdocs/web';
    /**
     * @var string
     */
    const DEB_PACKAGE_MARKER = '/opt/dreamfactory/platform/etc/apache2';
    /**
     * @var string
     */
    const RPM_PACKAGE_MARKER = '/opt/dreamfactory/platform/etc/httpd';

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * Determine the type of installation this is
     *
     * @param bool   $prettyPrint Return the pretty name instead of the id
     * @param string $prettyName  Will contain the pretty name upon return
     *
     * @return int|string
     */
    public static function determineType( $prettyPrint = false, &$prettyName = null )
    {
        static $_markers = array(
            //  Search occurs in this order if not fabric-hosted
            self::BITNAMI_PACKAGE => self::BITNAMI_PACKAGE_MARKER,
            self::DEB_PACKAGE     => self::DEB_PACKAGE_MARKER,
            self::RPM_PACKAGE     => self::RPM_PACKAGE_MARKER,
            self::BLUEMIX_PACKAGE => self::BLUEMIX_PACKAGE_MARKER,
            self::PIVOTAL_PACKAGE => self::PIVOTAL_PACKAGE_MARKER,
        );

        $_docRoot = Option::server( 'DOCUMENT_ROOT' );

        //	Default to stand-alone
        $_type = static::FABRIC_HOSTED;

        //	Hosted?
        if ( !Fabric::fabricHosted() )
        {
            $_type = static::STANDALONE_PACKAGE;

            foreach ( $_markers as $_id => $_marker )
            {
                if ( false !== stripos( $_docRoot, $_marker ) )
                {
                    $_type = $_id;
                    break;
                }
            }
        }

        //	Kajigger the name
        $prettyName = Inflector::display( strtolower( static ::nameOf( $_type ) ) );

        return $prettyPrint ? $prettyName : $_type;
    }
}
