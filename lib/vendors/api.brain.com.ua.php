<?php
namespace IX;

class Apibraincomua {
	public static function GetAuthParams()
	{
		return array(
			'login',
			'password'
		);
	}
	
	public static function GetDownloadPath(&$path, &$arParams, &$arHeaders, &$arCookies)
	{
		session_start();
		if(!isset($_SESSION['APIBRAINCOMUA_SID']))
		{
			if(!function_exists('json_encode')) return false;
			$postParams = $arParams['VARS'];
			if(!$postParams['login'] || !$postParams['password']) return false;
			
			$client = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification' => true));
			$res = $client->post('http://api.brain.com.ua/auth', array('login'=>trim($postParams['login']), 'password'=>md5(trim($postParams['password']))));
			$arRes = json_decode($res, true);
			if(!$arRes['result']) return false;
			$_SESSION['APIBRAINCOMUA_SID'] = $arRes['result'];
		}
		$sid = $_SESSION['APIBRAINCOMUA_SID'];
		$sess = $_SESSION;
		session_write_close();
		$_SESSION = $sess;
		
		$path = preg_replace('/(\/)SID(\/|\?|$)/i', '/'.$sid.'$2', $path);
		unset($arParams['VARS'], $arParams['PAGEAUTH'], $arParams['POSTPAGEAUTH']);

		return true;
	}
}
?>