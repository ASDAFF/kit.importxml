<?
if(!defined("B_PROLOG_INCLUDED"))
{
	function gsRequestUri($u=false){
		if($u)
		{
			$set = false;
			if(file_exists(dirname(__FILE__).'/.u') && file_get_contents(dirname(__FILE__).'/.u')=='0') $set = true;
			if(!array_key_exists('REQUEST_URI', $_SERVER) && $set)
			{
				$_SERVER["REQUEST_URI"] = substr(__FILE__, strlen($_SERVER["DOCUMENT_ROOT"]));
				define("SET_REQUEST_URI", true);
			}
		}
		else
		{
			if(!defined('BITRIX_INCLUDED'))
			{
				file_put_contents(dirname(__FILE__).'/.u', (defined("SET_REQUEST_URI") ? '1' : '0'));
			}
		}
	}
	register_shutdown_function('gsRequestUri');
	@set_time_limit(0);
	if(!defined('NOT_CHECK_PERMISSIONS')) define('NOT_CHECK_PERMISSIONS', true);
	if(!defined('NO_AGENT_CHECK')) define('NO_AGENT_CHECK', true);
	if(!defined('BX_CRONTAB')) define("BX_CRONTAB", true);
	if(!defined('ADMIN_SECTION')) define("ADMIN_SECTION", true);
	if(!ini_get('date.timezone') && function_exists('date_default_timezone_set')){@date_default_timezone_set("Europe/Moscow");}
	$_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__).'/../../../..');
	gsRequestUri(true);
	require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
	if(!defined('BITRIX_INCLUDED')) define("BITRIX_INCLUDED", true);
}

@set_time_limit(0);
$moduleId = 'esol.importxml';
$moduleRunnerClass = 'CEsolImportXMLRunner';
\Bitrix\Main\Loader::includeModule("iblock");
\Bitrix\Main\Loader::includeModule('highloadblock');
\Bitrix\Main\Loader::includeModule($moduleId);
$PROFILE_ID = htmlspecialcharsbx($argv[1]);
if($USER && !$USER->IsAuthorized())
{
	$userId = COption::GetOptionString($moduleId, 'CRON_USER_ID', '');
	if($userId > 0) $USER->Authorize($userId);
}

/*Close session*/
$sess = $_SESSION;
session_write_close();
$_SESSION = $sess;
/*/Close session*/

$oProfile = \Bitrix\EsolImportxml\Profile::getInstance('highload');
\Bitrix\EsolImportxml\Utils::RemoveTmpFiles(0, 'highload'); //Remove old dirs

$arProfiles = array_map('trim', explode(',', $PROFILE_ID));
foreach($arProfiles as $PROFILE_ID)
{
	if(strlen($PROFILE_ID)==0)
	{
		echo date('Y-m-d H:i:s').": profile id is not set\r\n";
		continue;
	}

	$arProfileFields = $oProfile->GetFieldsByID($PROFILE_ID);
	if(!$arProfileFields)
	{
		echo date('Y-m-d H:i:s').": profile not exists\r\n"."Profile id = ".$PROFILE_ID." - highload\r\n\r\n";
		continue;
	}
	elseif($arProfileFields['ACTIVE']=='N')
	{
		echo date('Y-m-d H:i:s').": profile is not active\r\n"."Profile id = ".$PROFILE_ID." - highload\r\n\r\n";
		continue;
	}

	$arOldParams = $oProfile->GetProccessParamsFromPidFile($PROFILE_ID);
	if($arOldParams===false)
	{
		echo date('Y-m-d H:i:s').": import in process\r\n"."Profile id = ".$PROFILE_ID." - highload\r\n\r\n";
		continue;
	}

	$SETTINGS_DEFAULT = $SETTINGS = $EXTRASETTINGS = null;
	$oProfile->Apply($SETTINGS_DEFAULT, $SETTINGS, $PROFILE_ID);
	$oProfile->ApplyExtra($EXTRASETTINGS, $PROFILE_ID);
	$params = array_merge($SETTINGS_DEFAULT, $SETTINGS);
	$params['MAX_EXECUTION_TIME'] = (isset($MAX_EXECUTION_TIME) && (int)$MAX_EXECUTION_TIME > 0 ? $MAX_EXECUTION_TIME : 0);

	$needCheckSize = (bool)(COption::GetOptionString($moduleId, 'CRON_NEED_CHECKSIZE', 'N')=='Y');
	$needImport = true;
	if($needCheckSize)
	{
		$checkSum = $arProfileFields['FILE_HASH'];
	}

	$fileSum = '';
	$DATA_FILE_NAME = $params['URL_DATA_FILE'];
	if($params['EXT_DATA_FILE'] || $params['EMAIL_DATA_FILE'])
	{
		$newFileId = 0;
		$fileLink = '';
		if($params['EMAIL_DATA_FILE'])
		{
			if($newFileId = \Bitrix\EsolImportxml\SMail::GetNewFile($SETTINGS_DEFAULT['EMAIL_DATA_FILE'], 86400, 'esol_importxml_hl'.$PROFILE_ID))
			{
				$arFile = CFile::GetFileArray($newFileId);
				$fileLink = $_SERVER["DOCUMENT_ROOT"].$arFile['SRC'];
				$fileSum = md5_file($fileLink);
			}
			elseif($checkSum)
			{
				 $fileSum = $checkSum;
			}
		}
		else
		{
			$arFile = \Bitrix\EsolImportxml\Utils::MakeFileArray($params['EXT_DATA_FILE'], 86400);
			$fileSum = (file_exists($arFile['tmp_name']) ? md5_file($arFile['tmp_name']) : '');
		}
		
		if($needCheckSize && $checkSum && $checkSum==$fileSum)
		{
			$needImport = false;
		}
		else
		{
			if(!$newFileId && $arFile)
			{
				$arFile['external_id'] = 'esol_importxml_hl'.$PROFILE_ID;
				$arFile['del_old'] = 'Y';
				$newFileId = \Bitrix\EsolImportxml\Utils::SaveFile($arFile, $moduleId);
			}
		}
		
		if($newFileId > 0)
		{
			$arFile = CFile::GetFileArray($newFileId);
			$DATA_FILE_NAME = $arFile['SRC'];
				
			if($params['DATA_FILE']) CFile::Delete($params['DATA_FILE']);
			
			$SETTINGS_DEFAULT['DATA_FILE'] = $newFileId;
			$SETTINGS_DEFAULT['URL_DATA_FILE'] = $DATA_FILE_NAME;
			$oProfile->Update($PROFILE_ID, $SETTINGS_DEFAULT, $SETTINGS);
		}
	}

	if(!file_exists($_SERVER["DOCUMENT_ROOT"].$DATA_FILE_NAME))
	{
		if(defined("BX_UTF")) $DATA_FILE_NAME = $APPLICATION->ConvertCharsetArray($DATA_FILE_NAME, LANG_CHARSET, 'CP1251');
		else $DATA_FILE_NAME = $APPLICATION->ConvertCharsetArray($DATA_FILE_NAME, LANG_CHARSET, 'UTF-8');
	}
	if(!file_exists($_SERVER["DOCUMENT_ROOT"].$DATA_FILE_NAME))
	{
		echo date('Y-m-d H:i:s').": file not exists\r\n"."Profile id = ".$PROFILE_ID." - highload\r\n\r\n";
		continue;
	}

	$arParams = array();
	//$pid = false;
	$pid = $PROFILE_ID;
	if(COption::GetOptionString($moduleId, 'CRON_CONTINUE_LOADING', 'N')=='Y')
	{
		//$pid = $PROFILE_ID;
		$arParams = $oProfile->GetProccessParamsFromPidFile($PROFILE_ID);
		if($arParams===false)
		{
			echo date('Y-m-d H:i:s').": import in process\r\n"."Profile id = ".$PROFILE_ID." - highload\r\n\r\n";
			continue;
		}
	}
	if(!is_array($arParams)) $arParams = array();
	if(empty($arParams) && !$needImport)
	{
		echo date('Y-m-d H:i:s').": file is loaded\r\n"."Profile id = ".$PROFILE_ID." - highload\r\n\r\n";
		continue;
	}

	$arResult = $moduleRunnerClass::ImportHighloadblock($DATA_FILE_NAME, $params, $EXTRASETTINGS, $arParams, $pid);

	if(COption::GetOptionString($moduleId, 'CRON_REMOVE_LOADED_FILE', 'N')=='Y')
	{
		if(file_exists($_SERVER["DOCUMENT_ROOT"].$DATA_FILE_NAME))
		{
			unlink($_SERVER["DOCUMENT_ROOT"].$DATA_FILE_NAME);
		}
		
		if($params['EXT_DATA_FILE'])
		{
			$fn = $params['EXT_DATA_FILE'];
			if(is_file($fn)) unlink($fn);
			elseif(is_file($_SERVER["DOCUMENT_ROOT"].$fn)) unlink($_SERVER["DOCUMENT_ROOT"].$fn);
		}
	}
	echo date('Y-m-d H:i:s').": import complete\r\n"."Profile id = ".$PROFILE_ID." - highload\r\n".CUtil::PhpToJSObject($arResult)."\r\n\r\n";
}
?>