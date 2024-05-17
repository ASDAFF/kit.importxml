<?php
namespace IX;

class Apibreezru {
	static $sectionsBlock = null;
	static $arBrands = null;
	
	public static function GetDownloadFile($arParams, $maxTime=20)
	{		
		if(preg_match('/^(https?:\/\/)([^:]*):(.*)@api\.breez\.ru\/v1\/products\/?\?.*format=xml/is', $arParams['FILELINK'], $m))
		{
			if($maxTime <= 1) $maxTime = 20;
			$arHeaders = array('Authorization' => 'Basic '.base64_encode($m[2].':'.$m[3]));
			$productsPath = str_replace($m[2].':'.$m[3], '', $arParams['FILELINK']);
			
			if(!isset(self::$arBrands))
			{
				self::$arBrands = array();
				$brandsPath = 'https://api.breez.ru/v1/brands/?format=xml';
				$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>true, 'socketTimeout'=>$maxTime, 'streamTimeout'=>$maxTime));
				foreach($arHeaders as $k=>$v)
				{
					$ob->setHeader($k, $v);
				}
				$res = $ob->get($brandsPath);
				if(preg_match_all('/<brand.*<\/brand>/Uis', $res, $m2))
				{
					foreach($m2[0] as $k=>$v)
					{
						if(preg_match('/<id.*>(.*)<\/id>/Uis', $v, $m3) && preg_match('/<title.*>(.*)<\/title>/Uis', $v, $m4))
						{
							self::$arBrands[$m3[1]] = $m4[1];
						}
					}
				}
			}
			
			if(!isset(self::$sectionsBlock))
			{
				self::$sectionsBlock = '';
				$categoriesPath = 'https://api.breez.ru/v1/categories/?format=xml';
				$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>true, 'socketTimeout'=>$maxTime, 'streamTimeout'=>$maxTime));
				foreach($arHeaders as $k=>$v)
				{
					$ob->setHeader($k, $v);
				}
				$res = $ob->get($categoriesPath);
				if(preg_match('/<categories.*<\/categories>/Uis', $res, $m2))
				{
					$c = $m2[0];
					/*$lastId = '';
					$lastLevel = 0;
					if(preg_match_all('/\<category.*<id.*>(.*)<\/id>.*<level.*>(.*)<\/level>.*<\/category>/Uis', $c, $m3))
					{
						foreach($m3[0] as $k=>$v)
						{
							if(strpos($v, '<parent_id')==false && strlen($lastId)>0 && $m3[2][$k] > $lastLevel)
							{
								$c = str_replace($v, str_replace('</id>', '</id><parent_id>'.$lastId.'</parent_id>', $v), $c);
							}
							$lastId = $m3[1][$k];
							$lastLevel = $m3[2][$k];
						}
					}*/
					
					self::$sectionsBlock = $c;
				}
			}
		
			$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>true, 'socketTimeout'=>$maxTime, 'streamTimeout'=>$maxTime));
			foreach($arHeaders as $k=>$v)
			{
				$ob->setHeader($k, $v);
			}
			$tmpPath = \CFile::GetTempName('', 'products.xml');
			if($ob->download($productsPath, $tmpPath))
			{
				if(strlen(self::$sectionsBlock) > 0)
				{
					$tmpPath2 = \CFile::GetTempName('', 'products.xml');
					$dir = \Bitrix\Main\IO\Path::getDirectory($tmpPath2);
					\Bitrix\Main\IO\Directory::createDirectory($dir);
					
					$handle1 = fopen($tmpPath, 'r');
					$handle2 = fopen($tmpPath2, 'a');
					$bufferSize = 65536;
					$findProducts = false;
					$buffer = '';
					while(!feof($handle1)) 
					{
						$buffer = $buffer.fgets($handle1, $bufferSize);
						/*if(!$findProducts && ($pos = mb_strpos($buffer, '<products'))!==false)
						{
							$buffer = mb_substr($buffer, 0, $pos).'<data>'.self::$sectionsBlock.mb_substr($buffer, $pos);
							$findProducts = true;
						}
						if($findProducts && feof($handle1) && preg_match('/<\/products>\s*$/', $buffer))
						{
							$buffer = $buffer.'</data>';
						}*/
						if(($pos = mb_strrpos($buffer, '>'))!==false)
						{
							$buffer2 = mb_substr($buffer, 0, $pos+1);
							$buffer = mb_substr($buffer, $pos+1);
						}
						else
						{
							$buffer2 = $buffer;
							$buffer = '';
						}
						if(!$findProducts && preg_match('/<products[^>]*>/Uis', $buffer2, $m2))
						{
							$buffer2 = preg_replace('/(<products[^>]*>)/Uis', '$1'.self::$sectionsBlock, $buffer2, 1);
							$findProducts = true;
						}
						if(preg_match('/(^|>)([^<>]*)<\/brand>/Uis', $buffer2, $m2) && array_key_exists(trim($m2[2]), self::$arBrands))
						{
							$buffer2 = str_replace($m2[0], $m2[0].'<brand_name>'.self::$arBrands[trim($m2[2])].'</brand_name>', $buffer2);
						}
						fwrite($handle2, $buffer2);
					}
					fclose($handle1);
					fclose($handle2);
					if($findProducts) $tmpPath = $tmpPath2;
				}
				$arFile = \CFile::MakeFileArray($tmpPath);
				return $arFile;
			}
		}
		
		return false;
	}
}
?>