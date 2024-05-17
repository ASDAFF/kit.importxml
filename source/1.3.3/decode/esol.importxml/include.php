<?php
include_once(dirname(__FILE__) . '/install/demo.php');
if (!class_exists('CEsolImportXMLRunner')) {
    class CEsolImportXMLRunner
    {
        protected static $_2019323885 = 'esol.importxml';

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
            $_906924150 = new \Bitrix\EsolImportxml\Importer($_2029131979, $_1137406749, $_1334224997, $_1605213925, $_279272061);
            return $_906924150->Import();
        }

        static function ImportHighloadblock($_2029131979, $_1137406749, $_1334224997, $_1605213925, $_279272061 = false)
        {
            if (self::__1667935303()) return array();
            $_906924150 = new \Bitrix\EsolImportxml\ImporterHl($_2029131979, $_1137406749, $_1334224997, $_1605213925, $_279272061);
            return $_906924150->Import();
        }
    }
}
$_2019323885 = CEsolImportXMLRunner::GetModuleId();
$_1938229550 = str_replace('.', '_', $_2019323885);
$_336247591 = '/bitrix/js/' . $_2019323885;
$_828554920 = '/bitrix/panel/' . $_2019323885;
$_987098826 = BX_ROOT . '/modules/' . $_2019323885 . '/lang/' . LANGUAGE_ID;
CModule::AddAutoloadClasses($_2019323885, array('\Bitrix\EsolImportxml\Profile' => 'lib/profile.php', '\Bitrix\EsolImportxml\ProfileTable' => 'lib/profile_table.php', '\Bitrix\EsolImportxml\ProfileHlTable' => 'lib/profile_hl_table.php', '\Bitrix\EsolImportxml\Utils' => 'lib/utils.php', '\Bitrix\EsolImportxml\HttpClient' => 'lib/httpclient.php', '\Bitrix\EsolImportxml\Json2Xml' => 'lib/json2xml.php', '\Bitrix\EsolImportxml\ProfileElementTable' => 'lib/profile_element.php', '\Bitrix\EsolImportxml\ProfileElementHlTable' => 'lib/profile_element_hl.php', '\Bitrix\EsolImportxml\ProfileExecTable' => 'lib/profile_exec.php', '\Bitrix\EsolImportxml\ProfileExecStatTable' => 'lib/profile_exec_stat.php', '\Bitrix\EsolImportxml\ProfileChangesTable' => 'lib/profile_changes.php', '\Bitrix\EsolImportxml\Sftp' => 'lib/sftp.php', '\Bitrix\EsolImportxml\Conversion' => 'lib/conversion.php', '\Bitrix\EsolImportxml\Cloud' => 'lib/cloud.php', '\Bitrix\EsolImportxml\Cloud\MailRu' => 'lib/cloud/mail_ru.php', '\Bitrix\EsolImportxml\ZipArchive' => 'lib/zip_archive.php', '\Bitrix\EsolImportxml\XMLViewer' => 'lib/xml_viewer.php', '\Bitrix\EsolImportxml\FieldList' => 'lib/field_list.php', '\Bitrix\EsolImportxml\Filter' => 'lib/filter.php', '\Bitrix\EsolImportxml\ImporterBase' => 'lib/importer_base.php', '\Bitrix\EsolImportxml\ImporterData' => 'lib/importer_data.php', '\Bitrix\EsolImportxml\Importer' => 'lib/importer.php', '\Bitrix\EsolImportxml\ImporterRollback' => 'lib/importer_rollback.php', '\Bitrix\EsolImportxml\ImporterHl' => 'lib/importer_hl.php', '\Bitrix\EsolImportxml\XMLReader' => 'lib/xmlreader.php', '\Bitrix\EsolImportxml\Logger' => 'lib/logger.php', '\Bitrix\EsolImportxml\Extrasettings' => 'lib/extrasettings.php', '\Bitrix\EsolImportxml\CFileInput' => 'lib/file_input.php', '\Bitrix\EsolImportxml\Imap' => 'lib/mail/imap.php', '\Bitrix\EsolImportxml\SMail' => 'lib/mail/mail.php', '\Bitrix\EsolImportxml\MailHeader' => 'lib/mail/mail_header.php', '\Bitrix\EsolImportxml\MailMessage' => 'lib/mail/mail_message.php', '\Bitrix\EsolImportxml\MailUtil' => 'lib/mail/mail_util.php', '\Bitrix\EsolImportxml\DataManager\Discount' => 'lib/datamanager/discount.php', '\Bitrix\EsolImportxml\DataManager\DiscountProductTable' => 'lib/datamanager/discount_product_table.php', '\Bitrix\EsolImportxml\DataManager\Price' => 'lib/datamanager/price.php', '\Bitrix\EsolImportxml\DataManager\PriceD7' => 'lib/datamanager/price_d7.php', '\Bitrix\EsolImportxml\DataManager\Product' => 'lib/datamanager/product.php', '\Bitrix\EsolImportxml\DataManager\ProductD7' => 'lib/datamanager/product_d7.php', '\Bitrix\EsolImportxml\DataManager\IblockElementTable' => 'lib/datamanager/iblockelement.php', '\Bitrix\EsolImportxml\DataManager\IblockElementIdTable' => 'lib/datamanager/iblockelementid_table.php', '\Bitrix\EsolImportxml\DataManager\ElementPropertyTable' => 'lib/datamanager/element_property_table.php', '\Bitrix\EsolImportxml\DataManager\InterhitedpropertyValues' => 'lib/datamanager/inheritedproperty_values.php', '\Bitrix\EsolImportxml\ClassManager' => 'lib/class_manager.php', '\Bitrix\EsolImportxml\Api' => 'lib/api.php', '\IX\Giftsru' => 'lib/vendors/gifts.ru.php', '\IX\B2bmerlioncom' => 'lib/vendors/b2b.merlion.com.php', '\IX\B2bhogartru' => 'lib/vendors/b2b.hogart.ru.php', '\IX\Apibraincomua' => 'lib/vendors/api.brain.com.ua.php', '\IX\Apiercua' => 'lib/vendors/api.erc.ua.php',));
$_1054917823 = $_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/php_interface/include/' . $_2019323885 . '/init.php';
if (file_exists($_1054917823)) include_once($_1054917823);
$_556944003 = (CJSCore::IsExtRegistered('jquery3') ? 'jquery3' : 'jquery2');
$_1761371605 = array($_1938229550 => array('js' => $_336247591 . '/script.js', 'css' => $_828554920 . '/styles.css', 'rel' => array($_556944003, $_1938229550 . '_chosen'), 'lang' => $_987098826 . '/js_admin.php',), $_1938229550 . '_highload' => array('js' => $_336247591 . '/script_highload.js', 'css' => $_828554920 . '/styles.css', 'rel' => array($_556944003, $_1938229550 . '_chosen'), 'lang' => $_987098826 . '/js_admin_hlbl.php',), $_1938229550 . '_chosen' => array('js' => $_336247591 . '/chosen/chosen.jquery.min.js', 'css' => $_336247591 . '/chosen/chosen.min.css', 'rel' => array($_556944003)));
foreach ($_1761371605 as $_343293781 => $_1867835914) {
    CJSCore::RegisterExt($_343293781, $_1867835914);
}
if (class_exists('\Bitrix\EsolImportxml\Utils')) \Bitrix\EsolImportxml\Utils::PrepareJs();;
?>