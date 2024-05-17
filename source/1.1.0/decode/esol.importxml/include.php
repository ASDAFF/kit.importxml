<?php

include_once(dirname(__FILE__) . '/install/demo.php');
if (!class_exists('CEsolImportXMLRunner')) {
    class CEsolImportXMLRunner
    {
        protected static $_740205939 = 'esol.importxml';

        static function GetModuleId()
        {
            return self::$_740205939;
        }

        private static function __1804665561()
        {
            $_2101021088 = CModule::IncludeModuleEx(self::$_740205939);
            $_1782712899 = $GLOBALS['____470634741'][46]('.', '_', self::$_740205939);
            if ($_2101021088 == MODULE_DEMO) {
                $_2091567310 = $GLOBALS['____470634741'][47]();
                if ($GLOBALS['____470634741'][48]($_1782712899 . '_OLDSITEEXPIREDATE')) {
                    if ($_2091567310 >= $GLOBALS['____470634741'][49]($_1782712899 . '_OLDSITEEXPIREDATE') || $GLOBALS['____470634741'][50]($_1782712899 . '_OLDSITEEXPIREDATE') > $_2091567310 + 1500000 || $_2091567310 - $GLOBALS['____470634741'][51](__FILE__) > 1500000) {
                        return true;
                    }
                } else {
                    return true;
                }
            } elseif ($_2101021088 == MODULE_DEMO_EXPIRED) {
                return true;
            }
            return false;
        }

        static function ImportIblock($_652630355, $_468252204, $_126227307, $_181255615, $_1868353362 = false)
        {
            if (self::__1804665561()) return array();
            $_402184783 = new \Bitrix\EsolImportxml\Importer($_652630355, $_468252204, $_126227307, $_181255615, $_1868353362);
            return $_402184783->Import();
        }

        static function ImportHighloadblock($_652630355, $_468252204, $_126227307, $_181255615, $_1868353362 = false)
        {
            if (self::__1804665561()) return array();
            $_402184783 = new \Bitrix\EsolImportxml\ImporterHl($_652630355, $_468252204, $_126227307, $_181255615, $_1868353362);
            return $_402184783->Import();
        }
    }
}
$_740205939 = CEsolImportXMLRunner::GetModuleId();
$_1759196730 = str_replace('.', '_', $_740205939);
$_1649534020 = '/bitrix/js/' . $_740205939;
$_1633344426 = '/bitrix/panel/' . $_740205939;
$_1576939079 = BX_ROOT . '/modules/' . $_740205939 . '/lang/' . LANGUAGE_ID;
CModule::AddAutoloadClasses($_740205939, array('\Bitrix\EsolImportxml\Profile' => 'lib/profile.php', '\Bitrix\EsolImportxml\ProfileTable' => 'lib/profile_table.php', '\Bitrix\EsolImportxml\ProfileHlTable' => 'lib/profile_hl_table.php', '\Bitrix\EsolImportxml\Utils' => 'lib/utils.php', '\Bitrix\EsolImportxml\HttpClient' => 'lib/httpclient.php', '\Bitrix\EsolImportxml\Json2Xml' => 'lib/json2xml.php', '\Bitrix\EsolImportxml\ProfileElementTable' => 'lib/profile_element.php', '\Bitrix\EsolImportxml\ProfileElementHlTable' => 'lib/profile_element_hl.php', '\Bitrix\EsolImportxml\ProfileExecTable' => 'lib/profile_exec.php', '\Bitrix\EsolImportxml\ProfileExecStatTable' => 'lib/profile_exec_stat.php', '\Bitrix\EsolImportxml\Sftp' => 'lib/sftp.php', '\Bitrix\EsolImportxml\Conversion' => 'lib/conversion.php', '\Bitrix\EsolImportxml\Cloud' => 'lib/cloud.php', '\Bitrix\EsolImportxml\Cloud\MailRu' => 'lib/cloud/mail_ru.php', '\Bitrix\EsolImportxml\ZipArchive' => 'lib/zip_archive.php', '\Bitrix\EsolImportxml\XMLViewer' => 'lib/xml_viewer.php', '\Bitrix\EsolImportxml\FieldList' => 'lib/field_list.php', '\Bitrix\EsolImportxml\ImporterBase' => 'lib/importer_base.php', '\Bitrix\EsolImportxml\ImporterData' => 'lib/importer_data.php', '\Bitrix\EsolImportxml\Importer' => 'lib/importer.php', '\Bitrix\EsolImportxml\ImporterRollback' => 'lib/importer_rollback.php', '\Bitrix\EsolImportxml\ImporterHl' => 'lib/importer_hl.php', '\Bitrix\EsolImportxml\XMLReader' => 'lib/xmlreader.php', '\Bitrix\EsolImportxml\Logger' => 'lib/logger.php', '\Bitrix\EsolImportxml\Extrasettings' => 'lib/extrasettings.php', '\Bitrix\EsolImportxml\CFileInput' => 'lib/file_input.php', '\Bitrix\EsolImportxml\Imap' => 'lib/mail/imap.php', '\Bitrix\EsolImportxml\SMail' => 'lib/mail/mail.php', '\Bitrix\EsolImportxml\MailHeader' => 'lib/mail/mail_header.php', '\Bitrix\EsolImportxml\MailMessage' => 'lib/mail/mail_message.php', '\Bitrix\EsolImportxml\MailUtil' => 'lib/mail/mail_util.php', '\Bitrix\EsolImportxml\DataManager\Discount' => 'lib/datamanager/discount.php', '\Bitrix\EsolImportxml\DataManager\DiscountProductTable' => 'lib/datamanager/discount_product_table.php', '\Bitrix\EsolImportxml\DataManager\Price' => 'lib/datamanager/price.php', '\Bitrix\EsolImportxml\DataManager\PriceD7' => 'lib/datamanager/price_d7.php', '\Bitrix\EsolImportxml\DataManager\Product' => 'lib/datamanager/product.php', '\Bitrix\EsolImportxml\DataManager\ProductD7' => 'lib/datamanager/product_d7.php', '\Bitrix\EsolImportxml\DataManager\IblockElementTable' => 'lib/datamanager/iblockelement.php', '\Bitrix\EsolImportxml\DataManager\IblockElementIdTable' => 'lib/datamanager/iblockelementid_table.php', '\Bitrix\EsolImportxml\DataManager\ElementPropertyTable' => 'lib/datamanager/element_property_table.php', '\Bitrix\EsolImportxml\DataManager\InterhitedpropertyValues' => 'lib/datamanager/inheritedproperty_values.php', '\Bitrix\EsolImportxml\ClassManager' => 'lib/class_manager.php', '\Bitrix\EsolImportxml\Api' => 'lib/api.php', '\IX\Giftsru' => 'lib/vendors/gifts.ru.php', '\IX\B2bmerlioncom' => 'lib/vendors/b2b.merlion.com.php', '\IX\B2bhogartru' => 'lib/vendors/b2b.hogart.ru.php', '\IX\Apibraincomua' => 'lib/vendors/api.brain.com.ua.php', '\IX\Apiercua' => 'lib/vendors/api.erc.ua.php',));
$_81098143 = $_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/php_interface/include/' . $_740205939 . '/init.php';
if (file_exists($_81098143)) include_once($_81098143);
$_657323066 = (CJSCore::IsExtRegistered('jquery3') ? 'jquery3' : 'jquery2');
$_1152178283 = array($_1759196730 => array('js' => $_1649534020 . '/script.js', 'css' => $_1633344426 . '/styles.css', 'rel' => array($_657323066, $_1759196730 . '_chosen'), 'lang' => $_1576939079 . '/js_admin.php',), $_1759196730 . '_highload' => array('js' => $_1649534020 . '/script_highload.js', 'css' => $_1633344426 . '/styles.css', 'rel' => array($_657323066, $_1759196730 . '_chosen'), 'lang' => $_1576939079 . '/js_admin_hlbl.php',), $_1759196730 . '_chosen' => array('js' => $_1649534020 . '/chosen/chosen.jquery.min.js', 'css' => $_1649534020 . '/chosen/chosen.min.css', 'rel' => array($_657323066)));
foreach ($_1152178283 as $_944638528 => $_755393006) {
    CJSCore::RegisterExt($_944638528, $_755393006);
}
if (class_exists('\Bitrix\EsolImportxml\Utils')) \Bitrix\EsolImportxml\Utils::PrepareJs();