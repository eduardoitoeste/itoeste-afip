<?php
namespace itoeste\afip;
use itoeste\afip\afip\Afip;
class ItoesteAfip {
	private $CUIT;
	private $WSAA_URL;
	private $RES_FOLDER;

	private $TAWSFE;
	private $CRT;
	private $KEY;
	private $SERVICE = 'wsfe';

	private $TOKEN;
	private $SIGN;
	function __construct($options)
	{
		$this->CreateServiceTA();

		$this->RES_FOLDER = __DIR__.'/';

		ini_set("soap.wsdl_cache_enabled", "0");


		if (!isset($options['CUIT'])) {
			return throw new Exception("CUIT es requerido");

		}else{
			$this->CUIT  = $options['CUIT'];
		}

		if (!isset($options['TAWSFE'])) {
			return throw new Exception("TAWSFE es requerido");
		}else{
			$this->TAWSFE  = $options['TAWSFE'];
		}



		// if (env('AFIP_DEV')) {
		if (true) {
			$this->WSAA_URL = 'https://wsaa.afip.gov.ar/ws/services/LoginCms';
		}
		else{
			$this->WSAA_URL = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';
		}




	}

	public function TokenAutorization($token,$sign)
	{
		$this->TOKEN = $token;
		$this->SIGN = $sign;
	}


	public function GetServiceTA($continue = TRUE)
	{
		if(strlen($this->TAWSFE) > 1){
			$TA = new SimpleXMLElement($this->TAWSFE);
			$actual_time 		= new DateTime(date('c',date('U')+600));
			$expiration_time 	= new DateTime($TA->header->expirationTime);
			if ($actual_time < $expiration_time) {
				return $this->TokenAutorization($TA->credentials->token, $TA->credentials->sign);
			}else{
				// throw new Exception("Error Getting TA", 5);
				if ($this->CreateServiceTA($service)){
					return $this->GetServiceTA(FALSE);
				}
			}
		}else{
			return throw new Exception("TAWSFE es requerido");
		}
	}

	public function CreateServiceTA()
	{
		$TRA = new SimpleXMLElement(
		'<?xml version="1.0" encoding="UTF-8"?>' .
		'<loginTicketRequest version="1.0">'.
		'</loginTicketRequest>');
		$TRA->addChild('header');
		$TRA->header->addChild('uniqueId',date('U'));
		$TRA->header->addChild('generationTime',date('c',date('U')-600));
		$TRA->header->addChild('expirationTime',date('c',date('U')+600));
		$TRA->addChild('service',$this->SERVICE);
		$TRA->asXML(public_path().'storage/'.'TRA-'.$this->SERVICE.'.xml');
		$TRA->asXML(public_path().'storage/'.'TRA-'.$this->SERVICE.'.temp');
		return public_path();

	}


	public function say()
	{
		return $this->RES_FOLDER;
	}
}


// $options = [
// 	'cuit'=>234324,
// ];
// $a = new ItoesteAfip($options);

// var_dump($a->say());