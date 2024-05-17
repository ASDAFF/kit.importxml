<?php
namespace IX;

class Shopntoysru {	
	static $sectionsBlock = null;
	static $arBrands = null;
	
	public static function GetDownloadFile($arParams, $maxTime=20)
	{		
		if(preg_match('/^https?:\/\/shopntoys\.ru\/apiopt\/api\/xml\/product\/?\?.*key=([^&]+)/is', $arParams['FILELINK'], $m))
		{
			if($maxTime <= 1) $maxTime = 20;
			$key = $m[1];
			$productsPath = $arParams['FILELINK'];
			
			if(!isset(self::$sectionsBlock))
			{
				self::$sectionsBlock = '';
				$categoriesPath = 'https://shopntoys.ru/apiopt/api/xml/category/?key='.$key;
				$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>true, 'socketTimeout'=>$maxTime, 'streamTimeout'=>$maxTime));
				$res = $ob->get($categoriesPath);
				if(preg_match('/<categories.*<\/categories>/Uis', $res, $m2))
				{
					$c = $m2[0];					
					self::$sectionsBlock = $c;
				}
			}
		
			$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>true, 'socketTimeout'=>$maxTime, 'streamTimeout'=>$maxTime));
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
						if(!$findProducts && preg_match('/<content[^>]*>/Uis', $buffer2, $m2))
						{
							$buffer2 = preg_replace('/(<content[^>]*>)/Uis', self::$sectionsBlock.'$1', $buffer2, 1);
							$findProducts = true;
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