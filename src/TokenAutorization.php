<?php
namespace itoeste\afip;
class TokenAutorization {
	/**
	 * Authorization and authentication web service Token
	 *
	 * @var string
	 **/
	public $token;

	/**
	 * Authorization and authentication web service Sign
	 *
	 * @var string
	 **/
	public $sign;

	function __construct($token, $sign)
	{
		$this->token 	= $token;
		$this->sign 	= $sign;
	}
}