<?php
namespace IX;

class Avsautoru {
	public static function GetAuthParams()
	{
		return array(
			'client_id',
			'client_secret',
			'username',
			'password'
		);
	}
	
	public static function GetDownloadFile($arParams, $maxTime=10)
	{
		if(!function_exists('json_encode')) return false;
		$postParams = $arParams['VARS'];
		$token = false;
		
		$arHeaders = array(
			'User-Agent' => \Bitrix\EsolImportxml\Utils::GetUserAgent(),
		);
		$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>false));
		foreach($arHeaders as $k=>$v) $ob->setHeader($k, $v);
		if($postParams['client_id'])
		{
			$res = $ob->post('https://avs-auto.ru/api-cu3rjuyt/methods/oauth.token', $postParams);
			$arCookies = $ob->getCookies()->toArray();
			$arRes = json_decode($res, true);
			$token = $arRes['response']['auth']['token'];
		}
		else
		{
			$location = trim($arParams['POSTPAGEAUTH'] ? $arParams['POSTPAGEAUTH'] : $arParams['PAGEAUTH']);
			$res = $ob->post($location, $postParams);
			$arCookies = $ob->getCookies()->toArray();
		}
		
		$path = $arParams['FILELINK'];
		if(\Bitrix\EsolImportxml\Utils::PathContianApiPages($path))
		{
			$path = \Bitrix\EsolImportxml\Utils::PathReplaceApiPages($path);
		}
		
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

		$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>false));
		foreach($arHeaders as $k=>$v) $ob->setHeader($k, $v);
		$ob->setCookies($arCookies);

		if($token)
		{
			$path = $path.(strpos($path, '?')===false ? '?' : '&').'token='.$token;
		}

		$fContent = $ob->get($path);

		$hcd = $ob->getHeaders()->get('content-disposition');
		$hct = $ob->getHeaders()->get('content-type');
		$fn = explode('?', bx_basename($path))[0];
		if($hcd && stripos($hcd, 'filename=')!==false)
		{
			$hcdParts = preg_grep('/filename=/i', array_map('trim', explode(';', $hcd)));
			if(count($hcdParts) > 0)
			{
				$hcdParts = explode('=', current($hcdParts));
				$fn = end(explode('/', trim(end($hcdParts), '"\' ')));
			}
		}
		if((strpos($hct, 'json')!==false) && mb_substr(ToLower($fn), -5)!='.json') $fn = $fn.'.json';
		elseif((strpos($hct, 'xml')!==false) && mb_substr(ToLower($fn), -4)!='.xml') $fn = $fn.'.xml';
		
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