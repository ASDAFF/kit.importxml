<?php
namespace Bitrix\EsolImportxml;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class Utils {
	protected static $moduleId = 'esol.importxml';
	protected static $fileSystemEncoding = null;
	protected static $siteEncoding = null;
	protected static $cpSpecCharLetters = null;
	protected static $arAgents = array();
	protected static $countAgents = 0;
	protected static $offerIblocks = array();
	protected static $offerIblockProps = array();
	protected static $lastCookies = array();
	protected static $lastUAgent = '';
	protected static $lastFileHash = '';
	protected static $apiPageParams = array('PAGE'=>'API_PAGE([+\-]\d+)?', 'OFFSET'=>'API_OFFSET_(\d+)');
	protected static $jsCounter = 0;
	protected static $apiPage = 1;
	protected static $eLinkedIblocks = array();
	
	public static function GetModuleId()
	{
		return self::$moduleId;
	}
	
	public static function GetOfferIblock($IBLOCK_ID, $retarray=false)
	{
		if(!$IBLOCK_ID) return false;
		$arFields = array();
		if(!isset(self::$offerIblocks[$IBLOCK_ID]))
		{
			if(!Loader::includeModule('catalog'))
			{
				$arRels = unserialize(\COption::GetOptionString(static::$moduleId, 'CATALOG_RELS'));
				if(!is_array($arRels)) $arRels = array();
				foreach($arRels as $arRel)
				{
					if($arRel['IBLOCK_ID']==$IBLOCK_ID)
					{
						$arIblock = \CIblock::GetById($IBLOCK_ID)->Fetch();
						$arFields = Array(
							'IBLOCK_ID' => $arRel['IBLOCK_ID'],
							'YANDEX_EXPORT' => 'N',
							'SUBSCRIPTION' => 'N',
							'VAT_ID' => 0,
							'PRODUCT_IBLOCK_ID' => 0,
							'SKU_PROPERTY_ID' => 0,
							'OFFERS_PROPERTY_ID' => $arRel['OFFERS_PROP_ID'],
							'OFFERS_IBLOCK_ID' => $arRel['OFFERS_IBLOCK_ID'],
							'ID' => $arRel['IBLOCK_ID'],
							'IBLOCK_TYPE_ID' => $arIblock['IBLOCK_TYPE_ID'],
							'IBLOCK_ACTIVE' => $arIblock['ACTIVE'],
							'LID' => $arIblock['LID'],
							'NAME' => $arIblock['NAME']
						);
					}
				}
			}
			elseif(is_callable(array('\CCatalogSku', 'GetInfoByIBlock')) && defined('\CCatalogSku::TYPE_FULL') && defined('\CCatalogSku::TYPE_PRODUCT') && ($arCatalog = \CCatalogSku::GetInfoByIBlock($IBLOCK_ID)) && in_array($arCatalog['CATALOG_TYPE'], array(\CCatalogSku::TYPE_FULL, \CCatalogSku::TYPE_PRODUCT)) && $arCatalog['PRODUCT_IBLOCK_ID'] > 0)
			{
				$arFields = Array(
					'IBLOCK_ID' => $arCatalog['PRODUCT_IBLOCK_ID'],
					'YANDEX_EXPORT' => $arCatalog['YANDEX_EXPORT'],
					'SUBSCRIPTION' => $arCatalog['SUBSCRIPTION'],
					'VAT_ID' => $arCatalog['VAT_ID'],
					'PRODUCT_IBLOCK_ID' => 0,
					'SKU_PROPERTY_ID' => 0,
					'OFFERS_PROPERTY_ID' => $arCatalog['SKU_PROPERTY_ID'],
					'OFFERS_IBLOCK_ID' => $arCatalog['IBLOCK_ID'],
					'ID' => $arCatalog['PRODUCT_IBLOCK_ID']
				);
			}
			else
			{
				$dbRes = \CCatalog::GetList(array(), array('IBLOCK_ID'=>$IBLOCK_ID));
				$arFields = $dbRes->Fetch();
				if(!$arFields['OFFERS_IBLOCK_ID'])
				{
					$dbRes = \CCatalog::GetList(array(), array('PRODUCT_IBLOCK_ID'=>$IBLOCK_ID));
					if($arFields2 = $dbRes->Fetch())
					{
						$arFields = Array(
							'IBLOCK_ID' => $arFields2['PRODUCT_IBLOCK_ID'],
							'YANDEX_EXPORT' => $arFields2['YANDEX_EXPORT'],
							'SUBSCRIPTION' => $arFields2['SUBSCRIPTION'],
							'VAT_ID' => $arFields2['VAT_ID'],
							'PRODUCT_IBLOCK_ID' => 0,
							'SKU_PROPERTY_ID' => 0,
							'OFFERS_PROPERTY_ID' => $arFields2['SKU_PROPERTY_ID'],
							'OFFERS_IBLOCK_ID' => $arFields2['IBLOCK_ID'],
							'ID' => $arFields2['IBLOCK_ID'],
							'IBLOCK_TYPE_ID' => $arFields2['IBLOCK_TYPE_ID'],
							'IBLOCK_ACTIVE' => $arFields2['IBLOCK_ACTIVE'],
							'LID' => $arFields2['LID'],
							'NAME' => $arFields2['NAME']
						);
					}
				}
			}
			self::$offerIblocks[$IBLOCK_ID] = $arFields;
		}
		else
		{
			$arFields = self::$offerIblocks[$IBLOCK_ID];
		}
		if($arFields['OFFERS_IBLOCK_ID'])
		{
			if($retarray) return $arFields;
			else return $arFields['OFFERS_IBLOCK_ID'];
		}
		return false;
	}
	
	public static function GetOfferIblockByOfferIblock($IBLOCK_ID)
	{
		if(!$IBLOCK_ID) return false;
		if(!isset(self::$offerIblockProps[$IBLOCK_ID]))
		{
			self::$offerIblockProps[$IBLOCK_ID] = array();
			if(Loader::includeModule('catalog'))
			{
				$dbRes = \CCatalog::GetList(array(), array('IBLOCK_ID'=>$IBLOCK_ID));
				if($arCatalog = $dbRes->Fetch())
				{
					self::$offerIblockProps[$IBLOCK_ID] = array(
						'IBLOCK_ID' => $arCatalog['PRODUCT_IBLOCK_ID'],
						'OFFERS_IBLOCK_ID' => $arCatalog['IBLOCK_ID'],
						'OFFERS_PROPERTY_ID' => $arCatalog['SKU_PROPERTY_ID']
					);
				}
			}
		}
		return self::$offerIblockProps[$IBLOCK_ID];
	}
	
	public static function GetXmlReaderClassByFile($fn)
	{
		$xmlReader = (class_exists('\XMLReader') ? '\XMLReader' : '\Bitrix\EsolImportxml\XMLReader');
		//$xmlReader = '\Bitrix\EsolImportxml\XMLReader';
		if(file_exists($fn) && ($c = file_get_contents($fn, false, null, 0, 8192)) && preg_match('/<\w:/', $c))
		{
			$xmlReader = '\XMLReader';
		}
		return $xmlReader;
	}
	
	public static function GetXmlReaderObject($fn)
	{
		$className = self::GetXmlReaderClassByFile($fn);
		if(defined('PHP_VERSION_ID') && PHP_VERSION_ID>=80000 && $className=='\XMLReader' && is_callable(array($className, 'open')))
		{
			$xml = $className::open($fn);
		}
		else
		{
			$xml = new $className();
			$xml->open($fn);
		}
		return $xml;
	}
	
	public static function GetFileName($fn)
	{
		global $APPLICATION;
		if(file_exists($_SERVER['DOCUMENT_ROOT'].$fn)) return $fn;
		
		if(defined("BX_UTF")) $tmpfile = $APPLICATION->ConvertCharsetArray($fn, LANG_CHARSET, 'CP1251');
		else $tmpfile = $APPLICATION->ConvertCharsetArray($fn, LANG_CHARSET, 'UTF-8');
		
		if(file_exists($_SERVER['DOCUMENT_ROOT'].$tmpfile)) return $tmpfile;
		
		return false;
	}
	
	public static function Win1251Utf8($str)
	{
		global $APPLICATION;
		return $APPLICATION->ConvertCharset($str, "Windows-1251", "UTF-8");
	}
	
	public static function GetFileLinesCount($fn)
	{
		if(!file_exists($fn)) return 0;
		
		$cnt = 0;
		$handle = fopen($fn, 'r');
		while (!feof($handle)) {
			$buffer = trim(fgets($handle));
			if($buffer) $cnt++;
		}
		fclose($handle);
		return $cnt;
	}
	
	public static function SortFileIds($fn)
	{
		if(!file_exists($fn)) return 0;

		$arIds = array();
		$handle = fopen($fn, 'r');
		while (!feof($handle)) {
			$buffer = trim(fgets($handle, 128));
			if($buffer) $arIds[] = (int)$buffer;
		}
		fclose($handle);
		sort($arIds, SORT_NUMERIC);

		unlink($fn);

		$handle = fopen($fn, 'a');
		$cnt = count($arIds);
		$step = 10000;
		for($i=0; $i<$cnt; $i+=$step)
		{
			fwrite($handle, implode("\r\n", array_slice($arIds, $i, $step))."\r\n");
		}
		fclose($handle);
		
		if($cnt > 0) return end($arIds);
		else return 0;
	}
	
	public static function GetPartIdsFromFile($fn, $min)
	{
		if(!file_exists($fn)) return array();

		$cnt = 0;
		$maxCnt = 5000;
		$arIds = array();
		$handle = fopen($fn, 'r');
		while (!feof($handle) && $maxCnt>$cnt) {
			$buffer = (int)trim(fgets($handle, 128));
			if($buffer > $min)
			{
				$arIds[] = (int)$buffer;
				$cnt++;
			}
		}
		fclose($handle);
		return $arIds;
	}
	
	public static function GetFileArray($id)
	{
		if(class_exists('\Bitrix\Main\FileTable'))
		{
			$arFile = \Bitrix\Main\FileTable::getList(array('filter'=>array('ID'=>$id)))->fetch();
			if(is_callable(array($arFile['TIMESTAMP_X'], 'toString'))) $arFile['TIMESTAMP_X'] = $arFile['TIMESTAMP_X']->toString();
			$arFile['SRC'] = \CFile::GetFileSRC($arFile, false, false);
		}
		else
		{
			$arFile = \CFile::GetFileArray($id);
		}
		return $arFile;
	}
	
	public static function SaveFile($arFile, $strSavePath=false, $bForceMD5=false, $bSkipExt=false)
	{
		if($strSavePath===false) $strSavePath = static::$moduleId;
		$oProfile = \Bitrix\EsolImportxml\Profile::getInstance();
		$isUtf = (bool)(defined("BX_UTF") && BX_UTF);
		if(\CUtil::DetectUTF8($arFile["name"]))
		{
			if(!$isUtf) $arFile["name"] = \Bitrix\Main\Text\Encoding::convertEncoding($arFile["name"], 'utf-8', LANG_CHARSET);
		}
		else
		{
			if($isUtf) $arFile["name"] = \Bitrix\Main\Text\Encoding::convertEncoding($arFile["name"], 'windows-1251', LANG_CHARSET);
		}
		$strFileName = GetFileName($arFile["name"]);	/* filename.gif */
		if(strpos($strFileName, '.')===0) $strFileName = '_'.$strFileName;

		if(isset($arFile["del"]) && $arFile["del"] <> '')
		{
			\CFile::DoDelete($arFile["old_file"]);
			if($strFileName == '')
				return "NULL";
		}

		if($arFile["name"] == '')
		{
			if(isset($arFile["description"]) && intval($arFile["old_file"])>0)
			{
				\CFile::UpdateDesc($arFile["old_file"], $arFile["description"]);
			}
			return false;
		}

		if (isset($arFile["content"]))
		{
			if (!isset($arFile["size"]))
			{
				$arFile["size"] = \CUtil::BinStrlen($arFile["content"]);
			}
		}
		else
		{
			try
			{
				$file = new \Bitrix\Main\IO\File(\Bitrix\Main\IO\Path::convertPhysicalToLogical($arFile["tmp_name"]));
				$arFile["size"] = $file->getSize();
			}
			catch(\Bitrix\Main\IO\IoException $e)
			{
				$arFile["size"] = 0;
			}
		}

		$arFile["ORIGINAL_NAME"] = $strFileName;

		//translit, replace unsafe chars, etc.
		$strFileName = self::transformName($strFileName, $bForceMD5, $bSkipExt);

		//transformed name must be valid, check disk quota, etc.
		if (self::validateFile($strFileName, $arFile) !== "")
		{
			return false;
		}

		if($arFile["type"] == "image/pjpeg" || $arFile["type"] == "image/jpg")
		{
			$arFile["type"] = "image/jpeg";
		}

		$bExternalStorage = false;
		/*foreach(GetModuleEvents("main", "OnFileSave", true) as $arEvent)
		{
			if(ExecuteModuleEventEx($arEvent, array(&$arFile, $strFileName, $strSavePath, $bForceMD5, $bSkipExt)))
			{
				$bExternalStorage = true;
				break;
			}
		}*/

		if(!$bExternalStorage)
		{
			$upload_dir = \COption::GetOptionString("main", "upload_dir", "upload");
			$io = \CBXVirtualIo::GetInstance();
			if($bForceMD5 != true)
			{
				$dir_add = '';
				$i=0;
				while(true)
				{
					$dir_add = substr(md5(uniqid("", true)), 0, 3);
					if(!$io->FileExists($_SERVER["DOCUMENT_ROOT"]."/".$upload_dir."/".$strSavePath."/".$dir_add."/".$strFileName))
					{
						break;
					}
					if($i >= 25)
					{
						$j=0;
						while(true)
						{
							$dir_add = substr(md5(mt_rand()), 0, 3)."/".substr(md5(mt_rand()), 0, 3);
							if(!$io->FileExists($_SERVER["DOCUMENT_ROOT"]."/".$upload_dir."/".$strSavePath."/".$dir_add."/".$strFileName))
							{
								break;
							}
							if($j >= 25)
							{
								$dir_add = substr(md5(mt_rand()), 0, 3)."/".md5(mt_rand());
								break;
							}
							$j++;
						}
						break;
					}
					$i++;
				}
				if(substr($strSavePath, -1, 1) <> "/")
					$strSavePath .= "/".$dir_add;
				else
					$strSavePath .= $dir_add."/";
			}
			else
			{
				$strFileExt = ($bSkipExt == true || ($ext = GetFileExtension($strFileName)) == ''? '' : ".".$ext);
				while(true)
				{
					if(substr($strSavePath, -1, 1) <> "/")
						$strSavePath .= "/".substr($strFileName, 0, 3);
					else
						$strSavePath .= substr($strFileName, 0, 3)."/";

					if(!$io->FileExists($_SERVER["DOCUMENT_ROOT"]."/".$upload_dir."/".$strSavePath."/".$strFileName))
						break;

					//try the new name
					$strFileName = md5(uniqid("", true)).$strFileExt;
				}
			}

			$arFile["SUBDIR"] = $strSavePath;
			$arFile["FILE_NAME"] = $strFileName;
			$strDirName = $_SERVER["DOCUMENT_ROOT"]."/".$upload_dir."/".$strSavePath."/";
			$strDbFileNameX = $strDirName.$strFileName;
			$strPhysicalFileNameX = $io->GetPhysicalName($strDbFileNameX);

			CheckDirPath($strDirName);

			if(is_set($arFile, "content"))
			{
				$f = fopen($strPhysicalFileNameX, "ab");
				if(!$f)
					return false;
				if(fwrite($f, $arFile["content"]) === false)
					return false;
				fclose($f);
			}
			elseif(
				!copy($arFile["tmp_name"], $strPhysicalFileNameX)
				&& !move_uploaded_file($arFile["tmp_name"], $strPhysicalFileNameX)
			)
			{
				\CFile::DoDelete($arFile["old_file"]);
				return false;
			}

			if(isset($arFile["old_file"]))
				\CFile::DoDelete($arFile["old_file"]);

			@chmod($strPhysicalFileNameX, BX_FILE_PERMISSIONS);

			//flash is not an image
			$flashEnabled = !\CFile::IsImage($arFile["ORIGINAL_NAME"], $arFile["type"]);

			$imgArray = \CFile::GetImageSize($strDbFileNameX, false, $flashEnabled);

			if(is_array($imgArray))
			{
				$arFile["WIDTH"] = $imgArray[0];
				$arFile["HEIGHT"] = $imgArray[1];

				if($imgArray[2] == IMAGETYPE_JPEG)
				{
					$exifData = \CFile::ExtractImageExif($io->GetPhysicalName($strDbFileNameX));
					if ($exifData  && isset($exifData['Orientation']))
					{
						//swap width and height
						if ($exifData['Orientation'] >= 5 && $exifData['Orientation'] <= 8)
						{
							$arFile["WIDTH"] = $imgArray[1];
							$arFile["HEIGHT"] = $imgArray[0];
						}

						$properlyOriented = \CFile::ImageHandleOrientation($exifData['Orientation'], $io->GetPhysicalName($strDbFileNameX));
						if ($properlyOriented)
						{
							$jpgQuality = intval(\COption::GetOptionString('main', 'image_resize_quality', '95'));
							if($jpgQuality <= 0 || $jpgQuality > 100)
								$jpgQuality = 95;
							imagejpeg($properlyOriented, $io->GetPhysicalName($strDbFileNameX), $jpgQuality);
						}
					}
				}
			}
			else
			{
				$arFile["WIDTH"] = 0;
				$arFile["HEIGHT"] = 0;
			}
			
			/*Remove bad string*/
			$ext = GetFileExtension($strFileName);
			if(in_array(Tolower($ext), array('xml', 'yml')) && $strPhysicalFileNameX)
			{
				$break = false;
				$filesize = filesize($strPhysicalFileNameX);
				$handle = fopen($strPhysicalFileNameX, 'r');
				$buffer = '';
				while(!$break && !feof($handle)) 
				{
					$str = fgets($handle, 65536);
					if(trim($str) && strpos($str, '>')!==false && stripos($str, '<?xml')===false && stripos($str, '<!DOCTYPE')===false)
					{
						$break = true;
					}
					$buffer .= $str;
				}
				$pos1 = $pos2 = $pos3 = 0;
				if(preg_match('/<\?xml[^>]*>/Uis', $buffer, $m)){$pos1 = mb_strpos($buffer, $m[0])+mb_strlen($m[0]);}
				if(preg_match('/<!DOCTYPE[^>]*>/Uis', $buffer, $m)){$pos2 = mb_strpos($buffer, $m[0])+mb_strlen($m[0]);}
				if(preg_match('/<[^\?!][^>]*>/Uis', $buffer, $m)){$pos3 = mb_strpos($buffer, $m[0])+mb_strlen($m[0]);}
				$maxPos = max($pos1, $pos2, $pos3);
				$buffer = mb_substr($buffer, 0, $maxPos);
				if(function_exists('mb_strlen')) $maxPos = mb_strlen($buffer, 'CP1251');
				fseek($handle, $maxPos);
				
				$updateFile = false;
				if(\COption::GetOptionString(static::$moduleId, 'AUTO_CORRECT_ENCODING', 'N')=='Y' && preg_match('/<\?xml[^>]*encoding=[\'"]([^\'"]*)[\'"][^>]*\?>/is', $buffer, $m))
				{
					$encoding = ToLower($m[1]);
					if($encoding=='cp1251') $encoding = 'windows-1251';
					if($encoding=='utf8') $encoding = 'utf-8';
					$curPos = ftell($handle);
					$partSize = 262144;
					fseek($handle, 0);
					$contents = fread($handle, $partSize);
					if($filesize > $partSize*2)
					{
						fseek($handle, max(($filesize - $partSize)/2, $partSize));
						$contents .= fread($handle, $partSize);
					}
					if($filesize > $partSize)
					{
						fseek($handle, max($filesize - $partSize, $partSize));
						$contents .= fread($handle, $partSize);
					}
					fseek($handle, $curPos);
					
					try{				
						$contents = preg_replace('/%[A-F0-9]{2}/', '', $contents);
						$fileEncoding = 'utf-8';
						if(!\CUtil::DetectUTF8($contents) && (!function_exists('iconv') || iconv('CP1251', 'CP1251', $contents)==$contents))
						{
							$fileEncoding = 'windows-1251';
						}
						if(in_array($encoding, array('windows-1251', 'utf-8')) && $encoding!=$fileEncoding)
						{
							$buffer = preg_replace('/(<\?xml[^>]*encoding=[\'"])([^\'"]*)([\'"][^>]*\?>)/is', '$1'.$fileEncoding.'$3', $buffer);
							$updateFile = true;
						}
					}catch(\Exception $ex){}
				}
				
				if(preg_match('/<\?xml[^>]*version=[\'"]([^\'"]*)[\'"][^>]*\?>/is', $buffer, $m))
				{
					$version = ToLower($m[1]);
					if($version!='1.0')
					{
						$buffer = preg_replace('/(<\?xml[^>]*version=)([\'"][^\'"]*[\'"])([^>]*\?>)/is', '$1"1.0"$3', $buffer);
						$updateFile = true;
					}
				}
				
				if(preg_match('/\s+xmlns\s*=\s*"[^"]*"\s*/is', $buffer, $m))
				{
					$buffer = str_replace($m[0], ' ', $buffer);
					$updateFile = true;
				}
				
				if(preg_match('/^\s+/s', $buffer, $m))
				{
					$buffer = ltrim($buffer);
					$updateFile = true;
				}

				if($oProfile->GetParam('AUTO_FIX_XML_ERRORS')=='Y')
				{
					$updateFile = true;
				}
				
				if($updateFile)
				{
					$bNumTags = (bool)($oProfile->GetParam('AUTO_FIX_XML_NUMTAGS')=='Y');
					$bNamespaces = (bool)($oProfile->GetParam('AUTO_FIX_XML_NAMESPACES')=='Y');
					$tags = $oProfile->GetParam('AUTO_FIX_XML_CDATA');
					$arTags = array_diff(array_unique(array_map('trim', explode(',', $tags))), array(''));
					
					$tmpFile = $strPhysicalFileNameX.'.tmp';
					$handle2 = fopen($tmpFile, 'a');
					if($bNamespaces) self::ReplaceNS($buffer);
					fwrite($handle2, $buffer);
					if($oProfile->GetParam('AUTO_FIX_XML_ERRORS')=='Y')
					{
						$fileEncoding = 'utf-8';
						if(preg_match('/<\?xml[^>]*encoding=[\'"]([^\'"]*)[\'"]/is', $buffer, $m) && in_array(ToLower($m[1]), array('windows-1251', 'cp1251'))) $fileEncoding = 'cp1251';
						$bufferSize = 65536;
						$bufferEnd = '';
						while(!feof($handle)) 
						{
							$buffer2 = $bufferEnd.fgets($handle, $bufferSize);
							while(($pos = strrpos($buffer2, '<'))===false && !feof($handle))
							{
								$buffer2 .= fgets($handle, $bufferSize);
							}
							if($fileEncoding=='cp1251' && function_exists('iconv'))
							{
								$buffer2 = iconv('CP1251', 'CP1251//IGNORE', $buffer2);
							}
							$bufferEnd = '';
							if($pos!==false && !feof($handle))
							{
								if(substr($buffer2, $pos, 1)!=='<' && function_exists('mb_strrpos'))
								{
									$encoding = self::getSiteEncoding();
									$pos = mb_strrpos($buffer2, '<', $encoding);
									if(mb_substr($buffer2, $pos, 1, $encoding)!=='<')
									{
										$encoding = ($encoding=='utf-8' ? 'windows-1251' : 'utf-8');
										$pos = mb_strrpos($buffer2, '<', $encoding);
									}
									$bufferEnd = mb_substr($buffer2, $pos, 2000000000, $encoding);
									$buffer2 = mb_substr($buffer2, 0, $pos, $encoding);
								}
								else
								{
									$bufferEnd = substr($buffer2, $pos);
									$buffer2 = substr($buffer2, 0, $pos);
								}
							}

							$buffer2 = preg_replace('/[\x00-\x08\x0b-\x0c\x0e-\x1f]/', '', $buffer2);
							$buffer2 = preg_replace('/&(?!(amp;|quot;|#039;|lt;|gt;|#x[\dA-F]+;))/', '&amp;', $buffer2);
							if($bNumTags)
							{
								$buffer2 = preg_replace_callback('/(<[^\s>\/]*[^\s>\d\_\-][\_\-]*\d+[^>\s\/]*)(\s[^>]*>|>|\/>)/is', array('\Bitrix\EsolImportxml\Utils', 'AddTagName'), $buffer2);
								$buffer2 = preg_replace('/(<\/?[^\s>]*[^\s>\d\_\-])[\_\-]*\d+[^>\s]*(\s[^>]*>|>)/is', '$1$2', $buffer2);
							}
							if($bNamespaces) self::ReplaceNS($buffer2);
							foreach($arTags as $tag)
							{
								$buffer2 = preg_replace('/(<'.$tag.'(\s[^>]*>|>))\s+(\S|$)/is', '$1$3', $buffer2);
								$buffer2 = preg_replace('/(<'.$tag.'(\s[^>]*>|>))(?!<\!\[CDATA\[)/Uis', '$1<![CDATA[', $buffer2);
								$buffer2 = preg_replace('/(^|\S)\s+(<\/'.$tag.'>)/is', '$1$2', $buffer2);
								$buffer2 = preg_replace('/(?<!\]\]\>)(<\/'.$tag.'>)/Uis', ']]>$1', $buffer2);
							}
							fwrite($handle2, $buffer2);
						}
					}
					else
					{
						while(!feof($handle)) 
						{
							fwrite($handle2, fgets($handle));
						}
					}
					fclose($handle2);
					fclose($handle);
					
					unlink($strPhysicalFileNameX);
					copy($tmpFile, $strPhysicalFileNameX);
					unlink($tmpFile);
				}
				else
				{
					fclose($handle);
				}
			}
			/*/Remove bad string*/
		}

		if($arFile["WIDTH"] == 0 || $arFile["HEIGHT"] == 0)
		{
			//mock image because we got false from CFile::GetImageSize()
			if(strpos($arFile["type"], "image/") === 0)
			{
				$arFile["type"] = "application/octet-stream";
			}
		}

		if($arFile["type"] == '' || !is_string($arFile["type"]))
		{
			$arFile["type"] = "application/octet-stream";
		}

		/****************************** QUOTA ******************************/
		if (\COption::GetOptionInt("main", "disk_space") > 0)
		{
			\CDiskQuota::updateDiskQuota("file", $arFile["size"], "insert");
		}
		/****************************** QUOTA ******************************/

		$NEW_IMAGE_ID = \CFile::DoInsert(array(
			"HEIGHT" => $arFile["HEIGHT"],
			"WIDTH" => $arFile["WIDTH"],
			"FILE_SIZE" => $arFile["size"],
			"CONTENT_TYPE" => $arFile["type"],
			"SUBDIR" => $arFile["SUBDIR"],
			"FILE_NAME" => $arFile["FILE_NAME"],
			"MODULE_ID" => $arFile["MODULE_ID"],
			"ORIGINAL_NAME" => $arFile["ORIGINAL_NAME"],
			"DESCRIPTION" => isset($arFile["description"])? $arFile["description"]: '',
			"HANDLER_ID" => isset($arFile["HANDLER_ID"])? $arFile["HANDLER_ID"]: '',
			"EXTERNAL_ID" => isset($arFile["external_id"])? $arFile["external_id"]: md5(mt_rand()),
		));

		\CFile::CleanCache($NEW_IMAGE_ID);
		
		if($arFile["del_old"]=='Y' && strpos($strSavePath, static::$moduleId)===0 && isset($arFile["external_id"]) && strlen($arFile["external_id"]) > 0)
		{
			self::DeleteFilesByExtId($arFile["external_id"], $NEW_IMAGE_ID);
		}
		
		return $NEW_IMAGE_ID;
	}
	
	public static function ReplaceNS(&$buffer)
	{
		$buffer = preg_replace('/(<\/?)[^\s>]+:([^>]*>)/is', '$1$2', $buffer);
		$pattern = '/(<[^>]+\s*)xmlns(:[^\s=>]*)?\s*=\s*"[^"]*"(\s[^>]*>|>)/is';
		while(preg_match($pattern, $buffer))
		{
			$buffer = preg_replace($pattern, '$1$3', $buffer);
		}
		$pattern = '/(<[^>]+\s+)[^\s=>]+:([^\s=>]+\s*=\s*"[^"]*"(\s[^>]*>|>))/is';
		while(preg_match($pattern, $buffer))
		{
			$buffer = preg_replace($pattern, '$1$2', $buffer);
		}
	}
	
	public static function AddTagName($m)
	{
		return $m[1].' _tagName_="'.trim($m[1], '<>').'" '.$m[2];
	}
	
	public static function DeleteFilesByExtId($extId, $id='')
	{
		$dbRes = \CFile::GetList(array(), array('EXTERNAL_ID'=>$extId));
		while($arr = $dbRes->Fetch())
		{
			if($arr['ID']!=$id)
			{
				\CFile::Delete($arr['ID']);
			}
		}
	}
	
	public static function CopyFile($FILE_ID, $bRegister = true, $newPath = "")
	{
		global $DB;

		$err_mess = "FILE: ".__FILE__."<br>LINE: ";
		$z = \CFile::GetByID($FILE_ID);
		if($zr = $z->Fetch())
		{
			/****************************** QUOTA ******************************/
			if (\COption::GetOptionInt("main", "disk_space") > 0)
			{
				$quota = new \CDiskQuota();
				if (!$quota->checkDiskQuota($zr))
					return false;
			}
			/****************************** QUOTA ******************************/

			$strNewFile = '';
			$bSaved = false;
			$bExternalStorage = false;
			foreach(GetModuleEvents("main", "OnFileCopy", true) as $arEvent)
			{
				if($bSaved = ExecuteModuleEventEx($arEvent, array(&$zr, $newPath)))
				{
					$bExternalStorage = true;
					break;
				}
			}

			$io = \CBXVirtualIo::GetInstance();

			if(!$bExternalStorage)
			{
				$strDirName = $_SERVER["DOCUMENT_ROOT"]."/".(\COption::GetOptionString("main", "upload_dir", "upload"));
				$strDirName = rtrim(str_replace("//","/",$strDirName), "/");

				$zr["SUBDIR"] = trim($zr["SUBDIR"], "/");
				$zr["FILE_NAME"] = ltrim($zr["FILE_NAME"], "/");

				$strOldFile = $strDirName."/".$zr["SUBDIR"]."/".$zr["FILE_NAME"];

				if(strlen($newPath))
					$strNewFile = $strDirName."/".ltrim($newPath, "/");
				else
				{
					$i = 1;
					while(($strNewFile = $strDirName."/".$zr["SUBDIR"]."/".preg_replace('/(\.[^\.]*)$/', '['.$i.']$1', $zr["FILE_NAME"])) && $io->FileExists($strNewFile) && $i<1000)
					{
						$i++;
					}
				}

				$zr["FILE_NAME"] = bx_basename($strNewFile);
				$zr["SUBDIR"] = mb_substr($strNewFile, mb_strlen($strDirName)+1, -(mb_strlen(bx_basename($strNewFile)) + 1));

				if(strlen($newPath))
					CheckDirPath($strNewFile);

				$bSaved = copy($io->GetPhysicalName($strOldFile), $io->GetPhysicalName($strNewFile));
			}

			if($bSaved)
			{
				if($bRegister)
				{
					$arFields = array(
						"TIMESTAMP_X" => $DB->GetNowFunction(),
						"MODULE_ID" => "'".$DB->ForSql($zr["MODULE_ID"], 50)."'",
						"HEIGHT" => intval($zr["HEIGHT"]),
						"WIDTH" => intval($zr["WIDTH"]),
						"FILE_SIZE" => intval($zr["FILE_SIZE"]),
						"ORIGINAL_NAME" => "'".$DB->ForSql($zr["ORIGINAL_NAME"], 255)."'",
						"DESCRIPTION" => "'".$DB->ForSql($zr["DESCRIPTION"], 255)."'",
						"CONTENT_TYPE" => "'".$DB->ForSql($zr["CONTENT_TYPE"], 255)."'",
						"SUBDIR" => "'".$DB->ForSql($zr["SUBDIR"], 255)."'",
						"FILE_NAME" => "'".$DB->ForSql($zr["FILE_NAME"], 255)."'",
						"HANDLER_ID" => $zr["HANDLER_ID"]? intval($zr["HANDLER_ID"]): "null",
						"EXTERNAL_ID" => $zr["EXTERNAL_ID"] != ""? "'".$DB->ForSql($zr["EXTERNAL_ID"], 50)."'": "null",
					);
					$NEW_FILE_ID = $DB->Insert("b_file",$arFields, $err_mess.__LINE__);

					if (\COption::GetOptionInt("main", "disk_space") > 0)
						\CDiskQuota::updateDiskQuota("file", $zr["FILE_SIZE"], "copy");

					\CFile::CleanCache($NEW_FILE_ID);

					return $NEW_FILE_ID;
				}
				else
				{
					if(!$bExternalStorage)
						return substr($strNewFile, strlen(rtrim($_SERVER["DOCUMENT_ROOT"], "/")));
					else
						return $bSaved;
				}
			}
			else
			{
				return false;
			}
		}
		return 0;
	}
	
	public static function transformName($name, $bForceMD5 = false, $bSkipExt = false)
	{
		//safe filename without path
		$fileName = GetFileName($name);

		$originalName = ($bForceMD5 != true);
		if($originalName)
		{
			//transforming original name:

			//transliteration
			if(\COption::GetOptionString("main", "translit_original_file_name", "N") == "Y")
			{
				$fileName = \CUtil::translit($fileName, LANGUAGE_ID, array("max_len"=>1024, "safe_chars"=>".", "replace_space" => '-'));
			}

			//replace invalid characters
			if(\COption::GetOptionString("main", "convert_original_file_name", "Y") == "Y")
			{
				$io = \CBXVirtualIo::GetInstance();
				$fileName = $io->RandomizeInvalidFilename($fileName);
			}
		}

		//.jpe is not image type on many systems
		if($bSkipExt == false && strtolower(GetFileExtension($fileName)) == "jpe")
		{
			$fileName = mb_substr($fileName, 0, -4).".jpg";
		}

		//double extension vulnerability
		$fileName = RemoveScriptExtension($fileName);

		if(!$originalName)
		{
			//name is md5-generated:
			$fileName = md5(uniqid("", true)).($bSkipExt == true || ($ext = GetFileExtension($fileName)) == ''? '' : ".".$ext);
		}

		return $fileName;
	}

	protected static function validateFile($strFileName, $arFile)
	{
		if($strFileName == '')
			return Loc::getMessage("FILE_BAD_FILENAME");

		$io = \CBXVirtualIo::GetInstance();
		if(!$io->ValidateFilenameString($strFileName))
			return Loc::getMessage("MAIN_BAD_FILENAME1");

		if(strlen($strFileName) > 255)
			return Loc::getMessage("MAIN_BAD_FILENAME_LEN");

		//check .htaccess etc.
		if(IsFileUnsafe($strFileName))
			return Loc::getMessage("FILE_BAD_TYPE");

		//nginx returns octet-stream for .jpg
		if(GetFileNameWithoutExtension($strFileName) == '')
			return Loc::getMessage("FILE_BAD_FILENAME");

		if (\COption::GetOptionInt("main", "disk_space") > 0)
		{
			$quota = new \CDiskQuota();
			if (!$quota->checkDiskQuota($arFile))
				return Loc::getMessage("FILE_BAD_QUOTA");
		}

		return "";
	}
	
	public static function GetFilesByExt($path, $arExt=array(), $checkSubdirs=true)
	{
		$limit = 100;
		$arFiles = array();
		$arDirs = array();
		if(file_exists($path) && ($dh = opendir($path))) 
		{
			while(($file = readdir($dh)) !== false) 
			{
				if($file=='.' || $file=='..') continue;
				if(is_file($path.$file) && (empty($arExt) || preg_match('/\.('.implode('|', $arExt).')$/i', ToLower($file)) || (($arFileData=getimagesize($path.$file)) && preg_match('/\/('.implode('|', $arExt).')$/i', ToLower($arFileData['mime'])))))
				{
					$arFiles[] = $path.$file;
					if(count($arFiles) > $limit) return array();
				}
				elseif(is_dir($path.$file))
				{
					$arDirs[] = $file;
				}
			}
			closedir($dh);
		}
		sort($arFiles);

		//if(!empty($arFiles)) return $arFiles;
		if($checkSubdirs===true || $checkSubdirs>0)
		{
			foreach($arDirs as $file)
			{
				$arFiles = array_merge($arFiles, self::GetFilesByExt($path.$file.'/', $arExt, ($checkSubdirs===true ? $checkSubdirs : $checkSubdirs -1)));
				if(count($arFiles) > $limit) return array();
			}
		}
		return $arFiles;
	}
	
	public static function GetFileSystemEncoding()
	{
		if(!isset(static::$fileSystemEncoding))
		{
			$fileSystemEncoding = strtolower(defined("BX_FILE_SYSTEM_ENCODING") ? BX_FILE_SYSTEM_ENCODING : "");

			if (empty($fileSystemEncoding))
			{
				if (strtoupper(substr(PHP_OS, 0, 3)) === "WIN")
					$fileSystemEncoding =  "windows-1251";
				else
					$fileSystemEncoding = "utf-8";
			}
			static::$fileSystemEncoding = $fileSystemEncoding;
		}
		return static::$fileSystemEncoding;
	}
	
	public static function CorrectEncodingForExtractDir($path)
	{
		$fileSystemEncoding = self::GetFileSystemEncoding();
		$arFiles = array();
		$arDirFiles = array_diff(scandir($path), array('.', '..'));
		foreach($arDirFiles as $file)
		{
			if(preg_match('/[^A-Za-z0-9_\-\.\s]/', $file) && ($fileSystemEncoding!='utf-8' || preg_match('/[^A-Za-z0-9_\-\p{Cyrillic}\.\s]/u', $file)))
			{
				$newfile = \Bitrix\Main\Text\Encoding::convertEncoding($file, $fileSystemEncoding, "cp866");
				$isUtf8 = \CUtil::DetectUTF8($newfile);
				if($isUtf8 && $fileSystemEncoding!='utf-8')
				{
					$newfile = \Bitrix\Main\Text\Encoding::convertEncoding($newfile, 'utf-8', $fileSystemEncoding);
				}
				elseif(!$isUtf8 && $fileSystemEncoding=='utf-8')
				{
					$newfile = \Bitrix\Main\Text\Encoding::convertEncoding($newfile, 'windows-1251', $fileSystemEncoding);
				}
				$newfile = str_replace('?', '', $newfile);
				$res = rename($path.$file, $path.$newfile);
				$file = $newfile;
			}
			if(is_dir($path.$file))
			{
				self::CorrectEncodingForExtractDir($path.$file.'/');
			}
		}
	}
	
	public static function GetDateFormat($m)
	{		
		$format = str_replace('_', ' ', $m[1]);
		$time = time();
		if(preg_match_all('/([jdmyY])([\-+][1-9]\d*)/', $format, $m2))
		{
			foreach($m2[1] as $k=>$key)
			{
				if($key=='j' || $key=='d') $time = mktime((int)date('h', $time), (int)date('i', $time), (int)date('s', $time), (int)date('n', $time), (int)date('j', $time) + (int)$m2[2][$k], (int)date('Y', $time));
				elseif($key=='m') $time = mktime((int)date('h', $time), (int)date('i', $time), (int)date('s', $time), (int)date('n', $time) + (int)$m2[2][$k], (int)date('j', $time), (int)date('Y', $time));
				elseif($key=='y' || $key=='Y') $time = mktime((int)date('h', $time), (int)date('i', $time), (int)date('s', $time), (int)date('n', $time), (int)date('j', $time), (int)date('Y', $time) + (int)$m2[2][$k]);
				$format = str_replace($m2[0][$k], $key, $format);
			}
		}
		if(Loader::includeModule("iblock"))
		{
			return ToLower(\CIBlockFormatProperties::DateFormat($format, $time));
		}
		else return date($format, $time);
	}
	
	public static function MergeCookie(&$arCookies, $arNewCookies)
	{
		if(!is_array($arCookies)) $arCookies = array();
		if(!is_array($arNewCookies)) $arNewCookies = array();
		foreach($arNewCookies as $k=>$v)
		{
			/*if(!isset($arCookies[$k]) || strpos(Tolower($k), 'session')===false)
			{
				$arCookies[$k] = $v;
			}*/
			$arCookies[$k] = $v;
		}
	}
	
	public static function GetNewLocation(&$location, $newLoc)
	{
		$arUrl = parse_url($location);
		$newLoc = trim($newLoc);
		$location = $newLoc;
		if(strlen($newLoc) > 0 && stripos($newLoc, 'http')!==0)
		{
			if(strpos($newLoc, '/')===0)
			{
				$location = $arUrl['scheme'].'://'.$arUrl['host'].$newLoc;
			}
			else
			{
				if($newLoc=='.') $newLoc = '';
				$dir = preg_replace('/[\/]+/', '/', preg_replace('/(^|\/)[^\/]*$/', '', $arUrl['path']).'/');
				$location = $arUrl['scheme'].'://'.$arUrl['host'].$dir.$newLoc;
			}
		}
	}
	
	public static function MakeFileArray($path, $maxTime = 0, $arCookies = array())
	{
		$isLoop = !empty($arCookies);
		$arExt = array('xml', 'yml', 'json', 'txt');
		if(is_array($path))
		{
			$arFile = $path;
			$temp_path = \CFile::GetTempName('', \Bitrix\Main\IO\Path::convertLogicalToPhysical($arFile["name"]));
			CheckDirPath($temp_path);
			if(!copy($arFile["tmp_name"], $temp_path)
				&& !move_uploaded_file($arFile["tmp_name"], $temp_path))
			{
				return false;
			}
			$arFile = \CFile::MakeFileArray($temp_path);
		}
		else
		{
			$path = $pathOrig = trim($path);
			$arHeaders = array('User-Agent' => self::GetUserAgent());
			if(preg_match('/^\{.*\}$/s', $path))
			{
				$arParams = \CUtil::JsObjectToPhp($path);
				if(is_array($arParams['HEADERS'])) $arHeaders = array_merge($arHeaders, $arParams['HEADERS']);
				$ctHeaderKeys = preg_grep('/content\-type/i', array_keys($arHeaders));
				if(count($ctHeaderKeys) > 0)
				{
					$ctHeaderKey = current($ctHeaderKeys);
					$contentType = $arHeaders[$ctHeaderKey];
					if(ToLower($contentType)=='application/json')
					{
						if(function_exists('json_encode')) $arParams['VARS'] = json_encode($arParams['VARS']);
						else $arParams['VARS'] = '{'.implode(',', array_map(array(__CLASS__, 'Vars2Json'), array_keys($arParams['VARS']), array_values($arParams['VARS']))).'}';
					}
				}
				if(isset($arParams['FILELINK']))
				{
					$path = $arParams['FILELINK'];

					if(!empty($arParams['VARS']) && $arParams['PAGEAUTH'])
					{
						$arUrl = parse_url($arParams['PAGEAUTH']);
						$className = self::GetVendorClassName($arUrl['host']);
						if(is_callable(array($className, 'GetDownloadFile')) && ($arDFile = call_user_func(array($className, 'GetDownloadFile'), $arParams, $maxTime)))
						{
							return self::MakeFileArray($arDFile['tmp_name'], $maxTime);
						}
						elseif(is_callable(array($className, 'GetParamsForDownload')) && $className::GetParamsForDownload($arParams, $arHeaders))
						{
							
						}
						elseif(is_callable(array($className, 'GetDownloadPath')) && $className::GetDownloadPath($path, $arParams, $arHeaders, $arCookies))
						{
							
						}
						
						$redirectCount = 0;
						$location = trim($arParams['PAGEAUTH']);
						while(strlen($location)>0 && $redirectCount<=5)
						{
							$client = self::GetHttpClient(array('disableSslVerification'=>true, 'redirect'=>false), $arHeaders, $arCookies, $location);
							$res = $client->get($location);
							static::MergeCookie($arCookies, $client->getCookies()->toArray());
							$arHeaders['Referer'] = $location;
							$location = $client->getHeaders()->get("Location");
							$status = $client->getStatus();
							$ctype = $client->getHeaders()->get("Content-Type");
							if(!in_array($status, array(301, 302, 303))) $location = '';
							$redirectCount++;
						}
						$needEncoding = $siteEncoding = self::getSiteEncoding();
						if(preg_match('/charset=(.*)(;|$)/', $ctype, $m) && strlen(trim($m[1])) > 0)
						{
							$needEncoding = ToLower(trim($m[1]));
						}
						if(is_array($arParams['VARS']))
						{
							if(strlen(trim($v)) > 0 && $needEncoding!=$siteEncoding)
							{
								$arParams['VARS'][$k] = \Bitrix\Main\Text\Encoding::convertEncoding($v, $siteEncoding, $needEncoding);
							}
							foreach($arParams['VARS'] as $k=>$v)
							{
								if(strlen(trim($v))==0 
									&& preg_match('/<input[^>]*name=[\'"]'.addcslashes($k, '-').'[\'"][^>]*>/Uis', $res, $m1)
									&& preg_match('/value=[\'"]([^\'"]*)[\'"]/Uis', $m1[0], $m2))
								{
										$arParams['VARS'][$k] = html_entity_decode($m2[1], ENT_COMPAT, $siteEncoding);
								}
							}
						}
						
						$redirectCount = 0;
						$location = trim($arParams['POSTPAGEAUTH'] ? $arParams['POSTPAGEAUTH'] : $arParams['PAGEAUTH']);
						
						if(in_array($status, array(400, 405)) && array_key_exists('client_secret', $arParams['VARS']))
						{
							$client = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>false, 'redirect'=>false));
							$res = trim($client->post($location, array('grant_type'=>'password')));
							if(strpos($res, '{')===0 && ($arAnswer = \CUtil::JsObjectToPhp($res)) && is_array($arAnswer) && 
								((array_key_exists('error', $arAnswer) && !empty($arAnswer['error']))
								|| (array_key_exists('message', $arAnswer) && !empty($arAnswer['message']))))
							{
								if(!array_key_exists('grant_type', $arParams['VARS']) || strlen($arParams['VARS']['grant_type'])==0) $arParams['VARS']['grant_type'] = 'password';
								$client = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>false, 'redirect'=>false));
								$arAnswer = \CUtil::JsObjectToPhp(trim($client->post($location, $arParams['VARS'])));
								if(is_array($arAnswer) && isset($arAnswer['token_type']) && isset($arAnswer['access_token']))
								{
									$arHeaders['Authorization'] = $arAnswer['token_type'].' '.$arAnswer['access_token'];
									$location = '';
								}
							}
						}

						while(strlen($location)>0 && $redirectCount<=5)
						{
							$client = self::GetHttpClient(array('disableSslVerification'=>true, 'redirect'=>false), $arHeaders, $arCookies, $location);
							$res = $client->post($location, $arParams['VARS']);
							$status = $client->getStatus();
							if($status==404)
							{
								$client = self::GetHttpClient(array('disableSslVerification'=>true, 'redirect'=>false), $arHeaders, $arCookies, $location);
								$res = $client->get($location);
								$status = $client->getStatus();
							}
							static::MergeCookie($arCookies, $client->getCookies()->toArray());
							$arHeaders['Referer'] = $location;
							$location = $client->getHeaders()->get("Location");
							if(!in_array($status, array(301, 302, 303))) $location = '';
							$redirectCount++;
						}
					}
					
					if(strlen($arParams['HANDLER_FOR_LINK_BASE64']) > 0) $handler = base64_decode(trim($arParams['HANDLER_FOR_LINK_BASE64']));
					else $handler = trim($arParams['HANDLER_FOR_LINK']);
					if(strlen($handler) > 0)
					{
						$val = '';
						if($path)
						{
							$client = self::GetHttpClient(array('disableSslVerification'=>true), $arHeaders, $arCookies, $path);
							$val = $client->get($path);
						}
						$res = self::ExecuteFilterExpression($val, $handler, '', $arCookies);
						if(is_array($res))
						{
							if(isset($res['PATH'])) $path = $res['PATH'];
							if(isset($res['COOKIES']) && is_array($res['COOKIES'])) $arCookies = array_merge($arCookies, $res['COOKIES']);
						}
						else
						{
							$path = $res;
						}
					}
				}
			}
			else
			{
				$arUrl = parse_url($path);
				$className = self::GetVendorClassName($arUrl['host']);
				if(is_callable(array($className, 'GetDownloadPath')) && $className::GetDownloadPath($path, $arParams, $arHeaders, $arCookies))
				{
					
				}
			}
			
			if(self::PathContianApiPages($path))
			{
				$path = self::PathReplaceApiPages($path);
			}
			$path = preg_replace_callback('/\{DATE_(\S*)\}/', array('\Bitrix\EsolImportxml\Utils', 'GetDateFormat'), $path);
			if(preg_match('/\{MAX_TIME=(\d+)\}/', $path, $m))
			{
				$maxTime = $m[1];
				$path = str_replace($m[0], '', $path);
			}
			if(!$maxTime) $maxTime = min(intval(ini_get('max_execution_time')) - 5, 1800);
			if(ini_get('max_execution_time')==='0') $maxTime = 300;
			elseif($maxTime<=0) $maxTime = 50;
			$cloud = new \Bitrix\EsolImportxml\Cloud();
			if($service = $cloud->GetService($path))
			{
				$arFile = $cloud->MakeFileArray($service, $path);
			}
			elseif(($maxTime >= 5 || !empty($arCookies)) && preg_match("#^(http[s]?)://#", $path) && class_exists('\Bitrix\Main\Web\HttpClient'))
			{
				if(preg_match('/^(https?:\/\/)(.*):(.*)@(.*\/.*)$/Uis', $path, $m))
				{
					$arHeaders['Authorization'] = 'Basic '.base64_encode($m[2].':'.$m[3]);
					$path = $m[1].$m[4];
				}
				$path = rawurldecode($path);
				$arUrl = parse_url($path);
				//Cyrillic domain
				if(preg_match('/[^A-Za-z0-9\-\.]/', $arUrl['host']))
				{
					if(!class_exists('idna_convert')) require_once(dirname(__FILE__).'/idna_convert.class.php');
					if(class_exists('idna_convert'))
					{
						$idn = new \idna_convert();
						$oldHost = $arUrl['host'];
						if(!\CUtil::DetectUTF8($oldHost)) $oldHost = \Bitrix\EsolImportxml\Utils::Win1251Utf8($oldHost);
						$path = str_replace($arUrl['host'], $idn->encode($oldHost), $path);
					}
				}

				$temp_path = '';
				$bExternalStorage = false;
				/*foreach(GetModuleEvents("main", "OnMakeFileArray", true) as $arEvent)
				{
					if(ExecuteModuleEventEx($arEvent, array($path, &$temp_path)))
					{
						$bExternalStorage = true;
						break;
					}
				}*/
				
				if(!$bExternalStorage)
				{
					$urlComponents = parse_url($path);
					$postBody = '';
					if(isset($urlComponents['fragment']) && stripos($urlComponents['fragment'], 'postbody=')===0)
					{
						$path = mb_substr($path, 0, -mb_strlen($urlComponents['fragment'])-1);
						$postBody = mb_substr($urlComponents['fragment'], 9);
					}
					if ($urlComponents && strlen($urlComponents["path"]) > 0) $baseName = bx_basename($urlComponents["path"]);
					else $baseName = bx_basename($path);
					$basename = preg_replace('/\?.*$/', '', $baseName);
					if(preg_match('/^[_+=!?]*\./', $baseName) || strlen(trim($baseName))==0) $baseName = 'f'.$baseName;
					$temp_path2 = \CFile::GetTempName('', $baseName);
					$temp_path = \Bitrix\Main\IO\Path::convertLogicalToPhysical($temp_path2);
					
					if(!\CUtil::DetectUTF8($path)) $path = self::Win1251Utf8($path);
					$path = preg_replace_callback('/[^:@\/?=&#%!$,\-\.\+\{\}\[\]]+/', array(__CLASS__, 'UrlEncodeCallback'), $path);

					$ob = self::GetHttpClient(array('socketTimeout'=>$maxTime, 'streamTimeout'=>$maxTime, 'disableSslVerification'=>true), $arHeaders, $arCookies, $path);
					if(strlen($postBody) > 0)
					{
						if(strpos($postBody, '<?xml')!==false) $ob->setHeader('content-type', 'application/xml');
						if($dRes = $ob->post($path, $postBody))
						{
							$dir = \Bitrix\Main\IO\Path::getDirectory($temp_path2);
							\Bitrix\Main\IO\Directory::createDirectory($dir);
							file_put_contents($temp_path, $dRes);
						}
					}
					else
					{
						$dRes = $ob->download($path, $temp_path2);
					}
					if(($dRes && $ob->getStatus()!=404) || in_array($ob->getStatus(), array(301, 302, 303)))
					{
						if($ob->getStatus()!=200)
						{
							$ob = self::GetHttpClient(array('socketTimeout'=>$maxTime, 'streamTimeout'=>$maxTime, 'disableSslVerification'=>true, 'redirect'=>false), $arHeaders, array(), $path);
							$ob->get($path);
							$loop = 0;
							while(in_array($ob->getStatus(), array(301, 302, 303)) && ++$loop<=5)
							{
								self::GetNewLocation($path, $ob->getHeaders()->get('location'));
								$arCookies = $ob->getCookies()->toArray();
								$ob = self::GetHttpClient(array('socketTimeout'=>15, 'streamTimeout'=>15, 'disableSslVerification'=>true, 'redirect'=>false), $arHeaders, $arCookies, $path);
								$ob->download($path, $temp_path2);
							}
						}

						if(!$isLoop 
							&& strpos($ob->getHeaders()->get("content-type"), 'text/html')!==false 
							&& ($content = file_get_contents($temp_path2, false, null, 0, 4096))
							&& (stripos($content, '<html>')!==false || stripos($content, '<script')!==false)
							&& preg_match('/document\.cookie\s*=\s*["\']([^"\']+)["\']/Uis', $content, $cm))
						{
							$arNewCookies = array();
							foreach(explode('&', $cm[1]) as $newCookie)
							{
								$arNewCookie = explode('=', $newCookie);
								$arNewCookies[$arNewCookie[0]] = current(explode(';', $arNewCookie[1]));
							}
							return self::MakeFileArray($path, $maxTime, $arNewCookies);
						}
						
						$i = 0;
						$handle = fopen($temp_path, 'r');
						while(!($str = trim(fgets($handle, 1024))) && !feof($handle) && ++$i<10) {}
						fclose($handle);
						$isXmlHeader = (bool)(stripos(trim($str), '<?xml')!==false);
						$isJsonHeader = (bool)(in_array(substr(trim($str), 0, 1), array('[', '{')));

						$realFileName = '';
						$hcd = $ob->getHeaders()->get('content-disposition');
						$hct = $ob->getHeaders()->get('content-type');
						$hce = $ob->getHeaders()->get('content-encoding');
						$ext = ToLower(self::GetFileExtension($temp_path));
						if($hcd && stripos($hcd, 'filename=')!==false)
						{
							$hcdParts = preg_grep('/filename=/i', array_map('trim', explode(';', $hcd)));
							if(count($hcdParts) > 0)
							{
								$hcdParts = explode('=', current($hcdParts));
								$fn = end(explode('/', trim(end($hcdParts), '"\' ')));
								if(strlen($fn) > 0 && strpos($temp_path, $fn)===false)
								{
									if($hce=='gzip')
									{
										$arTmpFile = \CFile::MakeFileArray($temp_path);
										if(in_array($arTmpFile['type'], array('application/gzip', 'application/x-gzip')) && !preg_match('/\.gz$/i', $fn))
										{
											$fn = $fn.'.gz';
										}
									}
									$realFileName = $fn;
									//function rename is problem for temp folder
									/*$old_temp_path = $temp_path;
									$temp_path = preg_replace('/\/[^\/]+$/', '/'.$fn, $old_temp_path);
									rename($old_temp_path, $temp_path);*/
								}
							}
						}
						elseif(!in_array($ext, array('xml', 'yml')) &&
							((strpos(ToLower($path), 'xml')!==false && !preg_match('/\.(zip|tag|gz|rar)/', ToLower($path)) && !$isJsonHeader) || (stripos($hct, 'text/xml')!==false) || (stripos($hct, 'application/xml')!==false) || $isXmlHeader))
						{
							//function rename is problem for temp folder
							/*$old_temp_path = $temp_path;
							//$temp_path = $temp_path.'.xml';
							$temp_path2 = \CFile::GetTempName('', bx_basename($temp_path2).'.xml');
							$dir = \Bitrix\Main\IO\Path::getDirectory($temp_path2);
							\Bitrix\Main\IO\Directory::createDirectory($dir);
							$temp_path = \Bitrix\Main\IO\Path::convertLogicalToPhysical($temp_path2);
							rename($old_temp_path, $temp_path);*/
							$realFileName = bx_basename($temp_path2).'.xml';
						}
						elseif((stripos($hct, 'application/json')!==false || $isJsonHeader) && !in_array(ToLower(self::GetFileExtension($temp_path)), array('xml', 'yml', 'json')))
						{
							//function rename is problem for temp folder
							/*$old_temp_path = $temp_path;
							$temp_path2 = \CFile::GetTempName('', bx_basename($temp_path2).'.json');
							$dir = \Bitrix\Main\IO\Path::getDirectory($temp_path2);
							\Bitrix\Main\IO\Directory::createDirectory($dir);
							$temp_path = \Bitrix\Main\IO\Path::convertLogicalToPhysical($temp_path2);
							rename($old_temp_path, $temp_path);*/
							$realFileName = bx_basename($temp_path2).'.json';
						}
						elseif(self::PathContainsMask(end(explode('/', current(explode('?', $pathOrig))))))
						{
							//http by mask
							$path1 = current(explode('?', $pathOrig));
							$path2 = preg_replace('/\/[^\/]+$/', '/', $path1);
							$path3 = preg_replace('/^.*\/([^\/]+)$/', '$1', $path1);
							$ob = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>$maxTime, 'streamTimeout'=>$maxTime, 'disableSslVerification'=>true));
							$ob->setCookies($arCookies);
							foreach($arHeaders as $hk=>$hv) $ob->setHeader($hk, $hv);
							$content = urldecode($ob->get($path2));
							if(preg_match_all(self::GetPatternForRegexp('href="'.$path3.'"'), $content, $m))
							{
								$arFiles = array();
								foreach($m[0] as $fn)
								{
									$arFiles[] = $path2.trim(substr($fn, 5), '"');
								}
								rsort($arFiles);
								$path1 = current($arFiles);
								return self::MakeFileArray($path1, $maxTime);
							}
						}
						$arFile = \CFile::MakeFileArray($temp_path);
						if(!$arFile) $arFile = \CFile::MakeFileArray(\Bitrix\Main\IO\Path::convertLogicalToPhysical($temp_path));
						if(strlen($realFileName) > 0) $arFile['name'] = $realFileName;
						
						if(!in_array($ext, array('xml', 'yml')) && strpos($arFile["type"], 'xml')===false)
						{
							$c = file_get_contents($arFile['tmp_name'], false, null, 0, 1024);
							if(strpos($c, "\xEF\xBB\xBF")===0) $c = substr($c, 3);
							$c = ToLower(trim($c));
							if(strpos($c, '<yml_catalog')===0)
							{
								$arFile["type"] = 'text/xml';
								$arFile["name"] .= '.yml';
							}
						}
					}
					elseif(($arFileTmp = \CFile::MakeFileArray($temp_path)) && $arFileTmp['size'] > 0 && strpos($arFileTmp['type'], 'xml')!==false && strpos(file_get_contents($arFileTmp['tmp_name'], false, null, 0, 4096), '<?xml')!==false)
					{
						$arFile = $arFileTmp;
					}
				}
				elseif($temp_path)
				{
					$arFile = \CFile::MakeFileArray($temp_path);
				}
				
				if(strlen($arFile["type"])<=0)
					$arFile["type"] = "unknown";
			}
			elseif(preg_match('/ftp(s)?:\/\//', $path))
			{
				$sftp = new \Bitrix\EsolImportxml\Sftp();
				$arFile = $sftp->MakeFileArray($path, array('TIMEOUT'=>max(20, $maxTime)));
				if(is_array($arFile) && $arFile['tmp_name'])
				{
					$handle = fopen($arFile['tmp_name'], 'r');
					while(!($str = trim(fgets($handle, 1024))) && !feof($handle) && ++$i<10) {}
					fclose($handle);
					$isXmlHeader = (bool)(stripos(trim($str), '<?xml')!==false);
					$isJsonHeader = (bool)(in_array(substr(trim($str), 0, 1), array('[', '{')));
					$ext = ToLower(self::GetFileExtension($arFile['tmp_name']));
					if(!in_array($ext, array('xml', 'yml')) &&
							((stripos($arFile['type'], 'text/xml')!==false) || (stripos($arFile['type'], 'application/xml')!==false) || $isXmlHeader))
					{
						$arFile['name'] = $arFile['name'].'.xml';
					}
				}
			}
			else
			{
				$path2 = preg_replace('/#.*$/', '', $path);
				if(self::PathContainsMask($path2) && !file_exists($path2) && !file_exists($_SERVER['DOCUMENT_ROOT'].$path2))
				{
					$arTmpFiles = self::GetFilesByMask($path2);
					if(count($arTmpFiles) > 0)
					{
						$path2 = current($arTmpFiles);
					}
				}
				$arFile = \CFile::MakeFileArray($path2);
			}
		}
		
		$ext = ToLower(self::GetFileExtension($arFile['tmp_name']));
		$ext2 = ToLower(self::GetFileExtension($arFile['name']));
		if(strlen($ext) == 0 || in_array($ext2, $arExt)) $ext = $ext2;

		if(in_array($arFile['type'], array('application/zip', 'application/x-zip-compressed', 'application/gzip', 'application/x-gzip', 'application/x-tar', 'application/rar', 'application/x-rar', 'application/x-rar-compressed', 'application/octet-stream')) && !in_array($ext, $arExt))
		{
			$archiveFn = $arFile['tmp_name'];
			$tmpsubdir = dirname($archiveFn).'/zip/';
			if(file_exists($tmpsubdir)) self::DeleteDirFiles($tmpsubdir);
			CheckDirPath($tmpsubdir);
			if(mb_substr($ext, -3)=='.gz' && $ext!='tar.gz' && function_exists('gzopen'))
			{
				$handle1 = gzopen($archiveFn, 'rb');
				$handle2 = fopen($tmpsubdir.mb_substr(basename(ToLower(mb_substr($archiveFn, -3)=='.gz') ? $archiveFn : $arFile['name']), 0, -3), 'wb');
				while(!gzeof($handle1)) {
					fwrite($handle2, gzread($handle1, 4096));
				}
				fclose($handle2);
				gzclose($handle1);
			}
			elseif($ext=='rar' && class_exists('\RarArchive'))
			{
				$rar = \RarArchive::open($archiveFn);
				$entries = $rar->getEntries();
				foreach($entries as $entry)
				{
					$entry->extract($tmpsubdir, $tmpsubdir.$entry->getName());
				}
				$rar->close();
			}
			elseif($ext=='zip' && filesize($archiveFn) > 5*1024*1024 && class_exists('\ZipArchive') && ($zipObj = new \ZipArchive) && $zipObj->open($archiveFn)===true && $zipObj->numFiles > 0)
			{
				$zipObj->extractTo($tmpsubdir);
				for($i=0; $i<$zipObj->numFiles; $i++)
				{
					$zipPath = $zipObj->getNameIndex($i);
					if(!file_exists($tmpsubdir.$zipPath))
					{
						CheckDirPath($tmpsubdir.$zipPath);
						copy("zip://".$archiveFn."#".$zipPath, $tmpsubdir.$zipPath);
					}
				}
				$zipObj->close();
			}
			else
			{
				$type = (in_array($ext, array('tar.gz', 'tgz')) ? 'TAR.GZ' : 'ZIP');
				$zipObj = \CBXArchive::GetArchive($archiveFn, $type);
				$zipObj->Unpack($tmpsubdir);
				if(count(array_diff(scandir($tmpsubdir), array('.', '..')))==0 && function_exists('exec'))
				{
					@exec('unzip "'.$archiveFn.'" -d '.$tmpsubdir);
				}
				elseif($arFile['type']=='application/zip') self::CorrectEncodingForExtractDir($tmpsubdir);
			}
			
			$arFile = array();
			if(!is_array($path)) $urlComponents = parse_url($path);
			else $urlComponents = array();
			if(isset($urlComponents['fragment']) && strlen($urlComponents['fragment']) > 0 && !preg_match('/^\s*page=(\d+)\s*$/', $urlComponents['fragment']))
			{
				$fn = $tmpsubdir.ltrim($urlComponents['fragment'], '/');
				$arFiles = array($fn);
				if((strpos($fn, '*')!==false || (strpos($fn, '{')!==false && strpos($fn, '}')!==false)) && !file_exists($fn))
				{
					$arFiles = glob($fn, GLOB_BRACE);
				}
			}
			else
			{
				$arFiles = self::GetFilesByExt($tmpsubdir, $arExt);
				if(count($arFiles) > 1)
				{
					$arNewFiles = array();
					foreach($arExt as $ext)
					{
						$arNewFiles = array_merge($arNewFiles, preg_grep('/\.'.$ext.'$/i', $arFiles));
					}
					$arFiles = $arNewFiles;
				}
			}

			if(count($arFiles) > 0)
			{
				$tmpfile = current($arFiles);
				$temp_path = \CFile::GetTempName('', bx_basename($tmpfile));
				$dir = \Bitrix\Main\IO\Path::getDirectory($temp_path);
				\Bitrix\Main\IO\Directory::createDirectory($dir);
				copy($tmpfile, $temp_path);
				$arFile = \CFile::MakeFileArray($temp_path);
			}
			self::DeleteDirFiles($tmpsubdir);
		}
		
		self::CheckJsonFile($arFile, $hct);
		static::$lastCookies = (is_array($arCookies) ? $arCookies : array());
		static::$lastUAgent = (is_array($arHeaders) && isset($arHeaders['User-Agent']) ? $arHeaders['User-Agent'] : '');
		static::$lastFileHash = (isset($arFile['tmp_name']) && file_exists($arFile['tmp_name']) ? md5_file($arFile['tmp_name']) : '');
		return $arFile;
	}
	
	public static function GetVendorClassName($host)
	{
		$host = ToLower(trim($host));
		if(mb_strpos($host, 'www.')===0) $host = mb_substr($host, 4);
		$fn = dirname(__FILE__).'/vendors/'.$host.'.php';
		if(file_exists($fn)) include_once($fn);
		$className = '\IX\\'.ToUpper(substr($host, 0, 1)).ToLower(str_replace(array('.', '-'), '', substr($host, 1)));
		return $className;
	}
	
	public static function DeleteDirFiles($tmpsubdir)
	{
		if(strpos($_SERVER['DOCUMENT_ROOT'], $tmpsubdir)===0)
		{
			DeleteDirFilesEx(substr($tmpsubdir, strlen($_SERVER['DOCUMENT_ROOT'])));
		}
		else
		{
			$tmpsubdir = rtrim($tmpsubdir, '/');
			$arFiles = scandir($tmpsubdir);
			foreach($arFiles as $file)
			{
				if(in_array($file, array('.', '..'))) continue;
				if(is_dir($tmpsubdir.'/'.$file)) self::DeleteDirFiles($tmpsubdir.'/'.$file);
				else unlink($tmpsubdir.'/'.$file);
			}
			rmdir($tmpsubdir);
		}
	}
	
	public static function SetLastFileParams(&$SETTINGS_DEFAULT)
	{
		$SETTINGS_DEFAULT["LAST_COOKIES"] = static::$lastCookies;
		$SETTINGS_DEFAULT["LAST_UAGENT"] = static::$lastUAgent;
		$SETTINGS_DEFAULT["FILE_HASH"] = static::$lastFileHash;
	}
	
	public static function CheckJsonFile(&$arFile, $hct='')
	{
		$ext = ToLower(self::GetFileExtension($arFile['tmp_name']));
		$ext2 = ToLower(self::GetFileExtension($arFile['name']));
		if($ext=='txt' || $ext2=='txt')
		{
			$handle = fopen($arFile['tmp_name'], 'r');
			while(!($str = trim(fgets($handle, 1024))) && !feof($handle) && ++$i<10) {}
			fclose($handle);
			$isJsonHeader = (bool)(in_array(substr(trim($str), 0, 1), array('[', '{')));
			if($isJsonHeader) $ext = 'json';
		}
		if($ext=='json' || $ext2=='json')
		{
			$tempPath = \CFile::GetTempName('', \Bitrix\Main\IO\Path::convertLogicalToPhysical($arFile['name']).'.xml');
			$dir = \Bitrix\Main\IO\Path::getDirectory($tempPath);
			\Bitrix\Main\IO\Directory::createDirectory($dir);
			$j2x = new \Bitrix\EsolImportxml\Json2Xml();
			$j2x->Convert($arFile['tmp_name'], $tempPath, $hct);
			$arFile = \CFile::MakeFileArray($tempPath);
		}
	}
	
	public static function GetNextImportFile($path, $page, $oldFile='', $pid='')
	{
		$path = trim($path);
		$arParams = array();
		if(preg_match('/^\{.*\}$/s', $path))
		{
			$arParams = \CUtil::JsObjectToPhp($path);
			if(isset($arParams['FILELINK']))
			{
				$path = $arParams['FILELINK'];
			}
		}
		if(self::PathContianApiPages($path))
		{
			self::$apiPage = $page;
			$path = self::PathReplaceApiPages($path, $page, $oldFile);
			if(is_array($arParams) && isset($arParams['FILELINK']))
			{
				$arParams['FILELINK'] = $path;
				$path = \CUtil::PHPToJSObject($arParams);
			}
			$arFile = self::MakeFileArray($path);
			if($arFile['name'])
			{
				if(strlen($oldFile) > 0 && file_exists($_SERVER['DOCUMENT_ROOT'].$oldFile) && filesize($_SERVER['DOCUMENT_ROOT'].$oldFile)==filesize($arFile['tmp_name']) && md5_file($_SERVER['DOCUMENT_ROOT'].$oldFile)==md5_file($arFile['tmp_name'])) return false;
				if(strpos($arFile['name'], '.')===false) $arFile['name'] .= '.xml';
				if(strlen($pid) > 0) $arFile['external_id'] = 'esol_importxml_'.$pid;
				$arFile['del_old'] = 'Y';
				$loop = 0;
				while($loop < 10 && !($fid = \Bitrix\EsolImportxml\Utils::SaveFile($arFile, static::$moduleId))){$loop++;}
				if($fid > 0) return $fid;
				else return false;
			}
		}

		return false;
	}
	
	public static function PathContianApiPages($path)
	{
		foreach(self::$apiPageParams as $pName)
		{
			if(preg_match('/\{'.$pName.'\}/', $path)) return true;
		}
		return self::IsApiService($path);
	}
	
	public static function IsApiService($path)
	{
		$arUrl = parse_url($path);
		if(in_array($arUrl['host'], array('ads-api.ru', 'atekwater.ru'))
			|| ($arUrl['host']=='b2b.hogart.ru' && preg_match('/(scrollId|product\-scroll)/', $arUrl['query']))
			|| self::IsWsdl($path)) return true;
		return false;
	}
	
	public static function IsWsdl($path)
	{
		if(stripos($path, 'wsdl')!==false && class_exists('\SoapClient'))
		{
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>15, 'disableSslVerification'=>true));
			$client->setHeader('User-Agent', self::GetUserAgent());
			$res = $client->get($path);
			if(stripos($res, '<wsdl:definitions')!==false || stripos($res, '<definitions')!==false)
			{
				return true;
			}
		}
		return false;
	}
	
	public static function PathReplaceApiPages($path, $page=1, $oldFile='')
	{
		//$path = str_replace('{'.self::$apiPageParams['PAGE'].'}', $page, $path);
		if(preg_match('/\{'.self::$apiPageParams['PAGE'].'\}/', $path, $m)){$path = str_replace($m[0], $page + (int)$m[1], $path);}
		if(preg_match('/\{'.self::$apiPageParams['OFFSET'].'\}/', $path, $m)){$path = str_replace($m[0], ($page - 1)*(int)$m[1], $path);}
		$arUrl = parse_url($path);
		if($arUrl['host']=='ads-api.ru')
		{
			$arGet = array_combine(array_map(array(__CLASS__, 'GetValBeforeEq'), explode('&', $arUrl['query'])), array_map(array(__CLASS__, 'GetValAfterEq'), explode('&', $arUrl['query'])));
			$arGet['sort'] = 'asc';
			if($page > 1 && strlen($oldFile) > 0 && file_exists($_SERVER['DOCUMENT_ROOT'].$oldFile)) 
			{
				$arXml = simplexml_load_file($_SERVER['DOCUMENT_ROOT'].$oldFile);
				$arTime = (is_callable(array($arXml, 'xpath')) ? $arXml->xpath('data/item/time') : false);
				$time1 = $time2 = '';
				if(is_array($arTime) && count($arTime) > 1)
				{
					$time1 = (string)array_shift($arTime);
					$time2 = (string)array_pop($arTime);
					$arGet['date1'] = $time2;
				}
				if($time1 == $time2 || strlen($time2)==0) return '';
				sleep(5);
			}
			$arUrl['query'] = implode('&', array_map(array(__CLASS__, 'KeyEqVal'), array_keys($arGet), array_values($arGet)));
			$path = $arUrl['scheme'].'://'.$arUrl['host'].$arUrl['path'].'?'.$arUrl['query'];
		}
		elseif($arUrl['host']=='b2b.hogart.ru')
		{
			if(preg_match('/(scrollId|product\-scroll)/', $arUrl['query']))
			{
				$arGet = array_combine(array_map(array(__CLASS__, 'GetValBeforeEq'), explode('&', $arUrl['query'])), array_map(array(__CLASS__, 'GetValAfterEq'), explode('&', $arUrl['query'])));
				if(strlen($oldFile) > 0 && file_exists($_SERVER['DOCUMENT_ROOT'].$oldFile)) 
				{
					$arXml = simplexml_load_file($_SERVER['DOCUMENT_ROOT'].$oldFile);
					$scrollid = (is_object($arXml) && isset($arXml->scrollid) ? (string)$arXml->scrollid : false);
					if(is_array($scrollid)) $scrollid = current($scrollid);
					if($scrollid) $arGet['scrollId'] = $scrollid;
				}
				$arUrl['query'] = implode('&', array_map(array(__CLASS__, 'KeyEqVal'), array_keys($arGet), array_values($arGet)));
				$path = $arUrl['scheme'].'://'.$arUrl['host'].$arUrl['path'].'?'.$arUrl['query'];
			}
			else
			{
				if(strlen($oldFile) > 0 && file_exists($_SERVER['DOCUMENT_ROOT'].$oldFile)) 
				{
					$arXml = simplexml_load_file($_SERVER['DOCUMENT_ROOT'].$oldFile);
					$pageCount = false;
					if(is_object($arXml))
					{
						if(isset($arXml->meta->pageCount)) $pageCount = (string)$arXml->meta->pageCount;
						if(isset($arXml->meta->pagecount)) $pageCount = (string)$arXml->meta->pagecount;
					}
					if($pageCount && $page > $pageCount) return false;
				}
			}
		}
		elseif(self::IsWsdl($path))
		{
			if($page > 1) return '';
			//$path = 'https://API_KEY|API_LOGIN:PASSWORD@api.merlion.com/re/mlservice3?wsdl#method(params)';
			$wsdl_url = $arUrl['scheme'].'://'.$arUrl['host'].($arUrl['port'] ? ':'.$arUrl['port'] : '').$arUrl['path'].'?'.$arUrl['query'];
			$arMethodParams = array();
			$isParamNames = false;
			/*if(preg_match('/\((.*)\)/Uis', $arUrl['fragment'], $m))
			{
				$arUrl['fragment'] = str_replace($m[0], '', $arUrl['fragment']);
				foreach(explode(',', $m[1]) as $v)
				{
					$v = trim($v);
					if(strpos($v, '=')!==false)
					{
						$arMethodParams[explode('=', $v)[0]] = explode('=', $v, 2)[1];
						$isParamNames = true;
					}
					else $arMethodParams[] = $v;
				}
			}*/
			if(preg_match('/\((.*)\)/Uis', $arUrl['fragment'], $m))
			{
				$arUrl['fragment'] = str_replace($m[0], '', $arUrl['fragment']);
				$strParams = $m[1];
				$arVars = array();
				$j = 1;
				while(preg_match_all('/\[([^\[\]]*)\]/', $strParams, $m2))
				{
					foreach($m2[1] as $k2=>$v2)
					{
						$tmpVars = array();
						foreach(explode(',', $v2) as $v)
						{
							$v = trim($v);
							if(strpos($v, '=')!==false)
							{
								list($k,$v) = explode('=', $v, 2);
								if(preg_match('/^\$(\d+)$/', $v, $m3) && isset($arVars[$m3[1]])) $v = $arVars[$m3[1]];
								$tmpVars[$k] = $v;
							}
							else 
							{
								if(preg_match('/^\$(\d+)$/', $v, $m3) && isset($arVars[$m3[1]])) $v = $arVars[$m3[1]];
								$tmpVars[] = $v;
							}
						}
						$arVars[$j] = $tmpVars;
						$strParams = str_replace($m2[0][$k2], '$'.$j, $strParams);
						$j++;
					}
				}
				foreach(explode(',', $strParams) as $v)
				{
					$v = trim($v);
					if(strpos($v, '=')!==false)
					{
						list($k,$v) = explode('=', $v, 2);
						if(preg_match('/^\$(\d+)$/', $v, $m3) && isset($arVars[$m3[1]])) $v = $arVars[$m3[1]];
						$arMethodParams[$k] = $v;
						$isParamNames = true;
					}
					else 
					{
						if(preg_match('/^\$(\d+)$/', $v, $m3) && isset($arVars[$m3[1]])) $v = $arVars[$m3[1]];
						$arMethodParams[] = $v;
					}
				}
			}
			//while(count($arMethodParams) > 0 && strlen($arMethodParams[count($arMethodParams)-1])==0) {unset($arMethodParams[count($arMethodParams)-1]);}
			$params = array(
				'login' => $arUrl['user'],
				'password' => $arUrl['pass'],
				'encoding' => self::getSiteEncoding(),
				'features' => SOAP_SINGLE_ELEMENT_ARRAYS
			);
			$client = new \SoapClient($wsdl_url, $params);
			$method = $arUrl['fragment'];
			if(is_callable(array($client, $method)))
			{
				$arFuncs = $client->__getFunctions();
				$arTypes = $client->__getTypes();
				if(($arFunc = preg_grep('/\s+'.preg_quote($method).'\((\S+)\s+/', $arFuncs)) && (count($arFunc)>0) && ($func = current($arFunc)) && preg_match('/\s+'.preg_quote($method).'\((\S+)\s+/', $func, $m) && ($arType = preg_grep('/^struct\s+'.preg_quote($m[1]).'\s*\{/', $arTypes)) && (count($arType)>0)) $arMethodParams = (object)$arMethodParams;
				//$arMethodParams = (object)$arMethodParams;
				//$cat = $client->__soapCall($method, $arMethodParams);
				if($isParamNames) $cat = call_user_func(array($client, $method), $arMethodParams);
				else $cat = call_user_func_array(array($client, $method), $arMethodParams);
				$xml = new \SimpleXMLElement('<data></data>');
				self::Array2SimpleXML($xml, $cat);
				$tempPath = self::GetNewFile(\Bitrix\Main\IO\Path::convertLogicalToPhysical($method));
				$xml = $xml->asXML();
				$xml = str_replace('<ID_PARENT>Order</ID_PARENT>', '', $xml);
				file_put_contents($tempPath, $xml);
				$path = $tempPath;
			}
		}
		return $path;
	}
	
	public static function Array2SimpleXML(&$xml_data, $data)
	{
		foreach($data as $key => $value)
		{
			if(is_object($value)) $value = (array)$value;
			if(is_array($value))
			{
				if(is_numeric($key)) $key = 'item';
				$subnode = $xml_data->addChild($key);
				self::Array2SimpleXML($subnode, $value);
			}
			else
			{
				$value = preg_replace('/&(?!(amp;|quot;|#039;|lt;|gt;))/', '&amp;', $value);
				$xml_data->addChild($key, htmlspecialcharsex($value));
			}
		}
	}
	
	public static function PathContainsMask($path)
	{
		return (bool)((strpos($path, '*')!==false || (strpos($path, '{')!==false && strpos($path, '}')!==false)));
	}
	
	public static function GetFilesByMask($mask)
	{
		$arFiles = array();
		$prefix = (strpos($mask, $_SERVER['DOCUMENT_ROOT'])===0 ? '' : $_SERVER['DOCUMENT_ROOT']);
		if(strpos($mask, '/*/')===false)
		{
			$arFiles = glob($prefix.$mask, GLOB_BRACE);
		}
		else
		{
			$i = 1;
			while(empty($arFiles) && $i<8)
			{
				$arFiles = glob($prefix.str_replace('/*/', str_repeat('/*', $i).'/', $mask), GLOB_BRACE);
				$i++;
			}
		}
		if(empty($arFiles)) return array();
		
		usort($arFiles, array(__CLASS__, 'SortByFilemtime'));
		
		$arFiles = array_map(array(__CLASS__, 'RemoveDocRoot'), $arFiles);
		return $arFiles;
	}
	
	public static function GetPatternForRegexp($pattern)
	{
		$pattern = preg_quote($pattern, '/');
		$pattern = preg_replace_callback('/\\\{([^\}]*)\\\}/', array(__CLASS__, 'GetPatternCallback'), $pattern);
		$pattern = strtr($pattern, array('\*'=>'.*', '\?'=>'.'));
		return '/'.$pattern.'/';
	}
	
	public static function GetNewFile($newName)
	{
		$temp_path = \CFile::GetTempName('', bx_basename($newName));
		$temp_dir = \Bitrix\Main\IO\Path::getDirectory($temp_path);
		\Bitrix\Main\IO\Directory::createDirectory($temp_dir);
		return $temp_path;
	}
	
	public static function RemoveOldFile($old_temp_path)
	{
		unlink($old_temp_path);
		$dir = dirname($old_temp_path);
		if(count(array_diff(scandir($dir), array('.', '..')))==0)
		{
			rmdir($dir);
		}
	}
	
	public static function ReplaceFile($old_temp_path, $newName)
	{
		$temp_path = self::GetNewFile(\Bitrix\Main\IO\Path::convertLogicalToPhysical($newName));
		if(file_exists($old_temp_path)) copy($old_temp_path, $temp_path);
		elseif(file_exists(\Bitrix\Main\IO\Path::convertLogicalToPhysical($old_temp_path))) copy(\Bitrix\Main\IO\Path::convertLogicalToPhysical($old_temp_path), $temp_path);
		self::RemoveOldFile($old_temp_path);
		return $temp_path;
	}
	
	public static function GetFileExtension($filename)
	{
		$filename = end(explode('/', $filename));
		$arParts = explode('.', $filename);
		if(count($arParts) > 1) 
		{
			$ext = array_pop($arParts);
			if(ToLower($ext)=='gz' && count($arParts) > 1)
			{
				$ext = array_pop($arParts).'.'.$ext;
			}
			return $ext;
		}
		else return '';
	}
	
	public static function GetShowFileBySettings($SETTINGS_DEFAULT)
	{
		$path = $link = '';
		if($SETTINGS_DEFAULT["EXT_DATA_FILE"])
		{
			if(preg_match('/^\{.*\}$/s', $SETTINGS_DEFAULT["EXT_DATA_FILE"]))
			{
				$arParams = \CUtil::JsObjectToPhp($SETTINGS_DEFAULT["EXT_DATA_FILE"]);
				if(isset($arParams['FILELINK']))
				{
					$path = $arParams['FILELINK'];
				}
			}
			else
			{
				$path = $SETTINGS_DEFAULT["EXT_DATA_FILE"];
			}
			if($path) $link = $path;
		}
		elseif($SETTINGS_DEFAULT["EMAIL_DATA_FILE"])
		{
			$json = $SETTINGS_DEFAULT["EMAIL_DATA_FILE"];
			if(strlen($json) > 0 && strpos($json, '{')===false) $json = base64_decode($json);
			$arParams = \CUtil::JsObjectToPhp($json);
			if(!is_array($arParams)) $arParams = unserialize($json);
			if(isset($arParams['EMAIL']))
			{
				$path = $arParams['EMAIL'];
			}
			if($SETTINGS_DEFAULT["URL_DATA_FILE"] && ($basename = bx_basename($SETTINGS_DEFAULT["URL_DATA_FILE"])))
			{
				$path = $basename.' <'.$path.'>';
			}
		}
		return array('link'=>$link, 'path'=>$path);
	}
	
	public static function AddFileInputActions()
	{
		//AddEventHandler("main", "OnEndBufferContent", Array("\Bitrix\EsolImportxml\Utils", "AddFileInputActionsHandler"));
	}
	
	public static function AddFileInputActionsHandler(&$content)
	{
		return;
		//if(!function_exists('imap_open')) return;
		
		$comment = 'ESOL_IX_CHOOSE_FILE';
		$commentBegin = '<!--'.$comment.'-->';
		$commentEnd = '<!--/'.$comment.'-->';
		$pos1 = mb_strpos($content, $commentBegin);
		$pos2 = mb_strpos($content, $commentEnd);
		if($pos1!==false && $pos2!==false)
		{
			$partContent = mb_substr($content, $pos1, $pos2 + mb_strlen($commentEnd) - $pos1);
			if(preg_match_all('/<script[^>]*>.*<\/script>/Uis', $partContent, $m))
			{
				$arScripts = preg_grep('/BX\.file_input\((\{.*\'bx_file_data_file\'.*\})\)[;<]/Uis', $m[0]);
				while(count($arScripts) > 1)
				{
					$script = array_pop($arScripts);
					if($pos = mb_strrpos($partContent, $script))
					{
						$newPartContent = mb_substr($partContent, 0, $pos).mb_substr($partContent, $pos+mb_strlen($script));
						$content = str_replace($partContent, $newPartContent, $content);
						$partContent = $newPartContent;
					}
				}
			}
			if(preg_match('/BX\.file_input\((\{.*\})\)\s*[:;<]/Us', $partContent, $m))
			{
				$json = $m[1];
				$arConfig = \CUtil::JsObjectToPhp($json);
				array_walk_recursive($arConfig, array(__CLASS__, 'ArrStringToBool'));
				$arConfigEmail = array(
					'TEXT' => Loc::getMessage("ESOL_IX_FILE_SOURCE_EMAIL"),
					'GLOBAL_ICON' => 'adm-menu-upload-email',
					'ONCLICK' => 'EProfile.ShowEmailForm();'
				);
				$arConfig['menuNew'][] = $arConfigEmail;
				$arConfig['menuExist'][] = $arConfigEmail;
				$arConfigLinkAuth = array(
					'TEXT' => Loc::getMessage("ESOL_IX_FILE_SOURCE_LINKAUTH"),
					'GLOBAL_ICON' => 'adm-menu-upload-linkauth',
					'ONCLICK' => 'EProfile.ShowFileAuthForm();'
				);
				$arConfig['menuNew'][] = $arConfigLinkAuth;
				$arConfig['menuExist'][] = $arConfigLinkAuth;
				$newJson = \CUtil::PHPToJSObject($arConfig);
				$newPartContent = str_replace($json, $newJson, $partContent);
				$content = str_replace($partContent, $newPartContent, $content);
			}
		}
	}
	
	public static function ExecuteFilterExpression($val, $expression, $altReturn = true, $arCookies=array())
	{
		$expression = trim($expression);
		try{				
			if(stripos($expression, 'return')===0)
			{
				return eval($expression.';');
			}
			elseif(preg_match('/\$val\s*=/', $expression))
			{
				eval($expression.';');
				return $val;
			}
			else
			{
				return eval('return '.$expression.';');
			}
		}catch(\Exception $ex){
			return $altReturn;
		}
	}
	
	public static function ShowFilter($sTableID, $IBLOCK_ID, $FILTER)
	{
		global $APPLICATION;
		\CJSCore::Init('file_input');
		$sf = 'FILTER';

		Loader::includeModule('iblock');
		$bCatalog = Loader::includeModule('catalog');
		if($bCatalog)
		{
			$arCatalog = \CCatalog::GetByID($IBLOCK_ID);
			if($arCatalog)
			{
				if(is_callable(array('\CCatalogAdminTools', 'getIblockProductTypeList')))
				{
					$productTypeList = \CCatalogAdminTools::getIblockProductTypeList($IBLOCK_ID, true);
				}
				
				$arStores = array();
				$dbRes = \CCatalogStore::GetList(array("SORT"=>"ID"), array(), false, false, array("ID", "TITLE", "ADDRESS"));
				while($arStore = $dbRes->Fetch())
				{
					if(strlen($arStore['TITLE'])==0 && $arStore['ADDRESS']) $arStore['TITLE'] = $arStore['ADDRESS'];
					$arStores[] = $arStore;
				}
				
				$arPrices = array();
				$dbPriceType = \CCatalogGroup::GetList(array("SORT" => "ASC"));
				while($arPriceType = $dbPriceType->Fetch())
				{
					if(strlen($arPriceType["NAME_LANG"])==0 && $arPriceType['NAME']) $arPriceType['NAME_LANG'] = $arPriceType['NAME'];
					$arPrices[] = $arPriceType;
				}
			}
			if(!$arCatalog) $bCatalog = false;
		}
		
		$arFields = (is_array($FILTER) ? $FILTER : array());
		$dbrFProps = \CIBlockProperty::GetList(
			array(
				"SORT"=>"ASC",
				"NAME"=>"ASC"
			),
			array(
				"IBLOCK_ID"=>$IBLOCK_ID,
				"CHECK_PERMISSIONS"=>"N",
			)
		);

		$arProps = array();
		while ($arProp = $dbrFProps->GetNext())
		{
			if ($arProp["ACTIVE"] == "Y")
			{
				$arProp["PROPERTY_USER_TYPE"] = ('' != $arProp["USER_TYPE"] ? \CIBlockProperty::GetUserType($arProp["USER_TYPE"]) : array());
				$arProp['NAME'] = $arProp['NAME'].' ['.$arProp['CODE'].']';
				$arProps[] = $arProp;
			}
		}
		
		?>
		<script>var arClearHiddenFields = [];</script>
		<!--<form method="GET" name="find_form" id="find_form" action="">-->
		<div class="find_form_inner">
		<?
		$arFindFields = Array();
		//$arFindFields["IBEL_A_F_ID"] = Loc::getMessage("ESOL_IX_IBEL_A_F_ID");
		$arFindFields["IBEL_A_F_PARENT"] = Loc::getMessage("ESOL_IX_IBEL_A_F_PARENT");

		$arFindFields["IBEL_A_F_MODIFIED_WHEN"] = Loc::getMessage("ESOL_IX_IBEL_A_F_MODIFIED_WHEN");
		$arFindFields["IBEL_A_F_MODIFIED_BY"] = Loc::getMessage("ESOL_IX_IBEL_A_F_MODIFIED_BY");
		$arFindFields["IBEL_A_F_CREATED_WHEN"] = Loc::getMessage("ESOL_IX_IBEL_A_F_CREATED_WHEN");
		$arFindFields["IBEL_A_F_CREATED_BY"] = Loc::getMessage("ESOL_IX_IBEL_A_F_CREATED_BY");

		$arFindFields["IBEL_A_F_ACTIVE_FROM"] = Loc::getMessage("ESOL_IX_IBEL_A_ACTFROM");
		$arFindFields["IBEL_A_F_ACTIVE_TO"] = Loc::getMessage("ESOL_IX_IBEL_A_ACTTO");
		$arFindFields["IBEL_A_F_ACT"] = Loc::getMessage("ESOL_IX_IBEL_A_F_ACT");
		$arFindFields["IBEL_A_F_NAME"] = Loc::getMessage("ESOL_IX_IBEL_A_F_NAME");
		$arFindFields["IBEL_A_F_DESC"] = Loc::getMessage("ESOL_IX_IBEL_A_F_DESC");
		$arFindFields["IBEL_A_CODE"] = Loc::getMessage("ESOL_IX_IBEL_A_CODE");
		$arFindFields["IBEL_A_EXTERNAL_ID"] = Loc::getMessage("ESOL_IX_IBEL_A_EXTERNAL_ID");
		$arFindFields["IBEL_A_PREVIEW_PICTURE"] = Loc::getMessage("ESOL_IX_IBEL_A_PREVIEW_PICTURE");
		$arFindFields["IBEL_A_DETAIL_PICTURE"] = Loc::getMessage("ESOL_IX_IBEL_A_DETAIL_PICTURE");
		$arFindFields["IBEL_A_TAGS"] = Loc::getMessage("ESOL_IX_IBEL_A_TAGS");
		
		if ($bCatalog)
		{
			if(is_array($productTypeList)) $arFindFields["CATALOG_TYPE"] = Loc::getMessage("ESOL_IX_CATALOG_TYPE");
			$arFindFields["CATALOG_BUNDLE"] = Loc::getMessage("ESOL_IX_CATALOG_BUNDLE");
			$arFindFields["CATALOG_AVAILABLE"] = Loc::getMessage("ESOL_IX_CATALOG_AVAILABLE");
			$arFindFields["CATALOG_QUANTITY"] = Loc::getMessage("ESOL_IX_CATALOG_QUANTITY");
			if(is_array($arStores))
			{
				foreach($arStores as $arStore)
				{
					$arFindFields["CATALOG_STORE".$arStore['ID']."_QUANTITY"] = sprintf(Loc::getMessage("ESOL_IX_CATALOG_STORE_QUANTITY"), $arStore['TITLE']);
				}
			}
			if(is_array($arPrices))
			{
				foreach($arPrices as $arPrice)
				{
					$arFindFields["CATALOG_PRICE_".$arPrice['ID']] = sprintf(Loc::getMessage("ESOL_IX_CATALOG_PRICE"), $arPrice['NAME_LANG']);
				}
			}
		}

		foreach($arProps as $arProp)
			if($arProp["FILTRABLE"]=="Y" && $arProp["PROPERTY_TYPE"]!="F")
				$arFindFields["IBEL_A_PROP_".$arProp["ID"]] = $arProp["NAME"];
		
		$oFilter = new \CAdminFilter($sTableID."_filter", $arFindFields);
		
		$oFilter->Begin();
		?>
			<?/*?><tr>
				<td><?echo Loc::getMessage("ESOL_IX_FILTER_FROMTO_ID")?>:</td>
				<td nowrap>
					<input type="text" name="<?echo $sf;?>[find_el_id_start]" size="10" value="<?echo htmlspecialcharsex($arFields['find_el_id_start'])?>">
					...
					<input type="text" name="<?echo $sf;?>[find_el_id_end]" size="10" value="<?echo htmlspecialcharsex($arFields['find_el_id_end'])?>">
				</td>
			</tr><?*/?>

			<tr>
				<td><?echo Loc::getMessage("ESOL_IX_FIELD_SECTION_ID")?>:</td>
				<td>
					<select name="<?echo $sf;?>[find_section_section][]" multiple size="5">
						<option value="-1"<?if((is_array($arFields['find_section_section']) && in_array("-1", $arFields['find_section_section'])) || $arFields['find_section_section']=="-1")echo" selected"?>><?echo Loc::getMessage("ESOL_IX_VALUE_ANY")?></option>
						<option value="0"<?if((is_array($arFields['find_section_section']) && in_array("0", $arFields['find_section_section'])) || $arFields['find_section_section']=="0")echo" selected"?>><?echo Loc::getMessage("ESOL_IX_UPPER_LEVEL")?></option>
						<?
						$bsections = \CIBlockSection::GetTreeList(Array("IBLOCK_ID"=>$IBLOCK_ID), array("ID", "NAME", "DEPTH_LEVEL"));
						while($ar = $bsections->GetNext()):
							?><option value="<?echo $ar["ID"]?>"<?if((is_array($arFields['find_section_section']) && in_array($ar["ID"], $arFields['find_section_section'])) || $ar["ID"]==$arFields['find_section_section'])echo " selected"?>><?echo str_repeat("&nbsp;.&nbsp;", $ar["DEPTH_LEVEL"])?><?echo $ar["NAME"]?></option><?
						endwhile;
						?>
					</select><br>
					<input type="checkbox" name="<?echo $sf;?>[find_el_subsections]" value="Y"<?if($arFields['find_el_subsections']=="Y")echo" checked"?>> <?echo Loc::getMessage("ESOL_IX_INCLUDING_SUBSECTIONS")?>
				</td>
			</tr>

			<tr>
				<td><?echo Loc::getMessage("ESOL_IX_FIELD_TIMESTAMP_X")?>:</td>
				<td><?echo CalendarPeriod($sf."[find_el_timestamp_from]", htmlspecialcharsex($arFields['find_el_timestamp_from']), $sf."[find_el_timestamp_to]", htmlspecialcharsex($arFields['find_el_timestamp_to']), "filter_form", "Y")?></font></td>
			</tr>

			<tr>
				<td><?=Loc::getMessage("ESOL_IX_FIELD_MODIFIED_BY")?>:</td>
				<td>
					<?echo FindUserID(
						$sf."[find_el_modified_user_id]",
						$arFields['find_el_modified_user_id'],
						"",
						"filter_form",
						"5",
						"",
						" ... ",
						"",
						""
					);?>
				</td>
			</tr>

			<tr>
				<td><?echo Loc::getMessage("ESOL_IX_EL_ADMIN_DCREATE")?>:</td>
				<td><?echo CalendarPeriod($sf."[find_el_created_from]", htmlspecialcharsex($arFields['find_el_created_from']), $sf."[find_el_created_to]", htmlspecialcharsex($arFields['find_el_created_to']), "filter_form", "Y")?></td>
			</tr>

			<tr>
				<td><?echo Loc::getMessage("ESOL_IX_EL_ADMIN_WCREATE")?></td>
				<td>
					<?echo FindUserID(
						$sf."[find_el_created_user_id]",
						$arFields['find_el_created_user_id'],
						"",
						"filter_form",
						"5",
						"",
						" ... ",
						"",
						""
					);?>
				</td>
			</tr>

			<tr>
				<td><?echo Loc::getMessage("ESOL_IX_EL_A_ACTFROM")?>:</td>
				<td><?echo CalendarPeriod($sf."[find_el_date_active_from_from]", htmlspecialcharsex($arFields['find_el_date_active_from_from']), $sf."[find_el_date_active_from_to]", htmlspecialcharsex($arFields['find_el_date_active_from_to']), "filter_form")?></td>
			</tr>

			<tr>
				<td><?echo Loc::getMessage("ESOL_IX_EL_A_ACTTO")?>:</td>
				<td><?echo CalendarPeriod($sf."[find_el_date_active_to_from]", htmlspecialcharsex($arFields['find_el_date_active_to_from']), $sf."[find_el_date_active_to_to]", htmlspecialcharsex($arFields['find_el_date_active_to_to']), "filter_form")?></td>
			</tr>

			<tr>
				<td><?echo Loc::getMessage("ESOL_IX_FIELD_ACTIVE")?>:</td>
				<td>
					<select name="<?echo $sf;?>[find_el_active]">
						<option value=""><?=htmlspecialcharsex(Loc::getMessage('ESOL_IX_VALUE_ANY'))?></option>
						<option value="Y"<?if($arFields['find_el_active']=="Y")echo " selected"?>><?=htmlspecialcharsex(Loc::getMessage("ESOL_IX_YES"))?></option>
						<option value="N"<?if($arFields['find_el_active']=="N")echo " selected"?>><?=htmlspecialcharsex(Loc::getMessage("ESOL_IX_NO"))?></option>
					</select>
				</td>
			</tr>

			<tr>
				<td><?echo Loc::getMessage("ESOL_IX_FIELD_NAME")?>:</td>
				<td><input type="text" name="<?echo $sf;?>[find_el_name]" value="<?echo htmlspecialcharsex($arFields['find_el_name'])?>" size="30"></td>
			</tr>
			<tr>
				<td><?echo Loc::getMessage("ESOL_IX_EL_ADMIN_DESC")?></td>
				<td><input type="text" name="<?echo $sf;?>[find_el_intext]" value="<?echo htmlspecialcharsex($arFields['find_el_intext'])?>" size="30"></td>
			</tr>

			<tr>
				<td><?=Loc::getMessage("ESOL_IX_EL_A_CODE")?>:</td>
				<td><input type="text" name="<?echo $sf;?>[find_el_code]" value="<?echo htmlspecialcharsex($arFields['find_el_code'])?>" size="30"></td>
			</tr>
			<tr>
				<td><?=Loc::getMessage("ESOL_IX_EL_A_EXTERNAL_ID")?>:</td>
				<td>
					<select class="esol-ix-filter-chval" name="<?echo $sf;?>[find_el_vtype_external_id]">
						<option value=""><?echo Loc::getMessage("ESOL_IX_IS_VALUE")?></option>
						<option value="contain"<?if($arFields["find_el_vtype_external_id"]=='contain'){echo ' selected';}?>><?echo Loc::getMessage("ESOL_IX_VTYPE_CONTAIN")?></option>
						<option value="not_contain"<?if($arFields["find_el_vtype_external_id"]=='not_contain'){echo ' selected';}?>><?echo Loc::getMessage("ESOL_IX_VTYPE_NOT_CONTAIN")?></option>
						<option value="begin_with"<?if($arFields["find_el_vtype_external_id"]=='begin_with'){echo ' selected';}?>><?echo Loc::getMessage("ESOL_IX_VTYPE_BEGIN_WITH")?></option>
						<option value="end_on"<?if($arFields["find_el_vtype_external_id"]=='end_on'){echo ' selected';}?>><?echo Loc::getMessage("ESOL_IX_VTYPE_END_ON")?></option>
						<option value="empty"<?if($arFields["find_el_vtype_external_id"]=='empty'){echo ' selected';}?>><?echo Loc::getMessage("ESOL_IX_IS_EMPTY")?></option>
						<option value="not_empty"<?if($arFields["find_el_vtype_external_id"]=='not_empty'){echo ' selected';}?>><?echo Loc::getMessage("ESOL_IX_IS_NOT_EMPTY")?></option>
					</select>
					<input type="text" name="<?echo $sf;?>[find_el_external_id]" value="<?echo htmlspecialcharsex($arFields["find_el_external_id"])?>" size="30">
				</td>
			</tr>
			<tr>
				<td><?=Loc::getMessage("ESOL_IX_EL_A_PREVIEW_PICTURE")?>:</td>
				<td>
					<select name="<?echo $sf;?>[find_el_preview_picture]">
						<option value=""><?=htmlspecialcharsex(Loc::getMessage('ESOL_IX_VALUE_ANY'))?></option>
						<option value="Y"<?if($arFields['find_el_preview_picture']=="Y")echo " selected"?>><?=htmlspecialcharsex(Loc::getMessage("ESOL_IX_IS_NOT_EMPTY"))?></option>
						<option value="N"<?if($arFields['find_el_preview_picture']=="N")echo " selected"?>><?=htmlspecialcharsex(Loc::getMessage("ESOL_IX_IS_EMPTY"))?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td><?=Loc::getMessage("ESOL_IX_EL_A_DETAIL_PICTURE")?>:</td>
				<td>
					<select name="<?echo $sf;?>[find_el_detail_picture]">
						<option value=""><?=htmlspecialcharsex(Loc::getMessage('ESOL_IX_VALUE_ANY'))?></option>
						<option value="Y"<?if($arFields['find_el_detail_picture']=="Y")echo " selected"?>><?=htmlspecialcharsex(Loc::getMessage("ESOL_IX_IS_NOT_EMPTY"))?></option>
						<option value="N"<?if($arFields['find_el_detail_picture']=="N")echo " selected"?>><?=htmlspecialcharsex(Loc::getMessage("ESOL_IX_IS_EMPTY"))?></option>
					</select>
				</td>
			</tr>
			<tr>
				<td><?=Loc::getMessage("ESOL_IX_EL_A_TAGS")?>:</td>
				<td>
					<input type="text" name="<?echo $sf;?>[find_el_tags]" value="<?echo htmlspecialcharsex($arFields['find_el_tags'])?>" size="30">
				</td>
			</tr>
			<?
			if ($bCatalog)
			{
				if(is_array($productTypeList))
				{
				?><tr>
					<td><?=Loc::getMessage("ESOL_IX_CATALOG_TYPE"); ?>:</td>
					<td>
						<select name="<?echo $sf;?>[find_el_catalog_type][]" multiple>
							<option value=""><?=htmlspecialcharsex(Loc::getMessage('ESOL_IX_VALUE_ANY'))?></option>
							<?
							$catalogTypes = (!empty($arFields['find_el_catalog_type']) ? $arFields['find_el_catalog_type'] : array());
							foreach ($productTypeList as $productType => $productTypeName)
							{
								?>
								<option value="<? echo $productType; ?>"<? echo (in_array($productType, $catalogTypes) ? ' selected' : ''); ?>><? echo htmlspecialcharsex($productTypeName); ?></option><?
							}
							unset($productType, $productTypeName, $catalogTypes);
							?>
						</select>
					</td>
				</tr>
				<?
				}
				?>
				<tr>
					<td><?echo Loc::getMessage("ESOL_IX_CATALOG_BUNDLE")?>:</td>
					<td>
						<select name="<?echo $sf;?>[find_el_catalog_bundle]">
							<option value=""><?=htmlspecialcharsex(Loc::getMessage('ESOL_IX_VALUE_ANY'))?></option>
							<option value="Y"<?if($arFields['find_el_catalog_bundle']=="Y")echo " selected"?>><?=htmlspecialcharsex(Loc::getMessage("ESOL_IX_YES"))?></option>
							<option value="N"<?if($arFields['find_el_catalog_bundle']=="N")echo " selected"?>><?=htmlspecialcharsex(Loc::getMessage("ESOL_IX_NO"))?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td><?echo Loc::getMessage("ESOL_IX_CATALOG_AVAILABLE")?>:</td>
					<td>
						<select name="<?echo $sf;?>[find_el_catalog_available]">
							<option value=""><?=htmlspecialcharsex(Loc::getMessage('ESOL_IX_VALUE_ANY'))?></option>
							<option value="Y"<?if($arFields['find_el_catalog_available']=="Y")echo " selected"?>><?=htmlspecialcharsex(Loc::getMessage("ESOL_IX_YES"))?></option>
							<option value="N"<?if($arFields['find_el_catalog_available']=="N")echo " selected"?>><?=htmlspecialcharsex(Loc::getMessage("ESOL_IX_NO"))?></option>
						</select>
					</td>
				</tr>
				<tr>
					<td><?echo Loc::getMessage("ESOL_IX_CATALOG_QUANTITY")?>:</td>
					<td>
						<select name="<?echo $sf;?>[find_el_catalog_quantity_comp]">
							<option value="eq" <?if($arFields['find_el_catalog_quantity_comp']=='eq'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_EQ')?></option>
							<option value="gt" <?if($arFields['find_el_catalog_quantity_comp']=='gt'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_GT')?></option>
							<option value="geq" <?if($arFields['find_el_catalog_quantity_comp']=='geq'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_GEQ')?></option>
							<option value="lt" <?if($arFields['find_el_catalog_quantity_comp']=='lt'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_LT')?></option>
							<option value="leq" <?if($arFields['find_el_catalog_quantity_comp']=='leq'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_LEQ')?></option>
						</select>
						<input type="text" name="<?echo $sf;?>[find_el_catalog_quantity]" value="<?echo htmlspecialcharsex($arFields['find_el_catalog_quantity'])?>" size="10">
					</td>
				</tr>
				
				<?
				if(is_array($arStores))
				{
					foreach($arStores as $arStore)
					{
						?>
						<tr>
							<td><?echo sprintf(Loc::getMessage("ESOL_IX_CATALOG_STORE_QUANTITY"), $arStore['TITLE'])?>:</td>
							<td>
								<select name="<?echo $sf;?>[find_el_catalog_store<?echo $arStore['ID'];?>_quantity_comp]">
									<option value="eq" <?if($arFields['find_el_catalog_store'.$arStore['ID'].'_quantity_comp']=='eq'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_EQ')?></option>
									<option value="gt" <?if($arFields['find_el_catalog_store'.$arStore['ID'].'_quantity_comp']=='gt'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_GT')?></option>
									<option value="geq" <?if($arFields['find_el_catalog_store'.$arStore['ID'].'_quantity_comp']=='geq'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_GEQ')?></option>
									<option value="lt" <?if($arFields['find_el_catalog_store'.$arStore['ID'].'_quantity_comp']=='lt'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_LT')?></option>
									<option value="leq" <?if($arFields['find_el_catalog_store'.$arStore['ID'].'_quantity_comp']=='leq'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_LEQ')?></option>
								</select>
								<input type="text" name="<?echo $sf;?>[find_el_catalog_store<?echo $arStore['ID'];?>_quantity]" value="<?echo htmlspecialcharsex($arFields['find_el_catalog_store'.$arStore['ID'].'_quantity'])?>" size="10">
							</td>
						</tr>
						<?
					}
				}
				
				if(is_array($arPrices))
				{
					foreach($arPrices as $arPrice)
					{
						?>
						<tr>
							<td><?echo sprintf(Loc::getMessage("ESOL_IX_CATALOG_PRICE"), $arPrice['NAME_LANG'])?>:</td>
							<td>
								<select name="<?echo $sf;?>[find_el_catalog_price_<?echo $arPrice['ID'];?>_comp]">
									<option value="eq" <?if($arFields['find_el_catalog_price_'.$arPrice['ID'].'_comp']=='eq'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_EQ')?></option>
									<option value="empty" <?if($arFields['find_el_catalog_price_'.$arPrice['ID'].'_comp']=='empty'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_EMPTY')?></option>
									<option value="gt" <?if($arFields['find_el_catalog_price_'.$arPrice['ID'].'_comp']=='gt'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_GT')?></option>
									<option value="geq" <?if($arFields['find_el_catalog_price_'.$arPrice['ID'].'_comp']=='geq'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_GEQ')?></option>
									<option value="lt" <?if($arFields['find_el_catalog_price_'.$arPrice['ID'].'_comp']=='lt'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_LT')?></option>
									<option value="leq" <?if($arFields['find_el_catalog_price_'.$arPrice['ID'].'_comp']=='leq'){echo 'selected';}?>><?=Loc::getMessage('ESOL_IX_COMPARE_LEQ')?></option>
								</select>
								<input type="text" name="<?echo $sf;?>[find_el_catalog_price_<?echo $arPrice['ID'];?>]" value="<?echo htmlspecialcharsex($arFields['find_el_catalog_price_'.$arPrice['ID']])?>" size="10">
							</td>
						</tr>
						<?
					}
				}
			}
			
		foreach($arProps as $arProp):
			if($arProp["FILTRABLE"]=="Y" && $arProp["PROPERTY_TYPE"]!="F"):
		?>
		<tr>
			<td><?=$arProp["NAME"]?>:</td>
			<td>
				<?if(array_key_exists("GetAdminFilterHTML", $arProp["PROPERTY_USER_TYPE"])):
					$fieldName = "filter1_find_el_property_".$arProp["ID"];
					if(isset($arFields["find_el_property_".$arProp["ID"]."_from"])) $GLOBALS[$fieldName."_from"] = $arFields["find_el_property_".$arProp["ID"]."_from"];
					if(isset($arFields["find_el_property_".$arProp["ID"]."_to"])) $GLOBALS[$fieldName."_to"] = $arFields["find_el_property_".$arProp["ID"]."_to"];
					$GLOBALS[$fieldName] = $arFields["find_el_property_".$arProp["ID"]];
					$GLOBALS['set_filter'] = 'Y';
					echo call_user_func_array($arProp["PROPERTY_USER_TYPE"]["GetAdminFilterHTML"], array(
						$arProp,
						array(
							"VALUE" => $fieldName,
							"TABLE_ID" => $sTableID,
						),
					));
				elseif($arProp["PROPERTY_TYPE"]=='S'):?>
					<select class="esol-ix-filter-chval" name="<?echo $sf;?>[find_el_vtype_property_<?=$arProp["ID"]?>]"><option value=""><?echo Loc::getMessage("ESOL_IX_IS_VALUE")?></option><option value="empty"<?if($arFields["find_el_vtype_property_".$arProp["ID"]]=='empty'){echo ' selected';}?>><?echo Loc::getMessage("ESOL_IX_IS_EMPTY")?></option><option value="not_empty"<?if($arFields["find_el_vtype_property_".$arProp["ID"]]=='not_empty'){echo ' selected';}?>><?echo Loc::getMessage("ESOL_IX_IS_NOT_EMPTY")?></option></select><input type="text" name="<?echo $sf;?>[find_el_property_<?=$arProp["ID"]?>]" value="<?echo htmlspecialcharsex($arFields["find_el_property_".$arProp["ID"]])?>" size="30">
				<?elseif($arProp["PROPERTY_TYPE"]=='N' || $arProp["PROPERTY_TYPE"]=='E'):?>
					<select class="esol-ix-filter-chval" name="<?echo $sf;?>[find_el_vtype_property_<?=$arProp["ID"]?>]"><option value=""><?echo Loc::getMessage("ESOL_IX_IS_VALUE")?></option><option value="empty"<?if($arFields["find_el_vtype_property_".$arProp["ID"]]=='empty'){echo ' selected';}?>><?echo Loc::getMessage("ESOL_IX_IS_EMPTY")?></option><option value="not_empty"<?if($arFields["find_el_vtype_property_".$arProp["ID"]]=='not_empty'){echo ' selected';}?>><?echo Loc::getMessage("ESOL_IX_IS_NOT_EMPTY")?></option></select><input type="text" name="<?echo $sf;?>[find_el_property_<?=$arProp["ID"]?>]" value="<?echo htmlspecialcharsex($arFields["find_el_property_".$arProp["ID"]])?>" size="30">
				<?elseif($arProp["PROPERTY_TYPE"]=='L'):?>
					<?
					$propVal = $arFields["find_el_property_".$arProp["ID"]];
					if(!is_array($propVal)) $propVal = array($propVal);
					?>
					<select name="<?echo $sf;?>[find_el_property_<?=$arProp["ID"]?>][]" multiple size="5">
						<option value=""><?echo Loc::getMessage("ESOL_IX_VALUE_ANY")?></option>
						<option value="NOT_REF"<?if(in_array("NOT_REF", $propVal))echo " selected"?>><?echo Loc::getMessage("ESOL_IX_ELEMENT_EDIT_NOT_SET")?></option><?
						$dbrPEnum = \CIBlockPropertyEnum::GetList(Array("SORT"=>"ASC", "NAME"=>"ASC"), Array("PROPERTY_ID"=>$arProp["ID"]));
						while($arPEnum = $dbrPEnum->GetNext()):
						?>
							<option value="<?=$arPEnum["ID"]?>"<?if(in_array($arPEnum["ID"], $propVal))echo " selected"?>><?=$arPEnum["VALUE"]?></option>
						<?
						endwhile;
				?></select>
				<?
				elseif($arProp["PROPERTY_TYPE"]=='G'):
					echo self::ShowGroupPropertyField2($sf.'[find_el_property_'.$arProp["ID"].']', $arProp, $arFields["find_el_property_".$arProp["ID"]]);
				elseif(array_key_exists("GetPropertyFieldHtml", $arProp["PROPERTY_USER_TYPE"])):
					$inputHTML = call_user_func_array($arProp["PROPERTY_USER_TYPE"]["GetPropertyFieldHtml"], array(
						$arProp,
						array(
							"VALUE" => $arFields["find_el_property_".$arProp["ID"]],
							"DESCRIPTION" => '',
						),
						array(
							"VALUE" => "filter1_find_el_property_".$arProp["ID"],
							"DESCRIPTION" => '',
							"MODE"=>"iblock_element_admin",
							"FORM_NAME"=>"filter_form"
						),
					));
					$inputHTML = '<table style="margin: 0 0 5px 12px;"><tr id="tr_PROPERTY_'.$arProp["ID"].'"><td>'.$inputHTML.'</td></tr></table>';
					//$inputHTML = '<span class="adm-select-wrap">'.$inputHTML.'</span>';
					if(class_exists('\Bitrix\Main\Page\Asset') && class_exists('\Bitrix\Main\Page\AssetShowTargetType'))
					{
						$inputHTML = \Bitrix\Main\Page\Asset::getInstance()->GetJs(\Bitrix\Main\Page\AssetShowTargetType::TEMPLATE_PAGE).\Bitrix\Main\Page\Asset::getInstance()->GetCss(\Bitrix\Main\Page\AssetShowTargetType::TEMPLATE_PAGE).$inputHTML;
					}
					echo $inputHTML;
				endif;
				?>
			</td>
		</tr>
		<?
			endif;
		endforeach;

		$oFilter->Buttons();
		/*?><span class="adm-btn-wrap"><input type="submit"  class="adm-btn" name="set_filter" value="<? echo Loc::getMessage("admin_lib_filter_set_butt"); ?>" title="<? echo Loc::getMessage("admin_lib_filter_set_butt_title"); ?>" onClick="return EProfile.ApplyFilter(this);"></span>
		<span class="adm-btn-wrap"><input type="submit"  class="adm-btn" name="del_filter" value="<? echo Loc::getMessage("admin_lib_filter_clear_butt"); ?>" title="<? echo Loc::getMessage("admin_lib_filter_clear_butt_title"); ?>" onClick="return EList.DeleteFilter(this);"></span>
		<?*/
		$oFilter->End();
		
		?>
		<!--</form>-->
		</div>
		<?
	}
	
	public static function ShowFilterHighload($sTableID, $HLBL_ID, $FILTER)
	{
		global $APPLICATION, $USER_FIELD_MANAGER;
		\CJSCore::Init('file_input');
		$sf = 'FILTER';

		$arFields = (is_array($FILTER) ? $FILTER : array());
		$ufEntityId = 'HLBLOCK_'.$HLBL_ID;
		?>
		<script>var arClearHiddenFields = [];</script>
		<!--<form method="GET" name="find_form" id="find_form" action="">-->
		<div class="find_form_inner">
		<?
		$filterValues = array();
		$arFindFields = array('ID');
		
		$USER_FIELD_MANAGER->AdminListAddFilterFields($ufEntityId, $filterFields);
		//$USER_FIELD_MANAGER->AddFindFields($ufEntityId, $arFindFields);
		$arUserFields = $USER_FIELD_MANAGER->GetUserFields($ufEntityId, 0, LANGUAGE_ID);
		foreach($arUserFields as $FIELD_NAME=>$arUserField)
		{
			if(/*$arUserField["SHOW_FILTER"]!="N" &&*/ $arUserField["USER_TYPE"]["BASE_TYPE"]!="file")
			{
				$arFindFields[$FIELD_NAME] = (strlen(trim($arUserField['LIST_FILTER_LABEL'])) > 0 ? $arUserField['LIST_FILTER_LABEL'] : $FIELD_NAME);
			}
		}
		
		$oFilter = new \CAdminFilter($sTableID."_filter", $arFindFields);
		
		$oFilter->Begin();
		
		?>
		<tr>
			<td>ID</td>
			<td><input type="text" name="<?echo $sf?>[find_ID]" size="47" value="<?echo htmlspecialcharsbx($arFields['find_ID'])?>"></td>
		</tr>
		<?
		foreach($arUserFields as $FIELD_NAME=>$arUserField)
		{
			if(/*$arUserField["SHOW_FILTER"]!="N" &&*/ $arUserField["USER_TYPE"]["BASE_TYPE"]!="file")
			{
				if(in_array($arUserField["USER_TYPE_ID"], array('date', 'datetime')))
				{
					$GLOBALS[$sf."[find_".$FIELD_NAME."]_from"] = $arFields['find_'.$FIELD_NAME.'_from'];
					$GLOBALS[$sf."[find_".$FIELD_NAME."]_to]"] = $arFields['find_'.$FIELD_NAME.'_to'];
					$GLOBALS[$sf."[find_".$FIELD_NAME."]_from_FILTER_PERIOD"] = $arFields['find_'.$FIELD_NAME.'_from_FILTER_PERIOD'];
					$GLOBALS[$sf."[find_".$FIELD_NAME."]_from_FILTER_DIRECTION"] = $arFields['find_'.$FIELD_NAME.'_from_FILTER_DIRECTION'];
					$inputHTML = $USER_FIELD_MANAGER->GetFilterHTML($arUserField, $sf.'[find_'.$FIELD_NAME.']', $arFields['find_'.$FIELD_NAME]);
				}
				else
				{
					$inputHTML = $USER_FIELD_MANAGER->GetFilterHTML($arUserField, $sf.'[find_'.$FIELD_NAME.']', $arFields['find_'.$FIELD_NAME]);
				}
				echo $inputHTML;
			}
		}
	
		$oFilter->Buttons();
		/*?><span class="adm-btn-wrap"><input type="submit"  class="adm-btn" name="set_filter" value="<? echo Loc::getMessage("admin_lib_filter_set_butt"); ?>" title="<? echo Loc::getMessage("admin_lib_filter_set_butt_title"); ?>" onClick="return EList.ApplyFilter(this);"></span>
		<span class="adm-btn-wrap"><input type="submit"  class="adm-btn" name="del_filter" value="<? echo Loc::getMessage("admin_lib_filter_clear_butt"); ?>" title="<? echo Loc::getMessage("admin_lib_filter_clear_butt_title"); ?>" onClick="return EList.DeleteFilter(this);"></span>
		<?*/
		$oFilter->End();

		?>
		<!--</form>-->
		</div>
		<?
	}
	
	public static function ShowGroupPropertyField2($name, $property_fields, $values)
	{
		if(!is_array($values)) $values = Array();

		$res = "";
		$result = "";
		$bWas = false;
		$sections = \CIBlockSection::GetTreeList(Array("IBLOCK_ID"=>$property_fields["LINK_IBLOCK_ID"]), array("ID", "NAME", "DEPTH_LEVEL"));
		while($ar = $sections->GetNext())
		{
			$res .= '<option value="'.$ar["ID"].'"';
			if(in_array($ar["ID"], $values))
			{
				$bWas = true;
				$res .= ' selected';
			}
			$res .= '>'.str_repeat(" . ", $ar["DEPTH_LEVEL"]).$ar["NAME"].'</option>';
		}
		$result .= '<select name="'.$name.'[]" size="'.($property_fields["MULTIPLE"]=="Y" ? "5":"1").'" '.($property_fields["MULTIPLE"]=="Y"?"multiple":"").'>';
		$result .= '<option value=""'.(!$bWas?' selected':'').'>'.Loc::getMessage("IBLOCK_ELEMENT_EDIT_NOT_SET").'</option>';
		$result .= $res;
		$result .= '</select>';
		return $result;
	}
	
	public static function AddFilter(&$arFilter, $arAddFilter)
	{
		$arAddFilter = unserialize(base64_decode($arAddFilter));
		if(!is_array($arFilter) || !is_array($arAddFilter)) return;
		
		$dbrFProps = \CIBlockProperty::GetList(array(), array("IBLOCK_ID"=>$arFilter['IBLOCK_ID'],"CHECK_PERMISSIONS"=>"N"));
		$arProps = array();
		while ($arProp = $dbrFProps->GetNext())
		{
			if ($arProp["ACTIVE"] == "Y")
			{
				$arProp["PROPERTY_USER_TYPE"] = ('' != $arProp["USER_TYPE"] ? \CIBlockProperty::GetUserType($arProp["USER_TYPE"]) : array());
				$arProps[] = $arProp;
			}
		}
		
		if(is_array($arAddFilter['find_section_section']))
		{
			if(count(array_diff($arAddFilter['find_section_section'], array('', '0' ,'-1'))) > 0)
			{
				$arFilter['SECTION_ID'] = array_diff($arAddFilter['find_section_section'], array('', '-1'));
			}
			elseif(in_array('-1', $arAddFilter['find_section_section']))
			{
				unset($arFilter["SECTION_ID"]);
			}
		}
		elseif(strlen($arAddFilter['find_section_section']) > 0 && (int)$arAddFilter['find_section_section'] >= 0) 
			$arFilter['SECTION_ID'] = $arAddFilter['find_section_section'];
		if($arAddFilter['find_el_subsections']=='Y')
		{
			if($arFilter['SECTION_ID']==0) unset($arFilter["SECTION_ID"]);
			else $arFilter["INCLUDE_SUBSECTIONS"] = "Y";
		}
		if(strlen($arAddFilter['find_el_modified_user_id']) > 0) $arFilter['MODIFIED_USER_ID'] = $arAddFilter['find_el_modified_user_id'];
		if(strlen($arAddFilter['find_el_modified_by']) > 0) $arFilter['MODIFIED_BY'] = $arAddFilter['find_el_modified_by'];
		if(strlen($arAddFilter['find_el_created_user_id']) > 0) $arFilter['CREATED_USER_ID'] = $arAddFilter['find_el_created_user_id'];
		if(strlen($arAddFilter['find_el_active']) > 0) $arFilter['ACTIVE'] = $arAddFilter['find_el_active'];
		if(strlen($arAddFilter['find_el_code']) > 0) $arFilter['?CODE'] = $arAddFilter['find_el_code'];
		self::AddFilterField($arFilter, $arAddFilter, 'EXTERNAL_ID', 'find_el_external_id', 'find_el_vtype_external_id');
		if(strlen($arAddFilter['find_el_tags']) > 0) $arFilter['?TAGS'] = $arAddFilter['find_el_tags'];
		if(strlen($arAddFilter['find_el_name']) > 0) $arFilter['?NAME'] = $arAddFilter['find_el_name'];
		if(strlen($arAddFilter['find_el_intext']) > 0) $arFilter['?DETAIL_TEXT'] = $arAddFilter['find_el_intext'];
		if($arAddFilter['find_el_preview_picture']=='Y') $arFilter['!PREVIEW_PICTURE'] =  false;
		elseif($arAddFilter['find_el_preview_picture']=='N') $arFilter['PREVIEW_PICTURE'] =  false;
		if($arAddFilter['find_el_detail_picture']=='Y') $arFilter['!DETAIL_PICTURE'] =  false;
		elseif($arAddFilter['find_el_detail_picture']=='N') $arFilter['DETAIL_PICTURE'] =  false;
		
		if(!empty($arAddFilter['find_el_id_start'])) $arFilter[">=ID"] = $arAddFilter['find_el_id_start'];
		if(!empty($arAddFilter['find_el_id_end'])) $arFilter["<=ID"] = $arAddFilter['find_el_id_end'];
		if(!empty($arAddFilter['find_el_timestamp_from'])) $arFilter["DATE_MODIFY_FROM"] = $arAddFilter['find_el_timestamp_from'];
		if(!empty($arAddFilter['find_el_timestamp_to'])) $arFilter["DATE_MODIFY_TO"] = \CIBlock::isShortDate($arAddFilter['find_el_timestamp_to'])? ConvertTimeStamp(AddTime(MakeTimeStamp($arAddFilter['find_el_timestamp_to']), 1, "D"), "FULL"): $arAddFilter['find_el_timestamp_to'];
		if(!empty($arAddFilter['find_el_created_from'])) $arFilter[">=DATE_CREATE"] = $arAddFilter['find_el_created_from'];
		if(!empty($arAddFilter['find_el_created_to'])) $arFilter["<=DATE_CREATE"] = \CIBlock::isShortDate($arAddFilter['find_el_created_to'])? ConvertTimeStamp(AddTime(MakeTimeStamp($arAddFilter['find_el_created_to']), 1, "D"), "FULL"): $arAddFilter['find_el_created_to'];
		if(!empty($arAddFilter['find_el_created_by']) && strlen($arAddFilter['find_el_created_by'])>0) $arFilter["CREATED_BY"] = $arAddFilter['find_el_created_by'];
		if(!empty($arAddFilter['find_el_date_active_from_from'])) $arFilter[">=DATE_ACTIVE_FROM"] = $arAddFilter['find_el_date_active_from_from'];
		if(!empty($arAddFilter['find_el_date_active_from_to'])) $arFilter["<=DATE_ACTIVE_FROM"] = $arAddFilter['find_el_date_active_from_to'];
		if(!empty($arAddFilter['find_el_date_active_to_from'])) $arFilter[">=DATE_ACTIVE_TO"] = $arAddFilter['find_el_date_active_to_from'];
		if(!empty($arAddFilter['find_el_date_active_to_to'])) $arFilter["<=DATE_ACTIVE_TO"] = $arAddFilter['find_el_date_active_to_to'];
		if (!empty($arAddFilter['find_el_catalog_type'])) $arFilter['CATALOG_TYPE'] = $arAddFilter['find_el_catalog_type'];
		if (!empty($arAddFilter['find_el_catalog_available'])) $arFilter['CATALOG_AVAILABLE'] = $arAddFilter['find_el_catalog_available'];
		if (!empty($arAddFilter['find_el_catalog_bundle'])) $arFilter['CATALOG_BUNDLE'] = $arAddFilter['find_el_catalog_bundle'];
		if (strlen($arAddFilter['find_el_catalog_quantity']) > 0)
		{
			$op = static::GetNumberOperation($arAddFilter['find_el_catalog_quantity'], $arAddFilter['find_el_catalog_quantity_comp']);
			$arFilter[$op.'CATALOG_QUANTITY'] = $arAddFilter['find_el_catalog_quantity'];
		}
		
		$arStoreKeys = preg_grep('/^find_el_catalog_store\d+_/', array_keys($arAddFilter));
		$arStoreKeys = array_unique(array_map(array(__CLASS__, 'ReplaceCatalogStore'), $arStoreKeys));
		if(!empty($arStoreKeys))
		{
			foreach($arStoreKeys as $storeKey)
			{
				if(strlen($arAddFilter['find_el_catalog_store'.$storeKey.'_quantity']) > 0)
				{
					$op = static::GetNumberOperation($arAddFilter['find_el_catalog_store'.$storeKey.'_quantity'], $arAddFilter['find_el_catalog_store'.$storeKey.'_quantity_comp']);
					$arFilter[$op.'CATALOG_STORE_AMOUNT_'.$storeKey] = $arAddFilter['find_el_catalog_store'.$storeKey.'_quantity'];
				}
			}
		}
		
		$arPriceKeys = preg_grep('/^find_el_catalog_price_\d+$/', array_keys($arAddFilter));
		$arPriceKeys = array_unique(array_map(array(__CLASS__, 'ReplaceCatalogPrice'), $arPriceKeys));
		if(!empty($arPriceKeys))
		{
			foreach($arPriceKeys as $priceKey)
			{
				if(strlen($arAddFilter['find_el_catalog_price_'.$priceKey]) > 0
					|| $arAddFilter['find_el_catalog_price_'.$priceKey.'_comp']=='empty')
				{
					$op = static::GetNumberOperation($arAddFilter['find_el_catalog_price_'.$priceKey], $arAddFilter['find_el_catalog_price_'.$priceKey.'_comp']);
					$arFilter[$op.'CATALOG_PRICE_'.$priceKey] = $arAddFilter['find_el_catalog_price_'.$priceKey];
				}
			}
		}
		
		foreach ($arProps as $arProp)
		{
			if ('Y' == $arProp["FILTRABLE"] && 'F' != $arProp["PROPERTY_TYPE"])
			{
				if (!empty($arProp['PROPERTY_USER_TYPE']) && isset($arProp["PROPERTY_USER_TYPE"]["AddFilterFields"]))
				{
					$fieldName = "filter_".$listIndex."_find_el_property_".$arProp["ID"];
					$GLOBALS[$fieldName] = $arAddFilter["find_el_property_".$arProp["ID"]];
					$GLOBALS['set_filter'] = 'Y';
					call_user_func_array($arProp["PROPERTY_USER_TYPE"]["AddFilterFields"], array(
						$arProp,
						array("VALUE" => $fieldName),
						&$arFilter,
						&$filtered,
					));
				}
				else
				{
					$value = $arAddFilter["find_el_property_".$arProp["ID"]];
					$vtype = $arAddFilter["find_el_vtype_property_".$arProp["ID"]];
					if(is_array($value)) $value = array_diff(array_map('trim', $value), array(''));
					if(strlen($vtype) > 0)
					{
						if($vtype=='empty') $arFilter["PROPERTY_".$arProp["ID"]] = false;
						elseif($vtype=='not_empty') $arFilter["!PROPERTY_".$arProp["ID"]] = false;
					}
					elseif((is_array($value) && count($value)>0) || (!is_array($value) && strlen($value)))
					{
						if(is_array($value))
						{
							foreach($value as $k=>$v)
							{
								if($v === "NOT_REF") $value[$k] = false;
							}
						}
						elseif($value === "NOT_REF") $value = false;
						if($arProp["PROPERTY_TYPE"]=='E' && $arProp["USER_TYPE"]=='')
						{
							$value = trim($value);
							if(preg_match('/[,;\s\|]/', $value))
							{
								$arFilter[] = array(
									'LOGIC'=>'OR', 
									array("=PROPERTY_".$arProp["ID"] => array_diff(array_map('trim', preg_split('/[,;\s\|]/', $value)), array(''))), 
									array("=PROPERTY_".$arProp["ID"].".NAME" => array_diff(array_map('trim', preg_split('/[,;\|]/', $value)), array('')))
								);
							}
							else 
							{
								$arFilter[] = array(
									'LOGIC'=>'OR', 
									array("=PROPERTY_".$arProp["ID"] => $value), 
									array("=PROPERTY_".$arProp["ID"].".NAME" => $value)
								);
							}
						}
						else
						{
							$arFilter["=PROPERTY_".$arProp["ID"]] = $value;
						}
					}
				}
			}
		}
	}
	
	public static function AddFilterField(&$arFilter, $arAddFilter, $fieldName, $filterName, $filterVtypeName)
	{
		$value = $arAddFilter[$filterName];
		$vtype = $arAddFilter[$filterVtypeName];
		if(is_array($value)) $value = array_diff(array_map('trim', $value), array(''));
		if($vtype=='empty') $arFilter[$fieldName] = false;
		elseif($vtype=='not_empty') $arFilter["!".$fieldName] = false;
		elseif((is_array($value) && !empty($value)) || strlen($value) > 0)
		{
			if($vtype=='contain') $arFilter["%".$fieldName] = $value;
			elseif($vtype=='not_contain') $arFilter["!%".$fieldName] = $value;
			elseif($vtype=='begin_with') $arFilter[$fieldName] = (is_array($value) ? array_map(array(__CLASS__, 'GetFilterBeginWith'), $value) : $value.'%');
			elseif($vtype=='end_on') $arFilter[$fieldName] = (is_array($value) ? array_map(array(__CLASS__, 'GetFilterEndOn'), $value) : '%'.$value);
			else $arFilter["=".$fieldName] = $value;
		}
	}
	
	public static function AddFilterHighload(&$arFilter, $arAddFilter, $HLBL_ID)
	{
		global $USER_FIELD_MANAGER;
		$arAddFilter = unserialize(base64_decode($arAddFilter));
		if(!is_array($arAddFilter)) return;
		
		$ufEntityId = 'HLBLOCK_'.$HLBL_ID;
		$arUserFields = $USER_FIELD_MANAGER->GetUserFields($ufEntityId, 0, LANGUAGE_ID);
		foreach($arUserFields as $FIELD_NAME=>$arUserField)
		{
			$key = 'find_'.$FIELD_NAME;
			if(array_key_exists($key, $arAddFilter))
			{
				$val = $arAddFilter[$key];
				$isVal = false;
				if(is_array($val))
				{
					$val = array_diff(array_map('trim', $val), array(''));
					if(!empty($val)) $isVal = true;
				}
				elseif(strlen(trim($val)) > 0) $isVal = true;

				if(in_array($arUserField["USER_TYPE_ID"], array('date', 'datetime')))
				{
					self::AddDateFilter($arFilter, $arAddFilter, '>='.$FIELD_NAME, '<='.$FIELD_NAME, "find_".$FIELD_NAME);
				}
				elseif($isVal)
				{
					if($arUserField["SHOW_FILTER"]=="I")
						$arFilter["=".$FIELD_NAME]=$val;
					elseif($arUserField["SHOW_FILTER"]=="S")
						$arFilter["%".$FIELD_NAME]=$val;
					else
						$arFilter[$FIELD_NAME]=$val;
				}
			}
		}	
	}
	
	public static function AddDateFilter(&$arFilter, $arAddFilter, $field1, $field2, $addField)
	{
		if($arAddFilter[$addField.'_from_FILTER_PERIOD']=='last_days'
			&& isset($arAddFilter[$addField.'_from_FILTER_LAST_DAYS']) && strlen(trim($arAddFilter[$addField.'_from_FILTER_LAST_DAYS'])) > 0)
		{
			$days = (int)trim($arAddFilter[$addField.'_from_FILTER_LAST_DAYS']);
			$arFilter[$field1] = $arAddFilter[$addField.'_from'] = ConvertTimeStamp(time()-$days*24*60*60, "FULL");
		}
		else
		{
			if(!empty($arAddFilter[$addField.'_from'])) $arFilter[$field1] = $arAddFilter[$addField.'_from'];
			if(!empty($arAddFilter[$addField.'_to'])) $arFilter[$field2] = \CIBlock::isShortDate($arAddFilter[$addField.'_to'])? ConvertTimeStamp(AddTime(MakeTimeStamp($arAddFilter[$addField.'_to']), 1, "D"), "FULL"): $arAddFilter[$addField.'_to'];
		}
	}
	
	public static function GetNumberOperation(&$val, $op)
	{
		if($op=='eq') return '=';
		elseif($op=='gt') return '>';
		elseif($op=='geq') return '>=';
		elseif($op=='lt') return '<';
		elseif($op=='leq') return '<=';
		elseif($op=='empty')
		{
			$val = false;
			return '';
		}
		else return '';
	}
	
	public static function ExportCsv($arResult)
	{
		require_once(dirname(__FILE__).'/PHPExcel/PHPExcel.php');
		$objPHPExcel = new \KDAPHPExcel();
		$arCols = range('A', 'Z');
		
		$row = 1;
		$worksheet = $objPHPExcel->getActiveSheet();
		foreach($arResult as $k=>$arFields)
		{
			$col = 0;
			foreach($arFields as $k=>$field)
			{
				$worksheet->setCellValueExplicit($arCols[$col++].$row, self::GetCsvCellValue($field, 'UTF-8'));
			}
			$row++;
		}
		$objWriter = \KDAPHPExcel_IOFactory::createWriter($objPHPExcel, 'CSV');
		$objWriter->setDelimiter(';');
		$objWriter->setEnclosure('"');
		$objWriter->setUseBOM(true);
		
		$tempPath = \CFile::GetTempName('', 'export.csv');
		$dir = \Bitrix\Main\IO\Path::getDirectory($tempPath);
		\Bitrix\Main\IO\Directory::createDirectory($dir);
		$objWriter->save($tempPath);
		
		$GLOBALS['APPLICATION']->RestartBuffer();
		ob_end_clean();
		header("Content-type: text/csv");
		header("Content-Disposition: attachment; filename=export.csv");
		header("Pragma: no-cache");
		header("Expires: 0");
		readfile($tempPath);
		die();
	}
	
	public static function ImportCsv($file)
	{
		require_once(dirname(__FILE__).'/PHPExcel/PHPExcel.php');
		$maxLine = 10000;
		$arLines = array();
		$objReader = \KDAPHPExcel_IOFactory::createReaderForFile($file);
		$efile = $objReader->load($file);
		foreach($efile->getWorksheetIterator() as $worksheet) 
		{
			$columns_count = max(\KDAPHPExcel_Cell::columnIndexFromString($worksheet->getHighestDataColumn()), $maxDrawCol);
			$columns_count = min($columns_count, 5000);
			$rows_count = $worksheet->getHighestDataRow();

			for ($row = 0; ($row < $rows_count && count($arLines) < $maxLine); $row++) 
			{
				$arLine = array();
				for($column = 0; $column < $columns_count; $column++) 
				{
					$val = $worksheet->getCellByColumnAndRow($column, $row+1);					
					$valText = self::GetCalculatedValue($val);
					$arLine[] = $valText;
				}

				if(count(array_diff($arLine, array(''))) > 0)
				{
					$arLines[] = $arLine;
				}
			}
		}
		return $arLines;
	}
	
	public static function GetCsvCellValue($val, $encoding='CP1251')
	{
		if($encoding=='CP1251')
		{
			if(defined('BX_UTF') && BX_UTF)
			{
				$val = $GLOBALS['APPLICATION']->ConvertCharset($val, 'UTF-8', 'CP1251');
			}
		}
		else
		{
			if(!defined('BX_UTF') || !BX_UTF)
			{
				$val = $GLOBALS['APPLICATION']->ConvertCharset($val, 'CP1251', 'UTF-8');
			}
		}
		return $val;
	}
	
	public static function GetCalculatedValue($val)
	{
		try{
			$val = $val->getFormattedValue();
		}catch(Exception $ex){}
		return self::CorrectCalculatedValue($val);
	}
	
	public static function CorrectCalculatedValue($val)
	{
		$val = str_ireplace('_x000D_', '', $val);
		if((!defined('BX_UTF') || !BX_UTF) && \CUtil::DetectUTF8($val))
		{
			if(function_exists('iconv'))
			{
				$newVal = iconv("UTF-8", "CP1251//IGNORE", $val);
				if(strlen(trim($newVal))==0 && strlen(trim($val))>0)
				{
					$newVal2 = utf8win1251($val);
					if(strpos(trim($newVal2), '?')!==0) $newVal = $newVal2;
				}
				$val = $newVal;
			}
			else $val = utf8win1251($val);
		}
		return $val;
	}
	
	public static function RemoveTmpFiles($maxTime = 5, $suffix='')
	{
		/*Check cron settings*/
		if(\Bitrix\Main\Config\Option::get(static::$moduleId, 'CRON_WO_MBSTRING', '')!='Y' && \Bitrix\EsolImportxml\ClassManager::VersionGeqThen('main', '20.100.0'))
		{
			\Bitrix\Main\Config\Option::set(static::$moduleId, 'CRON_WO_MBSTRING', 'Y');
			$arLines = array();
			if(function_exists('exec')) @exec('crontab -l', $arLines);
			if(is_array($arLines) && count($arLines) > 0)
			{
				$isChange = false;
				foreach($arLines as $k=>$v)
				{
					if(strpos($v, static::$moduleId)!==false && preg_match('/\-d\s+mbstring.func_overload=\d+/', $v))
					{
						$v = preg_replace('/\-d\s+mbstring.func_overload=\d+/', '-d default_charset='.self::getSiteEncoding(), $v);
						$v = preg_replace('/\s+\-d\s+mbstring.internal_encoding=\S+/', '', $v);
						$arLines[$k] = $v;
						$isChange = true;
					}
				}
				if($isChange)
				{
					$cfg_data = implode("\n", $arLines);
					$cfg_data = preg_replace("#\n{3,}#im", "\n\n", $cfg_data);
					$cfg_data = trim($cfg_data, "\r\n ")."\n";
					if(true /*file_exists($_SERVER["DOCUMENT_ROOT"]."/bitrix/crontab/crontab.cfg")*/)
					{
						CheckDirPath($_SERVER["DOCUMENT_ROOT"]."/bitrix/crontab/");
						file_put_contents($_SERVER["DOCUMENT_ROOT"]."/bitrix/crontab/crontab.cfg", $cfg_data);
					}
					$arRetval = array();
					@exec("crontab ".$_SERVER["DOCUMENT_ROOT"]."/bitrix/crontab/crontab.cfg", $arRetval, $return_var);
				}
			}
		}
		/*/Check cron settings*/
		
		$oProfile = \Bitrix\EsolImportxml\Profile::getInstance($suffix);
		$timeBegin = time();
		$docRoot = $_SERVER["DOCUMENT_ROOT"];
		$tmpDir = $docRoot.'/upload/tmp/'.static::$moduleId.'/';
		$arOldDirs = array();
		$arActDirs = array();
		if(file_exists($tmpDir) && ($dh = opendir($tmpDir))) 
		{
			while(($file = readdir($dh)) !== false) 
			{
				if(in_array($file, array('.', '..'))) continue;
				if(is_dir($tmpDir.$file))
				{
					if(!in_array($file, $arActDirs) && (time() - filemtime($tmpDir.$file) > 24*60*60))
					{
						$arOldDirs[] = $file;
					}
				}
				elseif(mb_substr($file, -4)=='.txt')
				{
					$arParams = $oProfile->GetProfileParamsByFile($tmpDir.$file);
					if(is_array($arParams) && isset($arParams['tmpdir']))
					{
						$actDir = preg_replace('/^.*\/([^\/]+)$/', '$1', trim($arParams['tmpdir'], '/'));
						$arActDirs[] = $actDir;
					}
				}
			}
			$arOldDirs = array_diff($arOldDirs, $arActDirs);
			foreach($arOldDirs as $subdir)
			{
				$oldDir = substr($tmpDir, strlen($docRoot)).$subdir;
				DeleteDirFilesEx($oldDir);
				if(($maxTime > 0) && (time() - $timeBegin >= $maxTime)) return;
			}
			closedir($dh);
		}
		
		$tmpDir = $docRoot.'/upload/tmp/';
		if(file_exists($tmpDir) && ($dh = opendir($tmpDir))) 
		{
			while(($file = readdir($dh)) !== false) 
			{
				if(!preg_match('/^[0-9a-z]{3}$/', $file))continue;
				$subdir = $tmpDir.$file;
				if(is_dir($subdir))
				{
					$subdir .= '/';
					if(time() - filemtime($subdir) > 24*60*60)
					{
						if($dh2 = opendir($subdir))
						{
							$emptyDir = true;
							while(($file2 = readdir($dh2)) !== false)
							{
								if(in_array($file2, array('.', '..'))) continue;
								if(time() - filemtime($subdir) > 24*60*60)
								{
									if(is_dir($subdir.$file2))
									{
										$oldDir = substr($subdir.$file2, strlen($docRoot));
										DeleteDirFilesEx($oldDir);
									}
									else
									{
										unlink($subdir.$file2);
									}
								}
								else
								{
									$emptyDir = false;
								}
							}
							closedir($dh2);
							if($emptyDir)
							{
								//unlink($subdir);
								rmdir($subdir);
							}
						}
						
						if(($maxTime > 0) && (time() - $timeBegin >= $maxTime)) return;
					}
				}
			}
			closedir($dh);
		}
	}
	
	public static function GetXmlEncoding($fn)
	{
		$encoding = 'utf-8';
		$handle = fopen($fn, "r");
		while(!($str = trim(fgets($handle, 4096))) && (!feof($handle))) {}
		if(preg_match('/<\?xml[^>]*encoding\s*=\s*[\'"]([^\'"]*)[\'"]/Uis', $str, $m))
		{
			$encoding = ToLower($m[1]);
		}
		else
		{
			fseek($handle, 0);
			$contents = fread($handle, 262144);
			if(!\CUtil::DetectUTF8($contents) && (!function_exists('iconv') || iconv('CP1251', 'CP1251', $contents)==$contents))
			{
				$encoding = 'windows-1251';
			}
		}
		fclose($handle);
		if($encoding=='cp1251') $encoding = 'windows-1251';
		//if($encoding=='utf8') $encoding = 'utf-8';
		if($encoding != 'windows-1251') $encoding = 'utf-8';
		return $encoding;
	}
	
	public static function ConvertDataEncoding($val, $fileEncoding, $siteEncoding)
	{
		if($siteEncoding==$fileEncoding) return $val;
		$val = \Bitrix\EsolImportxml\Utils::ReplaceCpSpecChars($val, $siteEncoding);
		$val = \Bitrix\Main\Text\Encoding::convertEncodingArray($val, $fileEncoding, $siteEncoding);
		return $val;
	}
	
	public static function GetELinkedIblock($arProp)
	{
		if(!array_key_exists($arProp['ID'], self::$eLinkedIblocks))
		{
			self::$eLinkedIblocks[$arProp['ID']] = false;
			if($arProp['USER_TYPE'])
			{
				if($arProp['USER_TYPE']=='CitrusArealtyZhk')
				{
					if($arProp['IBLOCK_ID'] && ($arIblock = \CIblock::GetById($arProp['IBLOCK_ID'])->Fetch()) && ($arIblock2 = \CIBlock::GetList(array(), array('CODE'=>'complexes', 'SITE_ID'=>$arIblock['LID']))->Fetch()))
					{
						self::$eLinkedIblocks[$arProp['ID']] = $arIblock2['ID'];
					}
				}
				elseif($arProp['USER_TYPE']=='CitrusArealtyHouse')
				{
					if($arProp['IBLOCK_ID'] && ($arIblock = \CIblock::GetById($arProp['IBLOCK_ID'])->Fetch()) && ($arIblock2 = \CIBlock::GetList(array(), array('CODE'=>'houses', 'SITE_ID'=>$arIblock['LID']))->Fetch()))
					{
						self::$eLinkedIblocks[$arProp['ID']] = $arIblock2['ID'];
					}
				}
			}
		}
		return self::$eLinkedIblocks[$arProp['ID']];
	}
	
	public static function GetCurUserID()
	{
		global $USER;
		if($USER && is_callable(array($USER, 'GetID'))) return $USER->GetID();
		else return 0;
	}
	
	public static function Trim($str)
	{
		if(is_array($str))
		{
			foreach($str as $k=>$v)
			{
				$str[$k] = self::Trim($v);
			}
			return $str;
		}
		$str = trim($str);
		$str = preg_replace('/(^(\xC2\xA0|\s)+|(\xC2\xA0|\s)+$)/s', '', $str);
		return $str;
	}
	
	public static function Translate($string, $langFrom, $langTo=false)
	{
		if(strlen(trim($string)) == 0) return $string;
		if($apiKey = \Bitrix\Main\Config\Option::get(static::$moduleId, 'TRANSLATE_GOOGLE_KEY', ''))
		{
			if($langTo===false) $langTo = LANGUAGE_ID;
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
			$res = $client->post('https://translation.googleapis.com/language/translate/v2', array('q'=>$string, 'source'=>$langFrom, 'target'=>$langTo, 'format'=>"text", 'key'=>$apiKey));
			$arRes = \CUtil::JSObjectToPhp($res);
			if(isset($arRes['data']['translations'][0]['translatedText']))
			{
				$string = (is_array($arRes['data']['translations'][0]['translatedText']) ? implode('', $arRes['data']['translations'][0]['translatedText']) : $arRes['data']['translations'][0]['translatedText']);
			}
		}
		elseif(($apiKey = \Bitrix\Main\Config\Option::get('main', 'translate_key_yandex', '')) ||
			($apiKey = \Bitrix\Main\Config\Option::get(static::$moduleId, 'TRANSLATE_YANDEX_KEY', '')))
		{
			if($langTo===false) $langTo = LANGUAGE_ID;
			$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
			$client->setHeader('Content-Type', 'application/xml');
			$res = $client->get('https://translate.yandex.net/api/v1.5/tr.json/translate?key='.$apiKey.'&lang='.$langFrom.'-'.$langTo.'&text='.urlencode($string));
			$arRes = \CUtil::JSObjectToPhp($res);
			if(array_key_exists('code', $arRes) && $arRes['code']==200 && array_key_exists('text', $arRes))
			{
				$string = (is_array($arRes['text']) ? implode('', $arRes['text']) : $arRes['text']);
			}
		}
		return $string;
	}
	
	public static function Str2Url($string, $arParams=array())
	{
		if(!is_array($arParams)) $arParams = array();
		if($arParams['USE_GOOGLE']=='Y') $string = self::Translate($string, LANGUAGE_ID, 'en');
		if($arParams['TRANSLITERATION']=='Y')
		{
			if(isset($arParams['TRANS_LEN'])) $arParams['max_len'] = $arParams['TRANS_LEN'];
			if(isset($arParams['TRANS_CASE'])) $arParams['change_case'] = $arParams['TRANS_CASE'];
			if(isset($arParams['TRANS_SPACE'])) $arParams['replace_space'] = $arParams['TRANS_SPACE'];
			if(isset($arParams['TRANS_OTHER'])) $arParams['replace_other'] = $arParams['TRANS_OTHER'];
			if(isset($arParams['TRANS_EAT']) && $arParams['TRANS_EAT']=='N') $arParams['delete_repeat_replace'] = false;
		}
		return \CUtil::translit($string, LANGUAGE_ID, $arParams);
	}
	
	public static function DownloadTextTextByLink($val, $altVal='')
	{
		$client = new \Bitrix\Main\Web\HttpClient(array('socketTimeout'=>10, 'disableSslVerification'=>true));
		$path = (strlen(trim($altVal)) > 0 ? trim($altVal) : trim($val));
		if(strlen($path)==0) return '';
		$arUrl = parse_url($path);
		$res = trim($client->get($path));
		if($client->getStatus()==404) $res = '';
		$hct = ToLower($client->getHeaders()->get('content-type'));
		$siteEncoding = $fileEncoding = self::getSiteEncoding();
		if(strlen($res) > 0 && class_exists('\DOMDocument') && $arUrl['fragment'])
		{
			$res = self::GetHtmlDomVal($res, $arUrl['fragment']);
		}
		elseif(preg_match('/charset=(.+)(;|$)/Uis', $hct, $m))
		{
			$fileEncoding = ToLower(trim($m[1]));
			if($siteEncoding!=$fileEncoding)
			{
				$res = \Bitrix\Main\Text\Encoding::convertEncoding($res, $fileEncoding, $siteEncoding);
			}
		}
		else
		{
			if(\CUtil::DetectUTF8($res))
			{
				if($siteEncoding!='utf-8') $res = \Bitrix\Main\Text\Encoding::convertEncoding($res, 'utf-8', $siteEncoding);
			}
			elseif($siteEncoding=='utf-8') $res = \Bitrix\Main\Text\Encoding::convertEncoding($res, 'cp1251', $siteEncoding);
		}
		return $res;
	}
	
	public static function GetHtmlDomVal($html, $selector, $img=false, $multi=false)
	{
		$finalHtml = '';
		if(strlen($html) > 0 && class_exists('\DOMDocument') && $selector)
		{
			if($multi && !$img) $multi = false;
			/*Bom UTF-8*/
			if(\CUtil::DetectUTF8(substr($html, 0, 10000)) && (substr($html, 0, 3)!="\xEF\xBB\xBF"))
			{
				$html = "\xEF\xBB\xBF".$html;
			}
			/*/Bom UTF-8*/
			$doc = new \DOMDocument();
			$doc->preserveWhiteSpace = false;
			$doc->formatOutput = true;
			$doc->loadHTML($html);
			$node = $doc;
			$arNodes = array();
			$arParts = preg_split('/\s+/', $selector);
			$i = 0;
			while(isset($arParts[$i]) && ($node instanceOf \DOMDocument || $node instanceOf \DOMElement))
			{
				$part = $arParts[$i];
				$tagName = (preg_match('/^([^#\.\[]+)([#\.\[].*$|$)/', $part, $m) ? $m[1] : '');
				$tagId = (preg_match('/^[^#]*#([^#\.\[]+)([#\.\[].*$|$)/', $part, $m) ? $m[1] : '');
				$arClasses = array_diff(explode('.', (preg_match('/^[^\.]*\.([^#\[]+)([#\.\[].*$|$)/', $part, $m) ? $m[1] : '')), array(''));
				$arAttributes = array_map(array(__CLASS__, 'StrKeyVal2Array'), (preg_match_all('/\[([^\]]+(=[^\]])?)\]/', $part, $m) ? $m[1] : array()));
				if($tagName)
				{
					$nodes = $node->getElementsByTagName($tagName);
					if($tagId || !empty($arClasses) || !empty($arAttributes))
					{
						$find = false;
						$key = 0;
						while((!$find || $multi) && $key<$nodes->length)
						{
							$node1 = $nodes->item($key);
							$subfind = true;
							if($tagId && $node1->getAttribute('id')!=$tagId) $subfind = false;
							foreach($arClasses as $className)
							{
								if($className && !preg_match('/(^|\s)'.preg_quote($className, '/').'(\s|$)/is', $node1->getAttribute('class'))) $subfind = false;
							}
							foreach($arAttributes as $arAttr)
							{
								if(!$node1->hasAttribute($arAttr['k']) || (strlen($arAttr['v']) > 0 && $node1->getAttribute($arAttr['k'])!=$arAttr['v'])) $subfind = false;
							}
							$find = $subfind;
							if($multi && $subfind) $arNodes[] = $nodes->item($key);
							if(!$find || $multi) $key++;
						}
						if($find && !$multi) $node = $nodes->item($key);
						else $node = null;
					}
					else
					{
						$node = $nodes->item(0);
					}
				}
				$i++;
			}
			
			if($img && $multi && count($arNodes) > 0)
			{
				$arLinks = array();
				foreach($arNodes as $node)
				{
					if($node instanceOf \DOMElement)
					{
						$link = '';
						if($node->hasAttribute('src')) $link = $node->getAttribute('src');
						elseif($node->hasAttribute('href')) $link = $node->getAttribute('href');
						$link = trim($link);
						if(strlen($link) > 0) $arLinks[] = $link;
					}
				}
				return $arLinks;
			}
			
			if($node instanceOf \DOMElement)
			{
				$innerHTML = '';
				if($img)
				{
					if($node->hasAttribute('src')) $innerHTML = $node->getAttribute('src');
					elseif($node->hasAttribute('href')) $innerHTML = $node->getAttribute('href');
				}
				else
				{
					$children = $node->childNodes;
					foreach($children as $child)
					{
						$innerHTML .= $child->ownerDocument->saveHTML($child);
					}
					if(strlen($innerHTML)==0 && $node->nodeValue) $innerHTML = $node->nodeValue;
				}
				$finalHtml = trim($innerHTML);
			}
			else
			{
				$finalHtml = '';
			}
			$siteEncoding = self::getSiteEncoding();
			if($finalHtml && $siteEncoding!='utf-8')
			{
				$finalHtml = \Bitrix\Main\Text\Encoding::convertEncoding($res, 'utf-8', $siteEncoding);
			}
		}
		return $finalHtml;
	}
	
	public static function DownloadImagesFromText($val, $domain='')
	{
		$domain = trim($domain);
		$imgDir = '/upload/esol_images/';
		$arPatterns = array(
			'/<img[^>]*\ssrc=["\']([^"\'<>]+)["\'][^>]*>/Uis',
			'/<a[^>]*\shref=["\']([^"\'<>]+\.(jpg|jpeg|png|gif|svg|webp|bmp|pdf)(\?[^"\']*)?)["\'][^>]*>/Uis',
		);
		foreach($arPatterns as $k0=>$pattern)
		{
			if(preg_match_all($pattern, $val, $m))
			{
				foreach($m[1] as $k=>$img)
				{
					if(mb_strpos($img, '//')===0) $img = (($pos = mb_strpos($domain, '//'))!==false ? mb_substr($domain, 0, $pos) : 'http:').$img;
					elseif(mb_strpos($img, '/')===0) $img = $domain.$img;
					$imgName = md5($img).'.'.preg_replace('/[#\?].*$/', '', bx_basename(rawurldecode($img)));
					$imgPathDir1 = $imgDir.mb_substr($imgName, 0, 3).'/';
					$imgPathDir = $_SERVER['DOCUMENT_ROOT'].$imgPathDir1;
					$imgPath1 = $imgPathDir1.$imgName;
					$imgPath = $imgPathDir.$imgName;
					$realFile = \Bitrix\Main\IO\Path::convertLogicalToPhysical($imgPath);
					$removeTag = false;
					if(!file_exists($realFile) || filesize($realFile)==0 || stripos(file_get_contents($realFile, false, null, 0, 100), '<html')!==false)
					{
						CheckDirPath($imgPathDir);
						$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>true, 'socketTimeout'=>15, 'streamTimeout'=>15));
						$ob->setHeader('User-Agent', self::GetUserAgent());
						$ob->download($img, $imgPath);
						if($k0==0 && ($ob->getStatus()==404 || (!file_exists($realFile) || filesize($realFile)==0 || stripos(file_get_contents($realFile, false, null, 0, 100), '<html')!==false)))
						{
							if(file_exists($realFile)) unlink($realFile);
							$removeTag = true;
						}
					}
					$imgHtml = str_replace($m[1][$k], $imgPath1, $m[0][$k]);
					if($removeTag) $val = str_replace($m[0][$k], '', $val);
					else $val = str_replace($m[0][$k], $imgHtml, $val);
				}
			}
		}
		return $val;
	}
	
	public static function PrepareJs()
	{
		$curFilename = end(explode('/', $_SERVER['SCRIPT_NAME']));
		if(file_exists($_SERVER["DOCUMENT_ROOT"].BX_ROOT.'/modules/'.static::$moduleId.'/install/admin/'.$curFilename))
		{
			AddEventHandler("main", "OnEndBufferContent", Array("\Bitrix\EsolImportxml\Utils", "PrepareJsDirect"));
		}
	}
	
	public static function PrepareJsDirect(&$content)
	{
		static::$jsCounter = 0;
		$content = preg_replace_callback('/<script[^>]+src="[^"]*\/js\/main\/jquery\/jquery\-[\d\.]+(\.min)+\.js[^"]*"[^>]*>\s*<\/script>/Uis', Array("\Bitrix\EsolImportxml\Utils", "DeleteExcellJs"), $content);
	}
	
	public static function DeleteExcellJs($m)
	{
		if(static::$jsCounter++==0) return $m[0];
		else return '';
	}
	
	public static function ReplaceCpSpecChars($val, $toEncoding)
	{
		if(!in_array($toEncoding, array('windows-1251', 'cp1251'))) return $val;
		if(is_array($val))
		{
			foreach($val as $k=>$v)
			{
				$val[$k] = self::ReplaceCpSpecChars($v, $toEncoding);
			}
			return $val;
		}
		$specChars = array('Ø'=>'&#216;', '™'=>'&#153;', '®'=>'&#174;', '©'=>'&#169;', 'Ö'=>'&#214;');
		if(!isset(static::$cpSpecCharLetters))
		{
			$cpSpecCharLetters = array();
			foreach($specChars as $char=>$code)
			{
				$letter = false;
				$pos = 0;
				for($i=192; $i<255; $i++)
				{
					$tmpLetter = \Bitrix\Main\Text\Encoding::convertEncoding(chr($i), 'CP1251', 'UTF-8');
					$tmpPos = strpos($tmpLetter, $char);
					if($tmpPos!==false)
					{
						$letter = $tmpLetter;
						$pos = $tmpPos;
					}
				}
				$cpSpecCharLetters[$char] = array('letter'=>$letter, 'pos'=>$pos);
			}
			static::$cpSpecCharLetters = $cpSpecCharLetters;
		}
		
		foreach($specChars as $char=>$code)
		{
			if(strpos($val, $char)===false) continue;
			$letter = static::$cpSpecCharLetters[$char]['letter'];
			$pos = static::$cpSpecCharLetters[$char]['pos'];

			if($letter!==false)
			{
				if($pos==0) $val = preg_replace('/'.mb_substr($letter, 0, 1).'(?!'.mb_substr($letter, 1, 1).')/', $code, $val);
				elseif($pos==1) $val = preg_replace('/(?<!'.mb_substr($letter, 0, 1).')'.mb_substr($letter, 1, 1).'/', $code, $val);
			}
			else
			{
				$val = str_replace($char, $code, $val);
			}
		}
		return $val;
	}
	
	public static function GetIniAbsVal($param)
	{
		$val = ToUpper(ini_get($param));
		if(substr($val, -1)=='K') $val = (float)$val*1024;
		elseif(substr($val, -1)=='M') $val = (float)$val*1024*1024;
		elseif(substr($val, -1)=='G') $val = (float)$val*1024*1024*1024;
		else $val = (float)$val;
		return $val;
	}
	
	public static function getUtfModifier()
	{
		if(self::getSiteEncoding()=='utf-8') return 'u';
		else return '';
	}
	
	public static function getSiteEncoding()
	{
		if(!isset(static::$siteEncoding))
		{
			if (defined('BX_UTF'))
				$logicalEncoding = "utf-8";
			elseif (defined("SITE_CHARSET") && (strlen(SITE_CHARSET) > 0))
				$logicalEncoding = SITE_CHARSET;
			elseif (defined("LANG_CHARSET") && (strlen(LANG_CHARSET) > 0))
				$logicalEncoding = LANG_CHARSET;
			elseif (defined("BX_DEFAULT_CHARSET"))
				$logicalEncoding = BX_DEFAULT_CHARSET;
			else
				$logicalEncoding = "windows-1251";

			static::$siteEncoding = trim(strtolower($logicalEncoding));
		}
		return static::$siteEncoding;
	}
	
	public static function GetHttpClient($arParams=false, $arHeaders=array(), $arCookies=array(), $path='')
	{
		if(!is_array($arParams)) $arParams = array('disableSslVerification'=>true);
		$arParams['useProxy'] = false;
		$client = new \Bitrix\EsolImportxml\HttpClient($arParams);
		if(is_array($arCookies) && count($arCookies) > 0) $client->setCookies($arCookies);
		if(!is_array($arHeaders)) $arHeaders = array();
		$arHeadersOrig = $arHeaders;
		$arHeaders = array();
		if(!array_key_exists('Host', $arHeadersOrig) && strlen($path) > 0)
		{
			$arUrl = parse_url($path);
			if(array_key_exists('host', $arUrl) && strlen($arUrl['host']) > 0)
			{
				$arHeaders['Host'] = $arUrl['host'];
			}
		}
		else
		{
			$arHeaders['Host'] = $arHeadersOrig['Host'];
			unset($arHeadersOrig['Host']);
		}
		foreach($arHeadersOrig as $hk=>$hv) $arHeaders[$hk] = $hv;
		foreach($arHeaders as $hk=>$hv) $client->setHeader($hk, $hv);

		return $client;
	}
	
	public static function GetUserAgent()
	{
		if(empty(self::$arAgents))
		{
			self::$arAgents = array(
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0',
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/80.0',
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/79.0',
				'Mozilla/5.0 (Windows NT 6.3; Win64; x64; rv:77.0) Gecko/20100101 Firefox/77.0',
				'Mozilla/5.0 (Windows NT 6.3; Win64; x64; rv:76.0) Gecko/20100101 Firefox/76.0',
			);
			self::$countAgents = count(self::$arAgents);
		}
		return self::$arAgents[rand(0, self::$countAgents - 1)];
	}
	
	public static function GetFloatRoundVal($val)
	{
		if(($ar = explode('.', $val)) && count($ar)>1){$val = round($val, strlen($ar[1]));}
		return $val;
	}
	
	public static function WordWithNum($num, $word)
	{
		list($word1, $word2, $word3) = array_map('trim', explode(',', $word));
		if($num%10==0 || $num%10>4 || ($num%100>10 && $num%100<20)) return $word3;
		elseif($num%10==1) return $word1;
		else return $word2;
	}
	
	public static function ArrayUnique($val)
	{
		if(!is_array($val)) return $val;
		$arVals = array();
		foreach($val as $k=>$v)
		{
			$sv = (is_array($v) ? serialize($v) : (string)($v));
			if(!in_array($sv, $arVals)) $arVals[] = $sv;
			else unset($val[$k]);
		}
		return array_values($val);
	}
	
	public static function UrlEncodeCallback($m)
	{
		return rawurlencode($m[0]);
	}
	
	public static function SortByFilemtime($a, $b)
	{
		return filemtime($a)>filemtime($b) ? -1 : 1;
	}
	
	public static function RemoveDocRoot($n)
	{
		return substr($n, strlen($_SERVER["DOCUMENT_ROOT"]));
	}
	
	public static function ArrStringToBool(&$n, $k)
	{
		if($n=="true"){$n=true;}elseif($n=="false"){$n=false;}
	}
	
	public static function ReplaceCatalogStore($n)
	{
		return preg_replace("/^find_el_catalog_store(\d+)_.*$/", "$1", $n);
	}
	
	public static function ReplaceCatalogPrice($n)
	{
		return preg_replace("/^find_el_catalog_price_(\d+)$/", "$1", $n);
	}
	
	public static function GetFilterBeginWith($n)
	{
		return $n."%";
	}
	
	public static function GetFilterEndOn($n)
	{
		return "%".$n;
	}
	
	public static function ArrayCombine($k, $v)
	{
		return array($k=>$v);
	}
	
	public static function SetConvType0($c)
	{
		if(strlen(trim($c["CELL"]))==0) $c["CELL"] = '0';
		$c["CONV_TYPE"]=0; return $c;
	}
	
	public static function SetConvType1($c)
	{
		if(strlen(trim($c["CELL"]))==0) $c["CELL"] = '0';
		$c["CONV_TYPE"]=1; return $c;
	}
	
	public static function CompEmptyString($n)
	{
		return (is_string($n) && $n==false ? 1 : $n);
	}
	
	public static function Vars2Json($k, $v)
	{
		return '"'.addcslashes($k, '"').'":"'.addcslashes($v, '"').'"';
	}
	
	public static function GetValBeforeEq($n)
	{
		return current(explode("=", $n, 2));
	}
	
	public static function GetValAfterEq($n)
	{
		return end(explode("=", $n, 2));
	}
	
	public static function KeyEqVal($k, $v)
	{
		return $k."=".$v;
	}
	
	public static function StrKeyVal2Array($n)
	{
		list($k, $v) = explode("=", $n, 2); 
		return array("k"=>$k, "v"=>trim($v, ' "\''));
	}
}
?>