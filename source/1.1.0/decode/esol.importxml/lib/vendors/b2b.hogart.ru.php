<?php
namespace IX;

class B2bhogartru {
	public static function GetAuthParams()
	{
		return array(
			'phone',
			'password'
		);
	}
	
	public static function GetParamsForDownload(&$arParams, &$arHeaders)
	{
		if(!function_exists('json_encode')) return false;
		$postParams = $arParams['VARS'];
		if(!$postParams['phone'] || !$postParams['password']) return false;
		
		$client = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification' => true));
		$res = $client->post('https://b2b.hogart.ru/api/v1/user/login', array('phone'=>trim($postParams['phone']), 'password'=>trim($postParams['password'])));
		$arRes = json_decode($res, true);
		if(!$arRes['token']) return false;
		
		unset($arParams['VARS'], $arParams['PAGEAUTH'], $arParams['POSTPAGEAUTH']);
		$arHeaders['Authorization'] = 'Bearer '.$arRes['token'];
		return true;
	}
}
?>