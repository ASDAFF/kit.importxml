<?php
namespace IX;

class Codamusicru {	
	public static function GetAuthParams()
	{
		return array(
			'login',
			'password',
		);
	}
	
	public static function GetDownloadPath(&$path, &$arParams, &$arHeaders, &$arCookies)
	{
		$authParams = $arParams['VARS'];
		if($authParams['login'] && $authParams['password'])
		{
			$token = '';
			$tokenUrl = 'https://codamusic.ru/Account/JwtPassword/'.$authParams['login'].'/'.$authParams['password'];
			if(function_exists('curl_init'))
			{
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $tokenUrl);
				curl_setopt($ch, CURLOPT_TIMEOUT, 20);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 30);
				$res = curl_exec($ch);
				curl_close($ch);
				if(intval(curl_getinfo($ch, CURLINFO_HTTP_CODE))==200) $token = $res;
			}
			else
			{
				$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>true, 'socketTimeout'=>20, 'streamTimeout'=>20));
				$res = $ob->get($tokenUrl);
				if($ob->getStatus()==200) $token = $res;
			}
			if(strlen($token) > 0) $arHeaders['Authorization'] = 'Bearer '.$token;
		}

		return true;
	}
}
?>