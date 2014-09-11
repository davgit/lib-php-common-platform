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
namespace DreamFactory\Platform\Yii\Models;

use DreamFactory\Platform\Utility\Platform;
use DreamFactory\Yii\Utility\Pii;
use Kisma\Core\Utility\Sql;

/**
 * Stats.php
 * DSP usage stats
 *
 * Columns
 *
 * @property integer $id
 * @property int     $type
 * @property string  $stat_date
 * @property string  $stat_data
 */
class Stat extends BasePlatformSystemModel
{
    //*************************************************************************
    //* Constants
    //*************************************************************************

    /**
     * @var int
     */
    const TYPE_LOCAL_AUTH = 0;
    /**
     * @var int
     */
    const TYPE_DRUPAL_AUTH = 1;
    /**
     * @var int
     */
    const TYPE_ASGARD = 2;

    //*************************************************************************
    //	Methods
    //*************************************************************************

    /**
     * @return string the associated database table name
     */
    public function tableName()
    {
        return static::tableNamePrefix() . 'stat';
    }

    /**
     * @param int    $type
     * @param int    $userId
     * @param string $statData
     * @param string $date
     *
     * @return int
     */
    public static function create( $type, $userId, $statData, $date = null )
    {
        $_sql = <<<SQL
INSERT INTO df_sys_stat
(
	type,
	user_id,
	stat_date,
	stat_data,
	created_date,
	last_modified_date
)
VALUES
(
	:type,
	:user_id,
	:stat_date,
	:stat_data,
	:created_date,
	:last_modified_date
)
SQL;

        //	Make sure we have a json string in the db...
        if ( !is_string( $statData ) )
        {
            if ( false === ( $_data = json_encode( $statData ) ) )
            {
                return false;
            }

            $statData = $_data;
        }

        $_timestamp = Platform::getSystemTimestamp();

        $_params = array(
            ':type'               => $type,
            ':user_id'            => $userId,
            ':stat_date'          => $date ?: $_timestamp,
            ':stat_data'          => $statData,
            ':created_date'       => $_timestamp,
            ':last_modified_date' => $_timestamp,
        );

        $_pdo = Pii::pdo();

        return Sql::execute( $_sql, $_params, $_pdo );
    }
}