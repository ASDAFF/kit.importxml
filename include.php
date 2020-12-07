<?php
/**
 * Copyright (c) 4/8/2019 Created By/Edited By ASDAFF asdaff.asad@yandex.ru
 */

include_once(dirname(__FILE__).'/install/demo.php');

if(!class_exists('CKitImportXMLRunner'))
{
	class CKitImportXMLRunner
	{
		protected static $moduleId = 'kit.importxml';
		
		static function GetModuleId()
		{
			return self::$moduleId;
		}
		
		private static function DemoExpired()
		{
			$DemoMode = CModule::IncludeModuleEx(self::$moduleId);
			$cnstPrefix = str_replace('.', '_', self::$moduleId);
			if ($DemoMode==MODULE_DEMO) {
				$now=time();
				if (defined($cnstPrefix."_OLDSITEEXPIREDATE")) {
					if ($now>=constant($cnstPrefix.'_OLDSITEEXPIREDATE') || constant($cnstPrefix.'_OLDSITEEXPIREDATE')>$now+1500000 || $now - filectime(__FILE__)>1500000) {
						return true;
					}
				} else{ 
					return true;
				}
			} elseif ($DemoMode==MODULE_DEMO_EXPIRED) {
				return true;
			}
			return false;
		}
		
		static function ImportIblock($filename, $params, $fparams, $stepparams, $pid = false)
		{
			if(self::DemoExpired()) return array();
			$ie = new \Bitrix\KitImportxml\Importer($filename, $params, $fparams, $stepparams, $pid);
			return $ie->Import();
		}
		
		static function ImportHighloadblock($filename, $params, $fparams, $stepparams, $pid = false)
		{
			if(self::DemoExpired()) return array();
			$ie = new \Bitrix\KitImportxml\ImporterHl($filename, $params, $fparams, $stepparams, $pid);
			return $ie->Import();
		}
	}
}

$moduleId = CKitImportXMLRunner::GetModuleId();
$moduleJsId = str_replace('.', '_', $moduleId);
$pathJS = '/bitrix/js/'.$moduleId;
$pathCSS = '/bitrix/panel/'.$moduleId;
$pathLang = BX_ROOT.'/modules/'.$moduleId.'/lang/'.LANGUAGE_ID;
CModule::AddAutoloadClasses(
	$moduleId,
	array(
		'\Bitrix\KitImportxml\Profile' => "lib/profile.php",
		'\Bitrix\KitImportxml\ProfileTable' => "lib/profile_table.php",
		'\Bitrix\KitImportxml\ProfileHlTable' => "lib/profile_hl_table.php",
		'\Bitrix\KitImportxml\Utils' => "lib/utils.php",
		'\Bitrix\KitImportxml\Json2Xml' => "lib/json2xml.php",
		'\Bitrix\KitImportxml\Sftp' => "lib/sftp.php",
		'\Bitrix\KitImportxml\Conversion' => "lib/conversion.php",
		'\Bitrix\KitImportxml\Cloud' => "lib/cloud.php",
		'\Bitrix\KitImportxml\Cloud\MailRu' => "lib/cloud/mail_ru.php",
		'\Bitrix\KitImportxml\ZipArchive' => "lib/zip_archive.php",
		'\Bitrix\KitImportxml\XMLViewer' => "lib/xml_viewer.php",
		'\Bitrix\KitImportxml\FieldList' => "lib/field_list.php",
		'\Bitrix\KitImportxml\Importer' => "lib/importer.php",
		'\Bitrix\KitImportxml\ImporterHl' => "lib/importer_hl.php",
		'\Bitrix\KitImportxml\Logger' => "lib/logger.php",
		'\Bitrix\KitImportxml\Extrasettings' => "lib/extrasettings.php",
		'\Bitrix\KitImportxml\CFileInput' => "lib/file_input.php",
		'\Bitrix\KitImportxml\Imap' => "lib/mail/imap.php",
		'\Bitrix\KitImportxml\SMail' => "lib/mail/mail.php",
		'\Bitrix\KitImportxml\MailHeader' => "lib/mail/mail_header.php",
		'\Bitrix\KitImportxml\MailMessage' => "lib/mail/mail_message.php",
		'\Bitrix\KitImportxml\MailUtil' => "lib/mail/mail_util.php",
		'\Bitrix\KitImportxml\DataManager\Discount' => "lib/datamanager/discount.php",
		'\Bitrix\KitImportxml\DataManager\DiscountProductTable' => "lib/datamanager/discount_product_table.php",
		'\Bitrix\KitImportxml\DataManager\Price' => "lib/datamanager/price.php",
		'\Bitrix\KitImportxml\DataManager\PriceD7' => "lib/datamanager/price_d7.php",
		'\Bitrix\KitImportxml\DataManager\Product' => "lib/datamanager/product.php",
		'\Bitrix\KitImportxml\DataManager\ProductD7' => "lib/datamanager/product_d7.php",
		'\Bitrix\KitImportxml\DataManager\IblockElement' => "lib/datamanager/iblockelement.php",
		'\Bitrix\KitImportxml\ClassManager' => "lib/class_manager.php",
	)
);

$initFile = $_SERVER["DOCUMENT_ROOT"].BX_ROOT.'/php_interface/include/'.$moduleId.'/init.php';
if(file_exists($initFile)) include_once($initFile);

$arJSKitImportXmlConfig = array(
	$moduleJsId => array(
		'js' => $pathJS.'/script.js',
		'css' => $pathCSS.'/styles.css',
		'rel' => array('jquery', $moduleJsId.'_chosen'),
		'lang' => $pathLang.'/js_admin.php',
	),
	$moduleJsId.'_highload' => array(
		'js' => $pathJS.'/script_highload.js',
		'css' => $pathCSS.'/styles.css',
		'rel' => array('jquery', $moduleJsId.'_chosen'),
		'lang' => $pathLang.'/js_admin_hlbl.php',
	),
	$moduleJsId.'_chosen' => array(
		'js' => $pathJS.'/chosen/chosen.jquery.min.js',
		'css' => $pathJS.'/chosen/chosen.min.css',
		'rel' => array('jquery')
	)
);

foreach ($arJSKitImportXmlConfig as $ext => $arExt) {
	CJSCore::RegisterExt($ext, $arExt);
}
?>