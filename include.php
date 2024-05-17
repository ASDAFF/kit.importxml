<?php
include_once(dirname(__FILE__) . '/install/demo.php');
if (!class_exists('CKitImportXMLRunner')) {
    class CKitImportXMLRunner
    {
        protected static $_2019323885 = 'kit.importxml';

        static function GetModuleId()
        {
            return self::$_2019323885;
        }

        private static function __1667935303()
        {
            $_38733226 = CModule::IncludeModuleEx(self::$_2019323885);
            $_789239635 = str_replace('.', '_', self::$_2019323885);
            if ($_38733226 == MODULE_DEMO) {
                $_289305976 = time();
                if (defined($_789239635 . '_OLDSITEEXPIREDATE')) {
                    if ($_289305976 >= constant($_789239635 . '_OLDSITEEXPIREDATE') || constant($_789239635 . '_OLDSITEEXPIREDATE') > $_289305976 + 1500000 || $_289305976 - filectime(__FILE__) > 1500000) {
                        return true;
                    }
                } else {
                    return true;
                }
            } elseif ($_38733226 == MODULE_DEMO_EXPIRED) {
                return true;
            }
            return false;
        }

        static function ImportIblock($_2029131979, $_1137406749, $_1334224997, $_1605213925, $_279272061 = false)
        {
            if (self::__1667935303()) return array();
            $_906924150 = new \Bitrix\KitImportxml\Importer($_2029131979, $_1137406749, $_1334224997, $_1605213925, $_279272061);
            return $_906924150->Import();
        }

        static function ImportHighloadblock($_2029131979, $_1137406749, $_1334224997, $_1605213925, $_279272061 = false)
        {
            if (self::__1667935303()) return array();
            $_906924150 = new \Bitrix\KitImportxml\ImporterHl($_2029131979, $_1137406749, $_1334224997, $_1605213925, $_279272061);
            return $_906924150->Import();
        }
    }
}
$_2019323885 = CKitImportXMLRunner::GetModuleId();
$_1938229550 = str_replace('.', '_', $_2019323885);
$_336247591 = '/bitrix/js/' . $_2019323885;
$_828554920 = '/bitrix/panel/' . $_2019323885;
$_987098826 = BX_ROOT . '/modules/' . $_2019323885 . '/lang/' . LANGUAGE_ID;
CModule::AddAutoloadClasses($_2019323885, array('\Bitrix\KitImportxml\Profile' => 'lib/profile.php', '\Bitrix\KitImportxml\ProfileTable' => 'lib/profile_table.php', '\Bitrix\KitImportxml\ProfileHlTable' => 'lib/profile_hl_table.php', '\Bitrix\KitImportxml\Utils' => 'lib/utils.php', '\Bitrix\KitImportxml\HttpClient' => 'lib/httpclient.php', '\Bitrix\KitImportxml\Json2Xml' => 'lib/json2xml.php', '\Bitrix\KitImportxml\ProfileElementTable' => 'lib/profile_element.php', '\Bitrix\KitImportxml\ProfileElementHlTable' => 'lib/profile_element_hl.php', '\Bitrix\KitImportxml\ProfileExecTable' => 'lib/profile_exec.php', '\Bitrix\KitImportxml\ProfileExecStatTable' => 'lib/profile_exec_stat.php', '\Bitrix\KitImportxml\ProfileChangesTable' => 'lib/profile_changes.php', '\Bitrix\KitImportxml\Sftp' => 'lib/sftp.php', '\Bitrix\KitImportxml\Conversion' => 'lib/conversion.php', '\Bitrix\KitImportxml\Cloud' => 'lib/cloud.php', '\Bitrix\KitImportxml\Cloud\MailRu' => 'lib/cloud/mail_ru.php', '\Bitrix\KitImportxml\ZipArchive' => 'lib/zip_archive.php', '\Bitrix\KitImportxml\XMLViewer' => 'lib/xml_viewer.php', '\Bitrix\KitImportxml\FieldList' => 'lib/field_list.php', '\Bitrix\KitImportxml\Filter' => 'lib/filter.php', '\Bitrix\KitImportxml\ImporterBase' => 'lib/importer_base.php', '\Bitrix\KitImportxml\ImporterData' => 'lib/importer_data.php', '\Bitrix\KitImportxml\Importer' => 'lib/importer.php', '\Bitrix\KitImportxml\ImporterRollback' => 'lib/importer_rollback.php', '\Bitrix\KitImportxml\ImporterHl' => 'lib/importer_hl.php', '\Bitrix\KitImportxml\XMLReader' => 'lib/xmlreader.php', '\Bitrix\KitImportxml\Logger' => 'lib/logger.php', '\Bitrix\KitImportxml\Extrasettings' => 'lib/extrasettings.php', '\Bitrix\KitImportxml\CFileInput' => 'lib/file_input.php', '\Bitrix\KitImportxml\Imap' => 'lib/mail/imap.php', '\Bitrix\KitImportxml\SMail' => 'lib/mail/mail.php', '\Bitrix\KitImportxml\MailHeader' => 'lib/mail/mail_header.php', '\Bitrix\KitImportxml\MailMessage' => 'lib/mail/mail_message.php', '\Bitrix\KitImportxml\MailUtil' => 'lib/mail/mail_util.php', '\Bitrix\KitImportxml\DataManager\Discount' => 'lib/datamanager/discount.php', '\Bitrix\KitImportxml\DataManager\DiscountProductTable' => 'lib/datamanager/discount_product_table.php', '\Bitrix\KitImportxml\DataManager\Price' => 'lib/datamanager/price.php', '\Bitrix\KitImportxml\DataManager\PriceD7' => 'lib/datamanager/price_d7.php', '\Bitrix\KitImportxml\DataManager\Product' => 'lib/datamanager/product.php', '\Bitrix\KitImportxml\DataManager\ProductD7' => 'lib/datamanager/product_d7.php', '\Bitrix\KitImportxml\DataManager\IblockElementTable' => 'lib/datamanager/iblockelement.php', '\Bitrix\KitImportxml\DataManager\IblockElementIdTable' => 'lib/datamanager/iblockelementid_table.php', '\Bitrix\KitImportxml\DataManager\ElementPropertyTable' => 'lib/datamanager/element_property_table.php', '\Bitrix\KitImportxml\DataManager\InterhitedpropertyValues' => 'lib/datamanager/inheritedproperty_values.php', '\Bitrix\KitImportxml\ClassManager' => 'lib/class_manager.php', '\Bitrix\KitImportxml\Api' => 'lib/api.php', '\IX\Giftsru' => 'lib/vendors/gifts.ru.php', '\IX\B2bmerlioncom' => 'lib/vendors/b2b.merlion.com.php', '\IX\B2bhogartru' => 'lib/vendors/b2b.hogart.ru.php', '\IX\Apibraincomua' => 'lib/vendors/api.brain.com.ua.php', '\IX\Apiercua' => 'lib/vendors/api.erc.ua.php',));
$_1054917823 = $_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/php_interface/include/' . $_2019323885 . '/init.php';
if (file_exists($_1054917823)) include_once($_1054917823);
$_556944003 = (CJSCore::IsExtRegistered('jquery3') ? 'jquery3' : 'jquery2');
$_1761371605 = array($_1938229550 => array('js' => $_336247591 . '/script.js', 'css' => $_828554920 . '/styles.css', 'rel' => array($_556944003, $_1938229550 . '_chosen'), 'lang' => $_987098826 . '/js_admin.php',), $_1938229550 . '_highload' => array('js' => $_336247591 . '/script_highload.js', 'css' => $_828554920 . '/styles.css', 'rel' => array($_556944003, $_1938229550 . '_chosen'), 'lang' => $_987098826 . '/js_admin_hlbl.php',), $_1938229550 . '_chosen' => array('js' => $_336247591 . '/chosen/chosen.jquery.min.js', 'css' => $_336247591 . '/chosen/chosen.min.css', 'rel' => array($_556944003)));
foreach ($_1761371605 as $_343293781 => $_1867835914) {
    CJSCore::RegisterExt($_343293781, $_1867835914);
}
if (class_exists('\Bitrix\KitImportxml\Utils')) \Bitrix\KitImportxml\Utils::PrepareJs();;
?>