<?php
namespace itoeste\afip;
class ItoesteAfip {
	public function say($text)
	{
		return $text.' '.env('AFIP_DEV');
	}
}