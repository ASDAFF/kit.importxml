<?php
namespace IX;

class EscoUa {	
	public static function GetDownloadPath(&$path, &$arParams, &$arHeaders, &$arCookies)
	{
		$arUrl = parse_url($path);
		if(!isset($arHeaders['Host'])) $arHeaders['Host'] = $arUrl['host'];
		if(!isset($arHeaders['Accept'])) $arHeaders['Accept'] = '*/*';
		if(!isset($arHeaders['Accept-Language'])) $arHeaders['Accept-Language'] = 'ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3';
		
		$client = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification' => true));
		$client->setCookies($arCookies);
		foreach($arHeaders as $k=>$v) $client->setHeader($k, $v);
		$res = $client->get($path);
		
		if($client->getStatus()==503)
		{
			$arCookies = array_merge($arCookies, $client->getCookies()->toArray());

			if(preg_match("/eval\('([\d\s\-\+\*\/]+)'\)/", $res, $m))
			{
				$command = '$code = '.$m[1].';';
				eval($command);
			} else return false;

			$client = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification' => true));
			$client->setCookies($arCookies);
			foreach($arHeaders as $k=>$v) $client->setHeader($k, $v);
			$client->setHeader('X-Requested-With', 'XMLHttpRequest');
			$client->setHeader('Referer', $path);
			$res = $client->get($path.'?access_challenge_key='.$code);
			$arCookies = array_merge($arCookies, $client->getCookies()->toArray());
			return true;
		}
		
		return false;
	}
}
?>