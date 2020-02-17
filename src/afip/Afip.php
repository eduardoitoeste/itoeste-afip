<?php
namespace itoeste\afip\afip;
class Afip {

	/**
	 * File name for the WSDL corresponding to WSAA
	 *
	 * @var string
	 **/
	var $WSAA_WSDL;

	/**
	 * The url to get WSAA token
	 *
	 * @var string
	 **/
	var $WSAA_URL;

	/**
	 * File name for the X.509 certificate in PEM format
	 *
	 * @var string
	 **/
	var $CERT;

	/**
	 * File name for the private key correspoding to CERT (PEM)
	 *
	 * @var string
	 **/
	var $PRIVATEKEY;

	/**
	 * The passphrase (if any) to sign
	 *
	 * @var string
	 **/
	var $PASSPHRASE;

	/**
	 * Afip resources folder
	 *
	 * @var string
	 **/
	var $RES_FOLDER;
    var $RUTA_PRINCIPAL;
	/**
	 * The CUIL to use
	 *
	 * @var int
	 **/
	var $CUIL;

	/**
	 * Implemented Web Services
	 *
	 * @var array[string]
	 **/
	var $implemented_ws = array(
		'ElectronicBilling'
	);

	function __construct($options)
	{
		ini_set("soap.wsdl_cache_enabled", "0");
		

		if (!isset($options['CUIT'])) {
			throw new Exception("CUIT field is required in options array");
		}
		else{
			$this->CUIT = $options['CUIT'];
		}

		if (!isset($options['production'])) {
			$options['production'] = FALSE;
		}

		if (!isset($options['passphrase'])) {
			$options['passphrase'] = 'xxxxx';
		}

		if(!isset($options['sede'])){
        {
            $sede = array();
            $sede["Certificado"] = "sede_prueba";
            $this->SEDE =$sede;
        }
        }else
            $this->SEDE = $options['sede'];

		$this->PASSPHRASE = $options['passphrase'];

		$this->options = $options;

		$dir_name = dirname(__FILE__);

        $this->RUTA_PRINCIPAL = $dir_name.'/afip_res//';
		$this->RES_FOLDER 	= $dir_name.'/afip_res//' . $this->SEDE["Certificado"] .'//';
		$this->CERT 		= $this->RES_FOLDER.'cert.crt';
		$this->PRIVATEKEY 	= $this->RES_FOLDER.'key.key';

		//$this->WSAA_WSDL 	= $this->RES_FOLDER.'wsaa.wsdl';
        $this->WSAA_WSDL 	= $this->RUTA_PRINCIPAL.'wsaa.wsdl';


		if ($options['production'] === TRUE) {
			$this->WSAA_URL 	= 'https://wsaa.afip.gov.ar/ws/services/LoginCms';
		}
		else{
			$this->WSAA_URL 	= 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';
		}

		if (!file_exists($this->CERT)) 
			throw new Exception("Failed to open ".$this->CERT."\n", 1);
		if (!file_exists($this->PRIVATEKEY)) 
			throw new Exception("Failed to open ".$this->PRIVATEKEY."\n", 2);
		if (!file_exists($this->WSAA_WSDL)) 
			throw new Exception("Failed to open ".$this->WSAA_WSDL."\n", 3);
	}

	/**
	 * Gets token authorization for an AFIP Web Service
	 *
	 * @since 0.1
	 *
	 * @param string $service Service for token authorization
	 *
	 * @throws Exception if an error occurs
	 *
	 * @return TokenAutorization Token Autorization for AFIP Web Service 
	**/
	public function GetServiceTA($service, $continue = TRUE)
	{
		if (file_exists($this->RES_FOLDER.'TA-'.$service.'.xml')) {
			$TA = new SimpleXMLElement(file_get_contents($this->RES_FOLDER.'TA-'.$service.'.xml'));

			$actual_time 		= new DateTime(date('c',date('U')+600));
			$expiration_time 	= new DateTime($TA->header->expirationTime);

			if ($actual_time < $expiration_time) 
				return new TokenAutorization($TA->credentials->token, $TA->credentials->sign);
			else if ($continue === FALSE)
				throw new Exception("Error Getting TA", 5);
		}

		if ($this->CreateServiceTA($service)) 
			return $this->GetServiceTA($service, FALSE);
	}

	/**
	 * Create an TA from WSAA
	 *
	 * Request to WSAA for a tokent authorization for service and save this
	 * in a xml file
	 *
	 * @since 0.1
	 *
	 * @param string $service Service for token authorization
	 *
	 * @throws Exception if an error occurs creating token authorization
	 *
	 * @return true if token authorization is created success
	**/
	private function CreateServiceTA($service)
	{
		//Creating TRA
		$TRA = new SimpleXMLElement(
		'<?xml version="1.0" encoding="UTF-8"?>' .
		'<loginTicketRequest version="1.0">'.
		'</loginTicketRequest>');
		$TRA->addChild('header');
		$TRA->header->addChild('uniqueId',date('U'));
		$TRA->header->addChild('generationTime',date('c',date('U')-600));
		$TRA->header->addChild('expirationTime',date('c',date('U')+600));
		$TRA->addChild('service',$service);
		$TRA->asXML($this->RES_FOLDER.'TRA-'.$service.'.xml');

		//Signing TRA
		$STATUS = openssl_pkcs7_sign($this->RES_FOLDER."TRA-".$service.".xml", $this->RES_FOLDER."TRA-".$service.".tmp", "file://".$this->CERT,
			array("file://".$this->PRIVATEKEY, $this->PASSPHRASE),
			array(),
			!PKCS7_DETACHED
		);
		if (!$STATUS) {return FALSE;}
		$inf = fopen($this->RES_FOLDER."TRA-".$service.".tmp", "r");
		$i = 0;
		$CMS="";
		while (!feof($inf)) {
			$buffer=fgets($inf);
			if ( $i++ >= 4 ) {$CMS.=$buffer;}
		}
		fclose($inf);
		unlink($this->RES_FOLDER."TRA-".$service.".xml");
		unlink($this->RES_FOLDER."TRA-".$service.".tmp");

		//Request TA to WSAA
		$client = new SoapClient($this->WSAA_WSDL, array(
		'soap_version'   => SOAP_1_2,
		'location'       => $this->WSAA_URL,
		'trace'          => 1,
		'exceptions'     => 0
		)); 
		$results=$client->loginCms(array('in0'=>$CMS));
		if (is_soap_fault($results)) 
			throw new Exception("SOAP Fault: ".$results->faultcode."\n".$results->faultstring."\n", 4);

		$TA = $results->loginCmsReturn;

		if (file_put_contents($this->RES_FOLDER.'TA-'.$service.'.xml', $TA)) 
			return TRUE;
		else
			throw new Exception('Error writing "TA-'.$service.'.xml"', 5);
	}

	public function __get($property)
	{
		if (in_array($property, $this->implemented_ws)) {
			if (isset($this->{$property})) {
				return $this->{$property};
			}
			else{
				//$file = $this->RES_FOLDER."Class\\".$property.'.php';
                $file = $this->RUTA_PRINCIPAL."Class//".$property.'.php';

				if (!file_exists($file)) 
					throw new Exception("Failed to open ".$file."\n", 1);

				include $file;

				return ($this->{$property} = new $property($this));
			}
		}
		else{
			return $this->{$property};
		}
	}
}

class TokenAutorization {
	/**
	 * Authorization and authentication web service Token
	 *
	 * @var string
	 **/
	var $token;

	/**
	 * Authorization and authentication web service Sign
	 *
	 * @var string
	 **/
	var $sign;

	function __construct($token, $sign)
	{
		$this->token 	= $token;
		$this->sign 	= $sign;
	}
}

class AfipWebService
{
	/**
	 * Web service SOAP version
	 *
	 * @var integer
	 **/
	var $soap_version;

	/**
	 * File name for the Web Services Description Language
	 *
	 * @var string
	 **/
	var $WSDL;
	
	/**
	 * The url to web service
	 *
	 * @var string
	 **/
	var $URL;

	/**
	 * File name for the Web Services Description 
	 * Language in test mode
	 *
	 * @var string
	 **/
	var $WSDL_TEST;

	
	/**
	 * The url to web service in test mode
	 *
	 * @var string
	 **/
	var $URL_TEST;
	
	/**
	 * The Afip parent Class
	 *
	 * @var Afip
	 **/
	var $afip;
	
	function __construct($afip)
	{
		$this->afip = $afip;

		if ($this->afip->options['production'] === TRUE) {
			//$this->WSDL = $this->afip->RES_FOLDER.$this->WSDL;
            $this->WSDL = $this->afip->RUTA_PRINCIPAL.$this->WSDL;
		}
		else{
			//$this->WSDL = $this->afip->RES_FOLDER.$this->WSDL_TEST;
            $this->WSDL = $this->afip->RUTA_PRINCIPAL.$this->WSDL_TEST;
			$this->URL 	= $this->URL_TEST;
		}

		if (!file_exists($this->WSDL)) 
			throw new Exception("Failed to open ".$this->WSDL."\n", 3);
	}

	/**
	 * Sends request to AFIP servers
	 * 
	 * @since 1.0
	 *
	 * @param string 	$operation 	SOAP operation to do 
	 * @param array 	$params 	Parameters to send
	 *
	 * @return mixed Operation results 
	 **/
	public function ExecuteRequest($operation, $params = array())
	{
		if (!isset($this->soap_client)) {
			$this->soap_client = new SoapClient($this->WSDL, array(
				'soap_version' 	=> $this->soap_version,
				'location' 		=> $this->URL,
				'trace' 		=> 1,
				'exceptions' 	=> 0
			)); 
		}

		$results = $this->soap_client->{$operation}($params);

		$this->_CheckErrors($operation, $results);

		return $results;
	}


	/**
	 * Check if occurs an error on Web Service request
	 * 
	 * @since 1.0
	 *
	 * @param string 	$operation 	SOAP operation to check 
	 * @param mixed 	$results 	AFIP response
	 *
	 * @throws Exception if exists an error in response 
	 * 
	 * @return void 
	 **/
	private function _CheckErrors($operation, $results)
	{
		if (is_soap_fault($results)) 
			throw new Exception("SOAP Fault: ".$results->faultcode."\n".$results->faultstring."\n", 4);
	}

}
?>