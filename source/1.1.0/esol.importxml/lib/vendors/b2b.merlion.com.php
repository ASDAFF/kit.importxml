<?php
namespace IX;

class B2bmerlioncom {
	public static function GetAuthParams()
	{
		return array(
			'clientNo',
			'clientLogin',
			'password'
		);
	}
	
	public static function GetDownloadFile($arParams, $maxTime=10)
	{
		if(!function_exists('json_encode')) return false;
		$postParams = json_encode($arParams['VARS']);
		$arHeaders = array(
			'User-Agent' => \Bitrix\EsolImportxml\Utils::GetUserAgent(),
			'content-type' => 'application/json',
			'authorization' => 'Bearer initial'
		);
		$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>false));
		foreach($arHeaders as $k=>$v) $ob->setHeader($k, $v);

		$res = $ob->post('https://b2b.merlion.com/api/login', $postParams);
		$arCookies = $ob->getCookies()->toArray();
		$arRes = json_decode($res, true);
		$arHeaders['authorization'] = 'Bearer '.$arRes['csrf_token'];
		
		$path = $arParams['FILELINK'];
		
		if(strlen($arParams['HANDLER_FOR_LINK_BASE64']) > 0) $handler = base64_decode(trim($arParams['HANDLER_FOR_LINK_BASE64']));
		else $handler = trim($arParams['HANDLER_FOR_LINK']);
		if(strlen($handler) > 0)
		{
			$val = '';
			if($path)
			{
				$client = \Bitrix\EsolImportxml\Utils::GetHttpClient(array('disableSslVerification'=>true), $arHeaders, $arCookies, $path);
				$val = $client->get($path);
			}
			$res = \Bitrix\EsolImportxml\Utils::ExecuteFilterExpression($val, $handler, '', $arCookies);
			$path = $res;
		}
		
		if(preg_match('#^\s*https?://b2b\.merlion\.com/pricelists/search/([^/]+.zip)\s*$#', $path, $m))
		{
			$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>false));
			foreach($arHeaders as $k=>$v) $ob->setHeader($k, $v);
			$ob->setCookies($arCookies);
			$res = $ob->get('https://b2b.merlion.com/api/v1/pricelists');
			$arRes = json_decode($res, true);
			if(isset($arRes['data']['data']) && is_array($arRes['data']['data']))
			{
				foreach($arRes['data']['data'] as $v)
				{
					if(!is_array($v)) continue;
					foreach($v as $type=>$v2)
					{
						if(is_array($v2) && isset($v2['name']) && ToLower($v2['name'])==ToLower($m[1]) && isset($v2['lol']))
						{
							$path = 'https://b2b.merlion.com/api/v1/pricelists/get?lol='.$v2['lol'].'&type='.$type;
						}
					}
				}
			}
		}

		$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>false));
		foreach($arHeaders as $k=>$v) $ob->setHeader($k, $v);
		$ob->setCookies($arCookies);
		$fContent = $ob->get($path);
		$hcd = $ob->getHeaders()->get('content-disposition');
		$fn = '';
		if($hcd && stripos($hcd, 'filename=')!==false)
		{
			$hcdParts = preg_grep('/filename=/i', array_map('trim', explode(';', $hcd)));
			if(count($hcdParts) > 0)
			{
				$hcdParts = explode('=', current($hcdParts));
				$fn = end(explode('/', trim(end($hcdParts), '"\' ')));
			}
		}
		if(strlen($fn) > 0)
		{
			$tmpPath = \CFile::GetTempName('', $fn);
			$dir = \Bitrix\Main\IO\Path::getDirectory($tmpPath);
			\Bitrix\Main\IO\Directory::createDirectory($dir);
			file_put_contents($tmpPath, $fContent);
			return \CFile::MakeFileArray($tmpPath);
		}
		
		return false;
	}
}
?>