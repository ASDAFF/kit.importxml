<?php
namespace Bitrix\EsolImportxml;

class HttpClient extends \Bitrix\Main\Web\HttpClient
{
	protected static $mProxyList = null;
	protected static $useProxy = true;
	
	
	public function __construct(array $options = null)
	{
		if($options['useProxy']===false) self::$useProxy = false;
		parent::__construct($options);
	}
	
	public function mInitProxyParams()
	{
		if(!isset(self::$mProxyList))
		{
			$moduleId = Utils::GetModuleId();
			$arProxies = unserialize(\Bitrix\Main\Config\Option::get($moduleId, 'PROXIES'));
			if(!is_array($arProxies)) $arProxies = array();
			if(count($arProxies)==0)
			{
				$arProxies[] = array(
					'HOST' => \Bitrix\Main\Config\Option::get($moduleId, 'PROXY_HOST', ''), 
					'PORT' => \Bitrix\Main\Config\Option::get($moduleId, 'PROXY_PORT', ''), 
					'USER' => \Bitrix\Main\Config\Option::get($moduleId, 'PROXY_USER', ''), 
					'PASSWORD' => \Bitrix\Main\Config\Option::get($moduleId, 'PROXY_PASSWORD', '')
				);
			}
			foreach($arProxies as $k=>$v)
			{
				if(!$v['HOST'] || !$v['PORT']) unset($arProxies[$k]);
			}
			self::$mProxyList = array_values($arProxies);
		}

		while(count(self::$mProxyList) > 0)
		{
			$key = rand(0, count(self::$mProxyList) - 1);
			$p = self::$mProxyList[$key];
			if(!array_key_exists('CHECKED', $p))
			{
				if($fp = fsockopen($p['HOST'], $p['PORT'], $errno, $errstr, 3))
				{
					self::$mProxyList[$key]['CHECKED'] = true;
					fclose($fp);
				}
				else
				{
					unset(self::$mProxyList[$key]);
					self::$mProxyList[$key] = array_values(self::$mProxyList);
					continue;
				}
			}
			$this->setProxy($p['HOST'], $p['PORT'], $p['USER'], $p['PASSWORD']);
			return $p;
		}
		return false;
	}
	
	public function download($url, $filePath)
	{
		if(self::$useProxy && ($p = $this->mInitProxyParams()) && preg_match('/^\s*https:/i', $url) && ($res = $this->mDownloadCurl($url, $filePath, $p))) return $res;			
		
		$res = parent::download($url, $filePath);
		if(true /*in_array($this->getStatus(), array(426, 505, 0))*/) //maybe status 200 and size 0
		{
			//$filePath2 = \Bitrix\Main\IO\Path::convertPhysicalToLogical($filePath);
			$filePath2 = \Bitrix\Main\IO\Path::convertLogicalToPhysical($filePath);
			if(filesize($filePath2)==0 || in_array($this->getStatus(), array(426, 505)))
			{
				if(file_exists($filePath2)) unlink($filePath2);
				$res = $this->mDownloadCurl($url, $filePath);
			}
		}
		return $res;
	}
	
	public function mDownloadCurl($url, $filePath, $p=array())
	{
		if(function_exists('curl_init'))
		{
			$arOrigHeaders = array();
			if(is_callable(array($this, 'getRequestHeaders'))) $arOrigHeaders = $this->getRequestHeaders()->toArray();
			elseif(isset($this->requestHeaders)) $arOrigHeaders = $this->requestHeaders->toArray();
			$arHeaders = array();
			$arSHeaders = array();
			foreach($arOrigHeaders as $header)
			{
				foreach($header["values"] as $value)
				{
					$arHeaders[] = $header["name"] . ": ".$value;
					$arSHeaders[$header["name"]] =  $value;
				}
			}
			$cookies = '';
			if(is_callable(array($this, 'getRequestHeaders'))) $cookies = $this->getRequestHeaders()->get('Cookie');
			elseif(isset($this->requestCookies)) $cookies = $this->requestCookies->toString();
			CheckDirPath($filePath);
			//$filePath2 = \Bitrix\Main\IO\Path::convertPhysicalToLogical($filePath);
			$filePath2 = \Bitrix\Main\IO\Path::convertLogicalToPhysical($filePath);
			$f = fopen($filePath2, 'w');
			$ch = curl_init();
			curl_setopt($ch, CURLINFO_HEADER_OUT, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_URL,$url);
			if(isset($p['HOST']) && $p['HOST']) curl_setopt($ch, CURLOPT_PROXY, $p['HOST'].':'.$p['PORT']);
			if(isset($p['USER']) && $p['USER']) curl_setopt($ch, CURLOPT_PROXYUSERPWD, $p['USER'].':'.$p['PASSWORD']);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->redirect);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $arHeaders);
			if($arSHeaders['User-Agent']) curl_setopt($ch, CURLOPT_USERAGENT, $arSHeaders['User-Agent']);
			if(strlen($cookies) > 0) curl_setopt($ch, CURLOPT_COOKIE, $cookies);
			curl_setopt($ch, CURLOPT_TIMEOUT, $this->socketTimeout);
			curl_setopt($ch, CURLOPT_FILE, $f);
			curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'mCurlGetHeaders'));
			$res = curl_exec($ch);
			curl_close($ch);
			fclose($f);

			$this->status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

			return $res;			
		}
		return false;
	}
	
	public function mCurlGetHeaders($ch, $header)
	{
		$len = mb_strlen($header);
		$header = explode(':', $header, 2);
		if(count($header) < 2) return $len;

		$headerName = trim($header[0]);
		$headerValue = trim($header[1]);
		if(ToLower($headerName)=='set-cookie')
		{
			if(isset($this->responseCookies)) $this->responseCookies->addFromString($headerValue);
			else $this->getHeaders()->add('set-cookie', array_map('trim', explode(';', $headerValue)));
		}
		
		if(strpos($headerName, "\0") === false && preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/', $headerName)
			&& strpos($headerValue, "\0") === false && preg_match('/^[\x20\x09\x21-\x7E\x80-\xFF]*$/', $headerValue))
		{
			if(isset($this->responseHeaders)) $this->responseHeaders->add($headerName, $headerValue);
			else $this->getHeaders()->add($headerName, $headerValue);
		}
		return $len;
	}
}
