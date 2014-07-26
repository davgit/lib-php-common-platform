<?php
/**
 * This file is part of the DreamFactory Services Platform(tm) (DSP)
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
namespace DreamFactory\Platform\Events;

/**
 * Used when storage data has been modified
 */
class StorageChangeEvent extends PlatformEvent
{
    //*************************************************************************
    //	Members
    //*************************************************************************

    /**
     * @var array
     */
    protected $_watchEvent;
    /**
     * @var int
     */
    protected $_watchId;
    /**
     * @var int
     */
    protected $_eventMask;
    /**
     * @var int
     */
    protected $_cookie;
    /**
     * @var string
     */
    protected $_name;

    //**************************************************************************
    //* Methods
    //**************************************************************************

    /**
     * @param array $watchEvent
     */
    public function __construct( array $watchEvent = array() )
    {
        $this->_watchId = $watchEvent['wd'];
        $this->_eventMask = $watchEvent['mask'];
        $this->_cookie = $watchEvent['cookie'];
        $this->_name = $watchEvent['name'];

        parent::__construct( $watchEvent );
    }

    /**
     * @return int
     */
    public function getCookie()
    {
        return $this->_cookie;
    }

    /**
     * @return int
     */
    public function getEventMask()
    {
        return $this->_eventMask;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->_name;
    }

    /**
     * @return int
     */
    public function getWatchId()
    {
        return $this->_watchId;
    }

}