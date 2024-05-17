<?
if(!defined('NO_AGENT_CHECK')) define('NO_AGENT_CHECK', true);
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_admin_before.php");
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/iblock/prolog.php");
$moduleId = 'kit.importxml';
CModule::IncludeModule('iblock');
CModule::IncludeModule('catalog');
CModule::IncludeModule($moduleId);
IncludeModuleLangFile(__FILE__);

$MODULE_RIGHT = $APPLICATION->GetGroupRight($moduleId);
if($MODULE_RIGHT < "W") $APPLICATION->AuthForm(GetMessage("ACCESS_DENIED"));

$arGet = $_GET;
$IBLOCK_ID = (int)$arGet['IBLOCK_ID'];
$OFFERS_IBLOCK_ID = \Bitrix\KitImportxml\Utils::GetOfferIblock($IBLOCK_ID);

$arParams1 = $arParams2 = array();
if(isset($_POST) && is_array($_POST) && ($arDefKeys = preg_grep('/^DEFAULTS(_\d+)?/', array_keys($_POST))) && count($arDefKeys) > 0)
{
	$arDefKeys = array_values($arDefKeys);
	foreach($arDefKeys as $k=>$v)
	{
		$key1 = $key2 = '';
		if($k > 0) $key1 = '_'.$k;
		if(preg_match('/^DEFAULTS_(\d+)/', $v, $m)) $key2 = '_'.$m[1];
		$arParams1['MAP'.$key1] = (isset($_POST['MAP'.$key2][$IBLOCK_ID]) && is_array($_POST['MAP'.$key2][$IBLOCK_ID]) ? $_POST['MAP'.$key2][$IBLOCK_ID] : array());
		$arParams1['PARAMS'.$key1] = (isset($_POST['DEFAULTS'.$key2]) && is_array($_POST['DEFAULTS'.$key2]) ? $_POST['DEFAULTS'.$key2] : array());
		if($OFFERS_IBLOCK_ID > 0)
		{
			$arParams2['MAP'.$key1] = (isset($_POST['MAP'.$key2][$OFFERS_IBLOCK_ID]) && is_array($_POST['MAP'.$key2][$OFFERS_IBLOCK_ID]) ? $_POST['MAP'.$key2][$OFFERS_IBLOCK_ID] : array());
			$arParams2['PARAMS'.$key1] = (isset($_POST['DEFAULTS'.$key2]) && is_array($_POST['DEFAULTS'.$key2]) ? $_POST['DEFAULTS'.$key2] : array());
		}
	}
}

/*if(!isset($MAP) || !is_array($MAP)) $MAP = array();
if(!isset($MAP[$IBLOCK_ID]) || !is_array($MAP[$IBLOCK_ID])) $MAP[$IBLOCK_ID] = array();
if($OFFERS_IBLOCK_ID >0 && !isset($MAP[$OFFERS_IBLOCK_ID]) || !is_array($MAP[$OFFERS_IBLOCK_ID])) $MAP[$OFFERS_IBLOCK_ID] = array();
if(!isset($DEFAULTS) || !is_array($DEFAULTS)) $DEFAULTS = array();*/

if($_POST['action']=='save')
{
	\CUtil::JSPostUnescape();
	define('PUBLIC_AJAX_MODE', 'Y');
	
	//\Bitrix\Main\Config\Option::set('iblock', 'KDA_IBLOCK'.$IBLOCK_ID.'_PRICEQNT_CONFORMITY', serialize(array('MAP'=>$MAP[$IBLOCK_ID], 'PARAMS'=>$DEFAULTS)));
	\Bitrix\Main\Config\Option::set('iblock', 'KDA_IBLOCK'.$IBLOCK_ID.'_PRICEQNT_CONFORMITY', serialize($arParams1));
	if($OFFERS_IBLOCK_ID > 0)
	{
		//\Bitrix\Main\Config\Option::set('iblock', 'KDA_IBLOCK'.$OFFERS_IBLOCK_ID.'_PRICEQNT_CONFORMITY', serialize(array('MAP'=>$MAP[$OFFERS_IBLOCK_ID], 'PARAMS'=>$DEFAULTS)));
		\Bitrix\Main\Config\Option::set('iblock', 'KDA_IBLOCK'.$OFFERS_IBLOCK_ID.'_PRICEQNT_CONFORMITY', serialize($arParams2));
	}
	
	$APPLICATION->RestartBuffer();
	ob_end_clean();
	
	echo '<script>';
	echo 'BX.WindowManager.Get().Close();';
	echo '</script>';
	die();
}

/*if(empty($MAP[$IBLOCK_ID]))
{
	$arParams = unserialize(\Bitrix\Main\Config\Option::get('iblock', 'KDA_IBLOCK'.$IBLOCK_ID.'_PRICEQNT_CONFORMITY'));
	$MAP[$IBLOCK_ID] = $arParams['MAP'];
	if(!is_array($MAP[$IBLOCK_ID])) $MAP[$IBLOCK_ID] = array();
	if(empty($MAP[$IBLOCK_ID])) $MAP[$IBLOCK_ID][] = array('price'=>'', 'quantity'=>'');
	$DEFAULTS = $arParams['PARAMS'];
	if(!is_array($DEFAULTS)) $DEFAULTS = array();
}*/

$arSharePriceTypes = array();
$dbPriceType = \CCatalogGroup::GetList(array("BASE"=>"DESC", "SORT" => "ASC"));
while($arPriceType = $dbPriceType->Fetch())
{
	$arSharePriceTypes[$arPriceType["ID"]] = ($arPriceType["NAME_LANG"] ? $arPriceType["NAME_LANG"] : $arPriceType["NAME"]);
}

$arParams1 = unserialize(\Bitrix\Main\Config\Option::get('iblock', 'KDA_IBLOCK'.$IBLOCK_ID.'_PRICEQNT_CONFORMITY'));
if(!isset($arParams1['MAP']) || !is_array($arParams1['MAP'])) $arParams1['MAP'] = array();
if(!isset($arParams1['PARAMS']) || !is_array($arParams1['PARAMS'])) $arParams1['PARAMS'] = array();

$arParams2 = array();
if($OFFERS_IBLOCK_ID) $arParams2 = unserialize(\Bitrix\Main\Config\Option::get('iblock', 'KDA_IBLOCK'.$OFFERS_IBLOCK_ID.'_PRICEQNT_CONFORMITY'));

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_popup_admin.php");
?>
<form action="" method="post" enctype="multipart/form-data" name="field_settings" id="kit-ix-price-calculating-form">
 <input type="hidden" name="action" value="save">
 <select name="SHARE_PRICE_TYPE" style="display: none;">
	<?
	foreach($arSharePriceTypes as $k=>$v)
	{
		?><option value="<?echo $k;?>"<?if($DEFAULTS['PRICE_TYPE']==$k){echo ' selected';}?>><?echo htmlspecialcharsbx($v); ?></option><?
	}
	?>
	<option value="PURCHASING_PRICE"<?if($DEFAULTS['PRICE_TYPE']=='PURCHASING_PRICE'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_TYPE_PURCHASING"); ?></option>
 </select>
	
<?
$j = 0;
while((($jk = ($j > 0 ? '_'.$j : '')) || true) && ($jp = 'PARAMS'.$jk) && ($jm = 'MAP'.$jk) && array_key_exists($jp, $arParams1))
{
	$MAP[$IBLOCK_ID] = $arParams1[$jm];
	if(!is_array($MAP[$IBLOCK_ID])) $MAP[$IBLOCK_ID] = array();
	if(empty($MAP[$IBLOCK_ID])) $MAP[$IBLOCK_ID][] = array('price'=>'', 'quantity'=>'');
	if($OFFERS_IBLOCK_ID)
	{
		$MAP[$OFFERS_IBLOCK_ID] = $arParams2[$jm];
		if(!is_array($MAP[$OFFERS_IBLOCK_ID])) $MAP[$OFFERS_IBLOCK_ID] = array();
		if(empty($MAP[$OFFERS_IBLOCK_ID])) $MAP[$OFFERS_IBLOCK_ID][] = array('price'=>'', 'quantity'=>'');
	}
	$DEFAULTS = $arParams1[$jp];
	if(!is_array($DEFAULTS)) $DEFAULTS = array();
?>	
 <div class="kit-ix-price-calculating-wrap" data-index="<?echo $j;?>"<?if($j > 0){echo 'style="display: none;"';}?>>
	<table width="100%" class="kit-ix-price-calculating">
		<col width="50%">
		<col width="50%">
		
		<tr>
			<td class="adm-detail-content-cell-l"><?echo GetMessage("KIT_IX_CALC_PRICE_TYPE"); ?></td>
			<td class="adm-detail-content-cell-r">
				<select name="DEFAULTS<?echo $jk;?>[PRICE_TYPE]" onclick="EProfile.ChangeTypeTypeSelect(this)">
					<?
					foreach($arSharePriceTypes as $k=>$v)
					{
						?><option value="<?echo $k;?>"<?if($DEFAULTS['PRICE_TYPE']==$k){echo ' selected';}?>><?echo htmlspecialcharsbx($v); ?></option><?
					}
					?>
					<option value="PURCHASING_PRICE"<?if($DEFAULTS['PRICE_TYPE']=='PURCHASING_PRICE'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_TYPE_PURCHASING"); ?></option>
				</select>
			</td>
		</tr>
		
		<?if(count($arSharePriceTypes) > 0){?>
		<tr>
			<td class="adm-detail-content-cell-l"></td>
			<td class="adm-detail-content-cell-r">
				<div class="kit-ix-price-calculating-ptypes"></div>
				<div class="kit-ix-price-calculating-ptype-new"><a href="javascript:void(0)" onclick="EProfile.AddNewCalcType(this)"><span><?echo GetMessage("KIT_IX_CALC_ADD_PRICE_TYPE"); ?></span></a></div>
			</td>
		</tr>
		<?}?>
		
		<tr>
			<td class="adm-detail-content-cell-l"><?echo GetMessage("KIT_IX_CALC_PRICE_CALC"); ?></td>
			<td class="adm-detail-content-cell-r">
				<select name="DEFAULTS<?echo $jk;?>[PRICE_CALC]">
					<option value="MIN"<?if($DEFAULTS['PRICE_CALC']=='MIN'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_CALC_MIN");?></option>
					<option value="MAX"<?if($DEFAULTS['PRICE_CALC']=='MAX'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_CALC_MAX");?></option>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="adm-detail-content-cell-l"><?echo GetMessage("KIT_IX_CALC_PRICE_EMPTY_ACTION"); ?></td>
			<td class="adm-detail-content-cell-r">
				<select name="DEFAULTS<?echo $jk;?>[PRICE_EMPTY_ACTION]">
					<option value="DELETE"<?if($DEFAULTS['PRICE_EMPTY_ACTION']=='DELETE'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_EMPTY_ACTION_DELETE");?></option>
					<option value="SALE_OLD"<?if($DEFAULTS['PRICE_EMPTY_ACTION']=='SALE_OLD'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_EMPTY_ACTION_SALE_OLD");?></option>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="adm-detail-content-cell-l"><?echo GetMessage("KIT_IX_CALC_PRICE_ROUND"); ?></td>
			<td class="adm-detail-content-cell-r">
				<select name="DEFAULTS<?echo $jk;?>[PRICE_ROUND]" onchange="if(this.value){$(this).next('select').show();}else{$(this).next('select').hide();}">
					<option value=""><?echo GetMessage("KIT_IX_CALC_PRICE_ROUND_OFF");?></option>
					<option value="MATH"<?if($DEFAULTS['PRICE_ROUND']=='MATH'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_ROUND_MATH");?></option>
					<option value="UP"<?if($DEFAULTS['PRICE_ROUND']=='UP'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_ROUND_UP");?></option>
					<option value="DOWN"<?if($DEFAULTS['PRICE_ROUND']=='DOWN'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_ROUND_DOWN");?></option>
				</select>
				<select name="DEFAULTS<?echo $jk;?>[PRICE_ROUND_DIGIT]" <?if(!$DEFAULTS['PRICE_ROUND']){echo 'style="display: none;"';}?>>
					<option value="0.01"<?if($DEFAULTS['PRICE_ROUND_DIGIT']=='0.01'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_ROUND_DIGIT_001");?></option>
					<option value="0.1"<?if($DEFAULTS['PRICE_ROUND_DIGIT']=='0.1'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_ROUND_DIGIT_01");?></option>
					<option value=""<?if(!is_numeric($DEFAULTS['PRICE_ROUND_DIGIT'])){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_ROUND_DIGIT_0");?></option>
					<option value="10"<?if($DEFAULTS['PRICE_ROUND_DIGIT']=='10'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_ROUND_DIGIT_10");?></option>
					<option value="100"<?if($DEFAULTS['PRICE_ROUND_DIGIT']=='100'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_ROUND_DIGIT_100");?></option>
					<option value="1000"<?if($DEFAULTS['PRICE_ROUND_DIGIT']=='1000'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_ROUND_DIGIT_1000");?></option>
					<option value="10000"<?if($DEFAULTS['PRICE_ROUND_DIGIT']=='10000'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_PRICE_ROUND_DIGIT_10000");?></option>
				</select>
			</td>
		</tr>
		
		<tr>
			<td class="adm-detail-content-cell-l"><?echo GetMessage("KIT_IX_CALC_PRICE_ONLY_AVAILABLE"); ?></td>
			<td class="adm-detail-content-cell-r">
				<input type="hidden" name="DEFAULTS<?echo $jk;?>[ONLY_AVAILABLE]" value="N">
				<input type="checkbox" name="DEFAULTS<?echo $jk;?>[ONLY_AVAILABLE]" value="Y"<?if($DEFAULTS['ONLY_AVAILABLE']!='N'){echo ' checked';}?>>
			</td>
		</tr>
		
		<?if($j==0){?>
			<tr>
				<td class="adm-detail-content-cell-l"><?echo GetMessage("KIT_IX_CALC_QUANTITY"); ?></td>
				<td class="adm-detail-content-cell-r">
					<select name="DEFAULTS<?echo $jk;?>[QUANTITY_CALC]">
						<option value="FROM_PRICE"<?if($DEFAULTS['QUANTITY_CALC']=='FROM_PRICE'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_QUANTITY_FROM_PRICE");?></option>
						<option value="SUM"<?if($DEFAULTS['QUANTITY_CALC']=='SUM'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_QUANTITY_SUM");?></option>
						<option value="NONE"<?if($DEFAULTS['QUANTITY_CALC']=='NONE'){echo ' selected';}?>><?echo GetMessage("KIT_IX_CALC_QUANTITY_NONE");?></option>
					</select>
				</td>
			</tr>
		<?}?>
	</table>
	
<?
$arStores = array();
$dbRes = \CCatalogStore::GetList(array("SORT"=>"ASC"), array(), false, false, array("ID", "TITLE", "ADDRESS"));
while($arStore = $dbRes->Fetch())
{
	$arStores['ICAT_STORE'.$arStore["ID"]] = GetMessage("KIT_IX_CALC_STORE_PREFIX").' "'.(strlen($arStore["TITLE"]) > 0 ? $arStore["TITLE"] : $arStore["ADDRESS"]).'"';
}

$arPriceTypes = array();
$dbRes = \CCatalogGroup::GetList(array("SORT" => "ASC"), array(), false, false, array("ID", "NAME", "NAME_LANG"));
while($arPriceType = $dbRes->Fetch())
{
	$arPriceTypes["ICAT_PRICE".$arPriceType["ID"]] = GetMessage("KIT_IX_CALC_PRICE_PREFIX").' "'.(strlen($arPriceType["NAME_LANG"]) > 0 ? $arPriceType["NAME_LANG"] : $arPriceType["NAME"]).'"';
}

$arIblocks = array('PRODUCTS'=>$IBLOCK_ID);
if($OFFERS_IBLOCK_ID) $arIblocks['OFFERS'] = $OFFERS_IBLOCK_ID;
foreach($arIblocks as $iblockType=>$iblockId)
{
	$arFieldsPrice = array();
	$dbRes = CIBlockProperty::GetList(array("sort" => "asc", "name" => "asc"), array("ACTIVE" => "Y", "IBLOCK_ID" => $iblockId, "CHECK_PERMISSIONS" => "N"));
	while($arr = $dbRes->Fetch())
	{
		if((($arr["PROPERTY_TYPE"]=='S' || $arr["PROPERTY_TYPE"]=='N') && !$arr['USER_TYPE'] && $arr['MULTIPLE']=='N'))
		{
			$arFieldsPrice['IP_PROP'.$arr['ID']] = $arr["NAME"].' ['.$arr["CODE"].']';
		}
	}
	$arFieldsQnt = array_merge($arFieldsPrice, $arStores);
	$arFieldsPrice = array_merge($arFieldsPrice, $arPriceTypes);
?>
  <div class="kit-ix-price-calculating-iblock">&nbsp;
	<div style="display: none;">
		<select name="price">
			<option value=""><?echo GetMessage("KIT_IX_NOT_CHOOSE");?></option><?
			if(($arGroupFields = preg_grep('/^IP_PROP/', array_keys($arFieldsPrice))) && count($arGroupFields) > 0)
			{
				?><optgroup label="<?echo GetMessage("KIT_IX_CALC_PROPS")?>"><?
				foreach($arFieldsPrice as $k=>$v)
				{
					if(!in_array($k, $arGroupFields)) continue;
					?><option value="<?echo $k; ?>"><?echo htmlspecialcharsbx($v); ?></option><?
				}
				?></optgroup><?
			}
			if(($arGroupFields = preg_grep('/^ICAT_PRICE/', array_keys($arFieldsPrice))) && count($arGroupFields) > 0)
			{
				?><optgroup label="<?echo GetMessage("KIT_IX_CALC_PRICES")?>"><?
				foreach($arFieldsPrice as $k=>$v)
				{
					if(!in_array($k, $arGroupFields)) continue;
					?><option value="<?echo $k; ?>"><?echo htmlspecialcharsbx($v); ?></option><?
				}
				?></optgroup><?
			}
			?>	
		</select>
		<select name="quantity">
			<option value=""><?echo GetMessage("KIT_IX_NOT_CHOOSE");?></option><?
			if(($arGroupFields = preg_grep('/^IP_PROP/', array_keys($arFieldsQnt))) && count($arGroupFields) > 0)
			{
				?><optgroup label="<?echo GetMessage("KIT_IX_CALC_PROPS")?>"><?
				foreach($arFieldsQnt as $k=>$v)
				{
					if(!in_array($k, $arGroupFields)) continue;
					?><option value="<?echo $k; ?>"><?echo htmlspecialcharsbx($v); ?></option><?
				}
				?></optgroup><?
			}
			if(($arGroupFields = preg_grep('/^ICAT_STORE/', array_keys($arFieldsQnt))) && count($arGroupFields) > 0)
			{
				?><optgroup label="<?echo GetMessage("KIT_IX_CALC_STORES")?>"><?
				foreach($arFieldsQnt as $k=>$v)
				{
					if(!in_array($k, $arGroupFields)) continue;
					?><option value="<?echo $k; ?>"><?echo htmlspecialcharsbx($v); ?></option><?
				}
				?></optgroup><?
			}
			?>
		</select>
	</div>
	
	<table width="100%" class="kit-ix-price-calculating">
		<col width="50%">
		<col width="50%">
		<tr class="heading">
			<td colspan="2">
				<?echo GetMessage("KIT_IX_REL_TABLE_PRICES").(count($arIblocks) > 1 ? ' ('.GetMessage("KIT_IX_REL_TABLE_PRICES_IBLOCK_".$iblockType).')' : ''); ?>
			</td>
		</tr>
		
		<tr>
			<td colspan="2" class="kit-ix-pricing-rels">
				<table width="100%" cellpadding="5" border="1" data-iblock-id="<?echo $iblockId?>">
				  <tr>
					<th width="45%"><?echo GetMessage("KIT_IX_REL_TABLE_COL_QNT"); ?></th>
					<th width="45%"><?echo GetMessage("KIT_IX_REL_TABLE_COL_PRICE"); ?></th>
					<th width="10%"><?echo GetMessage("KIT_IX_REL_TABLE_COL_EXTRA"); ?></th>
					<th width="30px"></th>
				  </tr>
				<?
				foreach($MAP[$iblockId] as $index=>$arMap)
				{
				?>
				  <tr data-index="<?echo $index;?>">
					<td>
					  <div class="kit-ix-select-mapping">
						<input type="hidden" name="MAP<?echo $jk;?>[<?echo $iblockId;?>][<?echo $index;?>][quantity]" value="<?echo htmlspecialcharsbx($arMap['quantity']);?>">
						<a href="javascript:void(0)" onclick="EProfile.RelTablePriceShowSelect(this, 'quantity', true)" data-default-text="<?echo GetMessage("KIT_IX_NOT_CHOOSE")?>"><?echo (strlen($arMap['quantity']) > 0 && isset($arFieldsQnt[$arMap['quantity']]) ? $arFieldsQnt[$arMap['quantity']] : GetMessage("KIT_IX_NOT_CHOOSE"))?></a>
					  </div>
					</td>
					<td>
					  <div class="kit-ix-select-mapping">
						<input type="hidden" name="MAP<?echo $jk;?>[<?echo $iblockId;?>][<?echo $index;?>][price]" value="<?echo htmlspecialcharsbx($arMap['price']);?>">
						<a href="javascript:void(0)" onclick="EProfile.RelTablePriceShowSelect(this, 'price', true)" data-default-text="<?echo GetMessage("KIT_IX_NOT_CHOOSE")?>"><?echo (strlen($arMap['price']) > 0 && isset($arFieldsPrice[$arMap['price']]) ? $arFieldsPrice[$arMap['price']] : GetMessage("KIT_IX_NOT_CHOOSE"))?></a>
					  </div>
					</td>
					<td>
						<input type="text" size="10" name="MAP<?echo $jk;?>[<?echo $iblockId;?>][<?echo $index;?>][extra]" value="<?echo htmlspecialcharsbx($arMap['extra']);?>">
					</td>
					<td>
					  <a href="javascript:void(0)" class="kit-ix-delete-row" onclick="EProfile.RelTablePriceRowRemove(this)" title="<?echo GetMessage("KIT_IX_REL_TABLE_REMOVE_ROW"); ?>"></a>
					</td>
				  </tr>
				<?
				}
				?>
				</table>
				<a href="javascript:void(0)" onclick="EProfile.RelTablePriceRowAdd(this)"><?echo GetMessage("KIT_IX_REL_TABLE_ADD_ROW"); ?></a>
			</td>
		</tr>		
	</table>
  </div>
<?}?>
 </div>
<?
$j++;
}?>
</form>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_popup_admin.php");?>