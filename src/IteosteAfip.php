<?php
#VERSION 1.0.2
namespace itoeste\afip;
use itoeste\afip\TokenAutorization;
use Exception;
class ItoesteAfip{
	public $CUIT;
	public $WSAA_URL;
	public $RES_FOLDER;

	public $TA;
	public $TATYPE;
	public $CERT;
	public $PRIVATEKEY;
	public $PASSPHRASE;
	public $SERVICE = 'wsfe';

	public $STORAGE;
	public $STORAGEDEFAULT = 'afip/';

	public $TOKEN;
	public $SIGN;

	public $soap_version 	= SOAP_1_2;
	public $soap_client;
	public $WSDL 			= 'wsfe-production.wsdl';
	public $URL 			= 'https://servicios1.afip.gov.ar/wsfev1/service.asmx';
	public $WSDL_TEST 		= 'wsfe.wsdl';
	public $URL_TEST 		= 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';

	public $TRACONVERT = null;


	function __construct($options)
	{
		ini_set("soap.wsdl_cache_enabled", "0");

		

		// storage
		$this->STORAGE = storage_path('app/public').'/';


		// certificado
		if(isset($options['CERT'])){
			$this->CERT=$this->STORAGE.$options['CERT'];
		}else{
			$this->CERT=$this->STORAGE.$this->STORAGEDEFAULT.'cert.crt';
		}

		if(!file_exists($this->CERT)){
			throw new Exception("El archivo cert.crt no existe en ".$this->CERT,1);
		}


		// KEY
		if(isset($options['KEY'])){
			$this->PRIVATEKEY=$this->STORAGE.$options['KEY'];
		}else{
			$this->PRIVATEKEY=$this->STORAGE.$this->STORAGEDEFAULT.'key.key';
		}

		if(!file_exists($this->PRIVATEKEY)){
			throw new Exception("El archivo key.key no existe en ".$this->PRIVATEKEY , 2);
		}


		// clave de parseo
		if(isset($options['PASSPHRASE'])){
			$this->PASSPHRASE = $options['PASSPHRASE'];
		}else{
			$this->PASSPHRASE = 'xxxxx';
		}
		

		// TA
		if(isset($options['TA'])){
			$this->TA = $options['TA'];
			$this->TATYPE = 'STRING';
		}else{
			$this->TA = $this->STORAGE.$this->STORAGEDEFAULT.'TA-wsfe.xml';
			$this->TATYPE = 'FILE';
			if(!file_exists($this->TA)){
				throw new Exception("El archivo TA-wsfe.xml no existe en ".$this->TA , 3);
			}
		}


		// CUIT obligatorio
		if (!isset($options['CUIT'])) {
			throw new Exception("CUIT es requerido");
		}else{
			$this->CUIT  = $options['CUIT'];
		}

		
		if(isset($options['WSAA'])){
			$this->WSAA_WSDL=$this->STORAGE.$options['WSAA'];
		}else{
			$this->WSAA_WSDL = $this->STORAGE.$this->STORAGEDEFAULT.'wsaa.wsdl';
		}

		if(!file_exists($this->WSAA_WSDL)){
			throw new Exception("El archivo wsaa.wsdl no existe en ".$this->STORAGE, 4);
		}

		if (env('AFIP_DEV')) {
			// desarrollo
			$this->WSAA_URL = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';
			$this->WSDL = $this->STORAGE.$this->STORAGEDEFAULT.'wsfe.wsdl';
			$this->URL = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';
			
		}else{
			// prod
			$this->WSAA_URL = 'https://wsaa.afip.gov.ar/ws/services/LoginCms';
			$this->WSDL = $this->STORAGE.$this->STORAGEDEFAULT.'wsfe-production.wsdl';
			$this->URL = 'https://servicios1.afip.gov.ar/wsfev1/service.asmx';
		}

		if(isset($options['WSDL'])){
			$this->WSDL=$this->STORAGE.$options['WSDL'];
		}

		if(!file_exists($this->WSDL)){
			throw new Exception("El archivo wsfe.wsdl no existe en ".$this->STORAGE,6);
		}
		
	}

	public function GetServiceTA($continue = TRUE)
	{
		if($this->TA){
			if($this->TATYPE == 'STRING'){
				$TA = new \SimpleXMLElement($this->TA);
			}
			if($this->TATYPE == 'FILE'){
				if(!file_exists($this->TA)){
					throw new Exception("El archivo TA-wsfe.xml no existe en ".$this->TA , 5);
				}
				
				$TA = new \SimpleXMLElement(file_get_contents($this->TA));
			}
			$actual_time 		= new \DateTime(date('c',date('U')+600));
			$expiration_time 	= new \DateTime($TA->header->expirationTime);
			if ($actual_time < $expiration_time) {
				return new TokenAutorization($TA->credentials->token, $TA->credentials->sign);
			}else{
				if(!$continue){
					throw new Exception("Error Getting TA", 6);
				}
				
				if ($this->CreateServiceTA()){
					return $this->GetServiceTA(FALSE);
				}
			}
		}else{
			throw new Exception("TA es requerido", 7);
		}
	}

	public function CreateServiceTA()
	{
		$TRA = new \SimpleXMLElement(
		'<?xml version="1.0" encoding="UTF-8"?>' .
		'<loginTicketRequest version="1.0">'.
		'</loginTicketRequest>');
		$TRA->addChild('header');
		$TRA->header->addChild('uniqueId',date('U'));
		$TRA->header->addChild('generationTime',date('c',date('U')-600));
		$TRA->header->addChild('expirationTime',date('c',date('U')+600));
		$TRA->addChild('service',$this->SERVICE);
		$TRA->asXML($this->STORAGE.$this->STORAGEDEFAULT.'TRA-'.$this->SERVICE.'.xml');
		$TRA->asXML($this->STORAGE.$this->STORAGEDEFAULT.'TRA-'.$this->SERVICE.'.tmp');
		
		$STATUS = openssl_pkcs7_sign($this->STORAGE.$this->STORAGEDEFAULT."TRA-".$this->SERVICE.".xml", $this->STORAGE.$this->STORAGEDEFAULT."TRA-".$this->SERVICE.".tmp","file://".$this->CERT,
			array("file://".$this->PRIVATEKEY, $this->PASSPHRASE),
			array(),
			!PKCS7_DETACHED
		);

		

		if (!$STATUS) {
			throw new Exception("No se pudo crear el certificado openssl_pkcs7_sign\n", 8);
		}
		$inf = fopen($this->STORAGE.$this->STORAGEDEFAULT."TRA-".$this->SERVICE.".tmp", "r");
		$i = 0;
		$CMS="";
		while (!feof($inf)) {
			$buffer=fgets($inf);
			if ( $i++ >= 4 ) {$CMS.=$buffer;}
		}
		fclose($inf);
		unlink($this->STORAGE.$this->STORAGEDEFAULT."TRA-".$this->SERVICE.".xml");
		unlink($this->STORAGE.$this->STORAGEDEFAULT."TRA-".$this->SERVICE.".tmp");
		
		$client = new \SoapClient($this->WSAA_WSDL, array(
			'soap_version'   => SOAP_1_2,
			'location'       => $this->WSAA_URL,
			'trace'          => 1,
			'exceptions'     => 0
		));

		

		$results=$client->loginCms(array('in0'=>$CMS));
		if (is_soap_fault($results)){
			throw new Exception("SOAP Fault: ".$results->faultcode."\n".$results->faultstring."\n", 9);
		}

		$TA = $results->loginCmsReturn;
		$this->TRACONVERT = $TA;
		$this->TA = $TA;
		$TRA = new \SimpleXMLElement($TA);
			
		if($TRA->asXML($this->STORAGE.$this->STORAGEDEFAULT."TA-".$this->SERVICE.".xml")){
			return true;
		}else{
			throw new Exception('Error writing "TA-'.$this->SERVICE.'.xml"', 10);
		}
		
	}


	public function GetLastBill($sales_point, $type)
	{
		$req = array(
			'PtoVta' 	=> $sales_point,
			'CbteTipo' 	=> $type
		);

		return $this->ExecuteRequest('FECompUltimoAutorizado', $req)->CbteNro;
	}




	public function CreateNewInvoice($data, $return_response = FALSE)
	{
		$req = array(
			'FeCAEReq' => array(
				'FeCabReq' => array(
					'CantReg' 	=> $data['CbteHasta']-$data['CbteDesde']+1,
					'PtoVta' 	=> $data['PtoVta'],
					'CbteTipo' 	=> $data['CbteTipo']
					),
				'FeDetReq' => array( 
					'FECAEDetRequest' => &$data
				)
			)
		);

		unset($data['CantReg']);
		unset($data['PtoVta']);
		unset($data['CbteTipo']);

		if (isset($data['Tributos'])) 
			$data['Tributos'] = array('Tributo' => $data['Tributos']);

		if (isset($data['Iva'])) 
			$data['Iva'] = array('AlicIva' => $data['Iva']);

		if (isset($data['Opcionales'])) 
			$data['Opcionales'] = array('Opcional' => $data['Opcionales']);

		$results = $this->ExecuteRequest('FECAESolicitar', $req);

		if ($return_response === TRUE) {
			return $results;
		}
		
		else{
			return array(
				'CAE' 		=> $results->FeDetResp->FECAEDetResponse->CAE,
				'CAEFchVto' => $this->FormatDate($results->FeDetResp->FECAEDetResponse->CAEFchVto),
			);
		}
	}








	public function ExecuteRequest($operation, $params = array())
	{
		$params = array_replace($this->GetWSInitialRequest($operation), $params); 

		$results = $this->ServiceExecuteRequest($operation, $params);

		$this->_CheckErrors($operation, $results);

		return $results->{$operation.'Result'};
	}

	private function GetWSInitialRequest($operation)
	{
		if ($operation == 'FEDummy') {
			return array();
		}
		$ta = $this->GetServiceTA();

		return array(
			'Auth' => array( 
				'Token' => $ta->token,
				'Sign' 	=> $ta->sign,
				'Cuit' 	=> $this->CUIT
				)
		);
	}





	public function ServiceExecuteRequest($operation, $params = array())
	{
		// try{
			if (!isset($this->soap_client)) {
			
				$this->soap_client = new \SoapClient($this->WSDL, array(
					'soap_version' 	=> $this->soap_version,
					'location' 		=> $this->URL,
					'trace' 		=> 1,
					'exceptions' 	=> 0
				)); 
			}
	
			$results = $this->soap_client->{$operation}($params);
			
			$this->_CheckErrors($operation, $results);
	
			return $results;
		// }catch(Exception $ex){
		// 	throw new Exception($ex, 11);
		// }	
		
	}

	private function _CheckErrors($operation, $results)
	{
		$res = $results->{$operation.'Result'};

		if ($operation == 'FECAESolicitar') {
			if (isset($res->FeDetResp->FECAEDetResponse->Observaciones) && $res->FeDetResp->FECAEDetResponse->Resultado != 'A') {
				$res->Errors = new \StdClass();
				$res->Errors->Err = $res->FeDetResp->FECAEDetResponse->Observaciones->Obs;
			}
		}

		if (isset($res->Errors)) {
			
			$err = is_array($res->Errors->Err) ? $res->Errors->Err[0] : $res->Errors->Err;
			throw new Exception('('.$err->Code.') '.$err->Msg ."\n");
		}
	}

	public function FormatDate($date)
	{
		return date_format(\DateTime::CreateFromFormat('Ymd', $date.''), 'Y-m-d');
	}

}