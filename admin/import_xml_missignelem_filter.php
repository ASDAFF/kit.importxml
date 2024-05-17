<?
if(!defined('NO_AGENT_CHECK')) define('NO_AGENT_CHECK', true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/iblock/prolog.php");
$moduleId = 'kit.importxml';
CModule::IncludeModule('iblock');
CModule::IncludeModule($moduleId);
IncludeModuleLangFile(__FILE__);

$MODULE_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if($MODULE_RIGHT < "W") $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$arGet = $_GET;
$IBLOCK_ID = (int)$arGet['IBLOCK_ID'];
$PROFILE_ID = (int)$arGet['PROFILE_ID'];

if($_POST && (isset($_POST['FILTER']) || isset($_POST['EFILTER'])))
{
	\CUtil::JSPostUnescape();
	define('PUBLIC_AJAX_MODE', 'Y');
	if(isset($_POST['FILTER']))
	{
		$arFilterKeys = preg_grep('/^filter1_/', array_keys($_POST));
		if(!empty($arFilterKeys))
		{
			if(!is_array($_POST['FILTER']))
			{
				$_POST['FILTER'] = array();
			}
			foreach($arFilterKeys as $key)
			{
				$arKey = explode('_', $key, 2);
				$_POST['FILTER'][$arKey[1]] = $_POST[$key];
			}
		}
		$arFilter = $_POST['FILTER'];
	}
	elseif(isset($_POST['EFILTER']))
	{
		$arFilter = $_POST['EFILTER'];
	}
	
	$APPLICATION->RestartBuffer();
	if(ob_get_contents()) ob_end_clean();
	echo base64_encode(serialize($arFilter));
	die();
}

if($OLDFILTER) $FILTER = unserialize(base64_decode($OLDFILTER));
if(!is_array($FILTER)) $FILTER = array();

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_popup_admin.php");

$oldTypeFilter = false;
if(count($FILTER) > 0 && count(preg_grep('/^find_/', array_keys($FILTER))) > 0)
{
	$arFilter = array('IBLOCK_ID'=>$IBLOCK_ID);
	\Bitrix\KitImportxml\Utils::AddFilter($arFilter, $OLDFILTER);
	if(count($arFilter) > 1) $oldTypeFilter = true;
	else $FILTER = array();
}
?>
<form action="" method="post" enctype="multipart/form-data" name="filter_form" id="kit-ix-filter" class="kit-ix-filter">
	<?
	if($oldTypeFilter)
	{
		\Bitrix\KitImportxml\Utils::ShowFilter('kit_importxml_'.$PROFILE_ID, $IBLOCK_ID, $FILTER);
	}
	else
	{
		$fl = new \Bitrix\KitImportxml\FieldList(array('IBLOCK_ID'=>$IBLOCK_ID));
		$eFilter = new \Bitrix\KitImportxml\Filter($IBLOCK_ID);
		$eFilter->ShowFilterBlock('kda-ee-sheet-efilter', $FILTER, $fl);
	}
	?>
</form>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_popup_admin.php");?>