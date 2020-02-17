<?php
namespace itoeste\afip;
class Test {
	public function say($text)
	{
		return $text.' '.env('AFIP_DEV');
	}
}