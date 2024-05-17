<?php
namespace IX;

class Apiercua {
	public static function GetAuthParams()
	{
		return array(
			'username',
			'password'
		);
	}
	
	public static function GetParamsForDownload(&$arParams, &$arHeaders)
	{
		session_start();
		if(!isset($_SESSION['APIERCUA_TOKEN']))
		{
			if(!function_exists('json_encode')) return false;
			$postParams = $arParams['VARS'];
			if(!$postParams['username'] || !$postParams['password']) return false;
			
			$client = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification' => true));
			$res = $client->post('https://api.erc.ua/v1/auth', array('username'=>trim($postParams['username']), 'password'=>trim($postParams['password'])));
			$arRes = json_decode($res, true);
			if(!$arRes['token']) return false;
			$_SESSION['APIERCUA_TOKEN'] = $arRes['token'];
		}
		$token = $_SESSION['APIERCUA_TOKEN'];
		$sess = $_SESSION;
		session_write_close();
		$_SESSION = $sess;
		
		unset($arParams['VARS'], $arParams['PAGEAUTH'], $arParams['POSTPAGEAUTH']);
		$arHeaders['X-AUTH-TOKEN'] = $token;
		return true;
	}
}
?>