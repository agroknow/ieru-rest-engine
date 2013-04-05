<?php
/** 
 * Multilingual translation adapter
 *
 * @package     Analytics API
 * @version     1.1 - 2013-04-04 | 1.0 - 2013-03-15
 * 
 * @author      David Baños Expósito
 * @copyright   Copyright (c)2013
 */

namespace Ieru\Ieruapis\Analytics\Providers\Translation;

interface MultilingualTranslationAdapter
{
	/**
	 * Checks if the service is active or not
	 *
	 * @return boolean
	 */
    public function check_status ();

    /**
     * Tries to connect to the service
     *
     * @return boolean
     */
    public function connect ();

    /**
     * Closes the service
     *
     * @return void
     */
    public function close ();

    /**
     * Sends a translation request to the service
     *
     * @param array 	$data 			Information needed to do the request
     * @return string 	The translation
     */
    public function request ( &$data );
}