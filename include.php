<?php
/**
 * Copyright (c) 4/8/2019 Created By/Edited By ASDAFF asdaff.asad@yandex.ru
 */

include_once(dirname(__FILE__).'/install/demo.php');

if(!class_exists('CIxmlImportXMLRunner'))
{
	class CIxmlImportXMLRunner
	{
		protected static $moduleId = 'ixml.importxml';
		
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
			$ie = new \Bitrix\IxmlImportxml\Importer($filename, $params, $fparams, $stepparams, $pid);
			return $ie->Import();
		}
		
		static function ImportHighloadblock($filename, $params, $fparams, $stepparams, $pid = false)
		{
			if(self::DemoExpired()) return array();
			$ie = new \Bitrix\IxmlImportxml\ImporterHl($filename, $params, $fparams, $stepparams, $pid);
			return $ie->Import();
		}
	}
}

$moduleId = CIxmlImportXMLRunner::GetModuleId();
$moduleJsId = str_replace('.', '_', $moduleId);
$pathJS = '/bitrix/js/'.$moduleId;
$pathCSS = '/bitrix/panel/'.$moduleId;
$pathLang = BX_ROOT.'/modules/'.$moduleId.'/lang/'.LANGUAGE_ID;
CModule::AddAutoloadClasses(
	$moduleId,
	array(
		'\Bitrix\IxmlImportxml\Profile' => "lib/profile.php",
		'\Bitrix\IxmlImportxml\ProfileTable' => "lib/profile_table.php",
		'\Bitrix\IxmlImportxml\ProfileHlTable' => "lib/profile_hl_table.php",
		'\Bitrix\IxmlImportxml\Utils' => "lib/utils.php",
		'\Bitrix\IxmlImportxml\Json2Xml' => "lib/json2xml.php",
		'\Bitrix\IxmlImportxml\Sftp' => "lib/sftp.php",
		'\Bitrix\IxmlImportxml\Conversion' => "lib/conversion.php",
		'\Bitrix\IxmlImportxml\Cloud' => "lib/cloud.php",
		'\Bitrix\IxmlImportxml\Cloud\MailRu' => "lib/cloud/mail_ru.php",
		'\Bitrix\IxmlImportxml\ZipArchive' => "lib/zip_archive.php",
		'\Bitrix\IxmlImportxml\XMLViewer' => "lib/xml_viewer.php",
		'\Bitrix\IxmlImportxml\FieldList' => "lib/field_list.php",
		'\Bitrix\IxmlImportxml\Importer' => "lib/importer.php",
		'\Bitrix\IxmlImportxml\ImporterHl' => "lib/importer_hl.php",
		'\Bitrix\IxmlImportxml\Logger' => "lib/logger.php",
		'\Bitrix\IxmlImportxml\Extrasettings' => "lib/extrasettings.php",
		'\Bitrix\IxmlImportxml\CFileInput' => "lib/file_input.php",
		'\Bitrix\IxmlImportxml\Imap' => "lib/mail/imap.php",
		'\Bitrix\IxmlImportxml\SMail' => "lib/mail/mail.php",
		'\Bitrix\IxmlImportxml\MailHeader' => "lib/mail/mail_header.php",
		'\Bitrix\IxmlImportxml\MailMessage' => "lib/mail/mail_message.php",
		'\Bitrix\IxmlImportxml\MailUtil' => "lib/mail/mail_util.php",
		'\Bitrix\IxmlImportxml\DataManager\Discount' => "lib/datamanager/discount.php",
		'\Bitrix\IxmlImportxml\DataManager\DiscountProductTable' => "lib/datamanager/discount_product_table.php",
		'\Bitrix\IxmlImportxml\DataManager\Price' => "lib/datamanager/price.php",
		'\Bitrix\IxmlImportxml\DataManager\PriceD7' => "lib/datamanager/price_d7.php",
		'\Bitrix\IxmlImportxml\DataManager\Product' => "lib/datamanager/product.php",
		'\Bitrix\IxmlImportxml\DataManager\ProductD7' => "lib/datamanager/product_d7.php",
		'\Bitrix\IxmlImportxml\DataManager\IblockElement' => "lib/datamanager/iblockelement.php",
		'\Bitrix\IxmlImportxml\ClassManager' => "lib/class_manager.php",
	)
);

$initFile = $_SERVER["DOCUMENT_ROOT"].BX_ROOT.'/php_interface/include/'.$moduleId.'/init.php';
if(file_exists($initFile)) include_once($initFile);

$arJSIxmlImportXmlConfig = array(
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

foreach ($arJSIxmlImportXmlConfig as $ext => $arExt) {
	CJSCore::RegisterExt($ext, $arExt);
}
?>