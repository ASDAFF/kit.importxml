<?php
namespace Bitrix\EsolImportxml;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class Json2Xml
{
	var $fileJson = '';
	var $fileXml = '';
	var $xmlHandle = null;
	var $siteEncoding = null;
	
	public function __construct()
	{
		$this->siteEncoding = \Bitrix\EsolImportxml\Utils::getSiteEncoding();
	}
	
	public function Convert($fileJson, $fileXml, $hct='')
	{
		if(!file_exists($fileJson)) return false;
		$this->fileJson = $fileJson;
		$this->fileXml = $fileXml;
		
		$json = file_get_contents($this->fileJson);
		$substr = substr($json, 0, 262144);
		$fileEncoding = 'utf-8';
		if(stripos($hct, 'utf-8')!==false) $fileEncoding = 'utf-8';
		elseif(!(\CUtil::DetectUTF8($substr)) && (!function_exists('iconv') || iconv('CP1251', 'CP1251', $substr)==$substr)) $fileEncoding = 'windows-1251';
		if($fileEncoding!=$this->siteEncoding)
		{
			$json = \Bitrix\Main\Text\Encoding::convertEncoding($json, $fileEncoding, $this->siteEncoding);
		}
		if(strpos($json, "\xEF\xBB\xBF")===0)
		{
			$json = mb_substr($json, 3, null, 'CP1251');
		}
		
		if(function_exists('json_decode') && $this->siteEncoding!='windows-1251')
		{
			$arItem = json_decode($json, true);
			if(!$arItem) $arItem = \CUtil::JsObjectToPhp($json);
		}
		else $arItem = \CUtil::JsObjectToPhp($json);
		unset($json);
		if(is_array($arItem))
		{
			CheckDirPath(dirname($this->fileXml));
			if(file_exists($this->fileXml)) unlink($this->fileXml);
			$this->xmlHandle = fopen($this->fileXml, 'a');
			fwrite($this->xmlHandle, '<?xml version="1.0" encoding="'.$this->siteEncoding.'"?>'."\r\n");
			$this->ConvertItem($arItem, 'data');
			fclose($this->xmlHandle);
			unset($arItem);
			return true;
		}
		return false;
	}
	
	public function ConvertItem($arItem, $itemName, $itemId='', $level = 1)
	{
		$itemName = ToLower($itemName);
		$itemName = preg_replace('/[\s,;#?!\.&\*@\$%^\(\)\[\]\{\}<>\/\\\\]/', '_', $itemName);
		if(preg_match('/^[\d\-]/', $itemName)) $itemName = 'd'.$itemName;
		if(strlen($itemName)==0) $itemName = 'd';
		fwrite($this->xmlHandle, str_repeat("\t", $level-1).'<'.$itemName.(strlen($itemId!=='') ? ' itemID="'.$this->GetValueForXml($itemId).'"' : '').'>');
		if(is_array($arItem))
		{
			fwrite($this->xmlHandle, "\r\n");
			foreach($arItem as $itemKey=>$itemVal)
			{
				$itemId = '';
				if(is_numeric($itemKey))
				{
					$itemId = $itemKey;
					$itemKey = 'item';
				}
				$this->ConvertItem($itemVal, $itemKey, $itemId, $level+1);
			}
			fwrite($this->xmlHandle, str_repeat("\t", $level-1));
		}
		else
		{
			fwrite($this->xmlHandle, $this->GetValueForXml($arItem));
		}
		fwrite($this->xmlHandle, '</'.$itemName.'>'."\r\n");
	}
	
	public function GetValueForXml($value)
	{
		$value = htmlspecialchars($value, ENT_QUOTES, $this->siteEncoding);
		$value = preg_replace('/[\x00-\x09\x0b-\x0c\x0e-\x1f]/', '', $value);
		/*if($this->siteEncoding=='windows-1251' && \CUtil::DetectUTF8($value))
		{
			$value = \Bitrix\Main\Text\Encoding::convertEncoding($value, 'UTF-8', $this->siteEncoding);
		}*/
		return $value;
	}
}
?>