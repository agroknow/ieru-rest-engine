<?php
/** 
 * Handles API requests for Analytics Service.
 *
 * @package     Analytics API
 * @version     1.2 - 2013-04-04 | 1.1 - 2013-02-18 | 1.0 - 2012-10-15
 * 
 * @author      David Baños Expósito
 * @copyright   Copyright (c)2013
 */

namespace Ieru\Ieruapis\Analytics; use \Ieru\Restengine\Engine\Exception\APIException as APIException;

/* Constants */
define( 'NAV_SEARCH_IP', '91.121.175.31' );
define( 'SERVER_ANALYTICS_URL', 'http://lingua.dev' );

class AnalyticsAPI
{
    /**
     * Constructor
     */
    public function __construct ( $params, $config )
    {
        $this->_params = $params;
        $this->_config = $config;
    }

    /**
     * Translates a word or phrase, automatically selecting the translation service.
     *
     * @return string The json with the request to be formatted
     */
    public function get_search ()
    {
        // Default search service
        $service = 'celi';

        // Check the service intended to be used for translation purposes
        try
        {
            // Check that the required parameters are set
            if ( !isset( $this->_params['offset'] ) OR !isset( $this->_params['limit'] ) )
                throw new APIException( 'Check the API documentation for the required parameters.' );

            // If the user requests a specific service, check that it is an allowed one
            if ( array_key_exists( 'service', $this->_config->get_search_services() ) )
            {
                // Check for a valid service
                if ( in_array( $this->_params['service'], $this->_config->get_search_services() ) )
                {
                    $service = $this->_params['service'];
                }
                else
                {
                    throw new APIException( 'Requested search service not available in this API.' );
                }
            }

            // Create service provider adapter
            $class_name = 'Ieru\Ieruapis\Analytics\Providers\Search\\'.ucfirst( $service ).'Service';
            $service = new $class_name();

            // Try to connect to the translation service
            if ( $service->check_status() )
                $service->connect();
            else
                throw new APIException( $class_name.' unavailable.' );

            $response = $service->request( $this->_params, $this->_params['request_string'] );
            $json = $service->format( json_decode( $response ) );

            // Save request in the database
            $this->_save_search_request( $response );
        }
        catch ( APIException $e )
        {
            $e->to_json();
        }

        return $json;
    }

    /**
     * Stores the info of a request in the database.
     *
     * @return void
     * @todo If an error raises while saving the request to a log, send an email to the admins
     */
    private function _save_search_request ( &$response )
    {
        try
        {
            $db_info = $this->_config->get_db_analytics_info();
            $this->_db = $db = new \PDO( 'mysql:host='.$db_info['host'].';dbname='.$db_info['database'], $db_info['username'], $db_info['password'] );
            $data['service_id']       = 1;
           @$data['request_language'] = $this->_params['lang'];
            $data['request_string']   = $this->_params['request_string'];
            $data['request_response'] = $response;
            $data['request_term']     = $this->_params['text'];
            $this->_save_request( $data );

            // Mirar en qué formato se van a guardar los logs a disco y cómo.
            // redis.io <- nosql a logs.
            // little book of redis.
            // Cambiar logs a un adaptador también.

            // Selector de mejor traductor para cada idioma
        }
        // Even if this throws an exception, it must not block sending back the resources
        catch ( \Exception $e )
        {
            // Best option -> send email to the administrator telling them that the logging system is down
        }
    }

    /**
     * Stores the info of a request in the database.
     *
     * @return void
     * @throws Exception
     */
    private function _save_request ( $data )
    {
        if ( !is_array( $data ) )
            throw new Exception( 'Could not save request into analytics service. ' );

        // Get time and ip of the request
        $data['request_ip']       = $_SERVER['REMOTE_ADDR'];
        $data['request_datetime'] = date( 'Y-m-d H:m:s' );

        // Variables for formatting automatically the INSERT statement
        foreach ( $data as $key=>$value )
        {
            if ( !is_array( $value ) )
            {
                $set[] = $key.' = ?';
                $info[] = $value;
            }
        }

        // Esto está haciendo que falle cuando hay varios filtros activos
        //$stmt = $this->_db->prepare( 'INSERT INTO request SET '.implode( ',', $set ) );
        //$stmt->execute( $info );
    }

    /**
     * Fetches the rating associated with a resource
     *
     * @return array
     */
    public function get_rating ()
    {
        $entry = str_replace( '_', '/', $this->_params['entry'] );
        $entry = str_replace( '///', '://', $entry );

        try
        {
            $clienteSOAP = new \SoapClient( 'http://62.217.124.135/cfmodule/server.php?wsdl' );

            $func = 'Functionclass1.resourceRatingsMeanValue';
            $rating = $clienteSOAP->$func( $entry );

            $func = 'Functionclass1.resourceRatingsCount';
            $votes = $clienteSOAP->$func( $entry );
        }
        catch( \SoapFault $e )
        {
            $result = array( 'success'=>false, 'message'=>'Error connecting with ratings service.' );
        }
        
        $rating = ( @$rating->noitemfound || @$rating->norate ) ? 0 : $rating->totalRatingsMeanValue;
        $votes = ( @$votes->noitemfound || @$votes->norate ) ? 0 : $votes->resourceRatingsTotalNumber;

        $result = array( 'success'=>true, 'message'=>'Rating retrieved correctly', 'id'=>$this->_params['id'], 'data'=>array( 'rating'=>round( $rating ), 'votes'=>$votes ) );

        return $result;
    }

    /**
     * Fetches the rating associated with a resource
     *
     * @return array
     */
    public function get_tags ()
    {
        $entry = str_replace( '_', '/', $this->_params['entry'] );
        $entry = str_replace( '///', '://', $entry );

        try
        {
            $clienteSOAP = new \SoapClient( 'http://62.217.124.135/cfmodule/server.php?wsdl' );

            $func = 'Functionclass1.resourceTaggings';
            $tags = $clienteSOAP->$func( $entry );
        }
        catch( \SoapFault $e )
        {
            $result = array( 'success'=>false, 'message'=>'Error connecting with ratings service.' );
        }

        if ( @$tags[0]->noitemfound || @$tags->norate )
            $tags = array();

        $result = array( 'success'=>true, 'message'=>'Tags retrieved correctly', 'id'=>$this->_params['id'], 'data'=>$tags );

        return $result;
    }

    /**
     * @return array
     */
    public function get_history ()
    {
        $entry = str_replace( '_', '/', $this->_params['entry'] );
        $entry = str_replace( '///', '://', $entry );

        try
        {
            $clienteSOAP = new \SoapClient( 'http://62.217.124.135/cfmodule/server.php?wsdl' );
            $func = 'Functionclass1.resourceRatings';
            $rating = $clienteSOAP->$func( $entry );
        }
        catch( \SoapFault $e )
        {
            $result = array( 'success'=>false, 'message'=>'Error connecting with ratings service.' );
        }
        

        //$rating = ( @$rating->noitemfound || @$rating->norate ) ? 0 : $rating->totalRatingsMeanValue;
        if ( @$rating[0]->noitemfound || @$rating->norate )
            $rating = array();

        $result = array( 'success'=>true, 'message'=>'Rating history retrieved correctly', 'id'=>$this->_params['id'], 'data'=>$rating );

        return $result;
    }

    /**
     * @return array
     */
    public function get_review_history ()
    {
        $entry = str_replace( '_', '/', $this->_params['entry'] );
        $entry = str_replace( '///', '://', $entry );

        try
        {
            $clienteSOAP = new \SoapClient( 'http://62.217.124.135/cfmodule/server.php?wsdl' );
            $func = 'Functionclass1.resourceReviewings';
            $rating = $clienteSOAP->$func( $entry );
        }
        catch( \SoapFault $e )
        {
            $result = array( 'success'=>false, 'message'=>'Error connecting with ratings service.' );
        }

        //$rating = ( @$rating->noitemfound || @$rating->norate ) ? 0 : $rating->totalRatingsMeanValue;
        if ( @$rating[0]->noitemfound || @$rating->norate )
        {
            $rating = array();
        }

        $result = array( 'success'=>true, 'message'=>'Rating history retrieved correctly.', 'id'=>$this->_params['id'], 'data'=>$rating );

        return $result;
    }

    /**
     * Translates a text from one language to another specified by the user
     *
     * @return array
     */
    public function get_translation ()
    {
        // Default translation service
        $service = 'microsoft';

        // Check the service intended to be used for translation purposes
        try
        {
            if ( array_key_exists( 'service', $this->_params ) )
            {
                // Check for a valid service
                if ( in_array( $this->_params['service'], $GLOBALS['analyticsapi']['transl_services'] ) )
                {
                    $service = $this->_params['service'];
                }
                else
                {
                    throw new APIException( 'Requested translation service not available in this API.' );
                }
            }

            $class_name = 'Ieru\Ieruapis\Analytics\Providers\Translation\\'.ucfirst( $service ).'Service';
            $service = new $class_name( $this->_params );

            // Try to connect to the translation service
            if ( $service->check_status() )
                $service->connect();
            else
                throw new APIException( $class_name.' unavailable.' );
        }
        catch ( APIException $e )
        {
            $e->to_json();
        }
                
        // Execute the translation
        $translation = $service->request( $this->_params );

        // Save the translation details to the database
        return array( 'success'=>true, 'message'=>'Translation done.', 'data'=>array( 'translation'=>$translation ) );
    }
}