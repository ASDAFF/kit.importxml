<?php
namespace Bitrix\EsolImportxml\DataManager;

use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
Loc::loadMessages(__FILE__);

class PriceD7 extends Price
{
	protected $priceFields = null;
	
	public function __construct($ie=false)
	{
		$this->priceFields = array_keys(\Bitrix\Catalog\PriceTable::getMap());
		parent::__construct($ie);
	}
	
	public function GetList($arOrder = array(), $arFilter = array(), $arGroupBy = false, $arNavStartParams = false, $arSelectFields = array())
	{
		$arParams = array();
		if(!empty($arOrder)) $arParams['order'] = $arOrder;
		if(!empty($arFilter)) $arParams['filter'] = $arFilter;
		if(is_array($arGroupBy) && !empty($arGroupBy)) $arParams['group'] = $arGroupBy;
		if(is_array($arNavStartParams) && !empty($arNavStartParams))
		{
			if($arNavStartParams['nTopCount']) $arParams['limit'] = $arNavStartParams['nTopCount'];
		}
		if(!empty($arSelectFields)) $arParams['select'] = array_intersect($arSelectFields, $this->priceFields);
		return \Bitrix\Catalog\Model\Price::getList($arParams);
	}
	
	public function CheckExceptions($ex, $last=true)
	{
		if(is_callable(array('\Bitrix\Main\Diag\ExceptionHandlerFormatter', 'format')) && ($errorText = \Bitrix\Main\Diag\ExceptionHandlerFormatter::format($ex)))
		{
			if(!$last && mb_strpos($errorText, 'Deadlock found when trying to get lock')!==false)
			{
				return 'iteration';
			}
			else throw new \Exception($errorText);
		}
		else throw new \Exception($ex->getMessage());
		return false;
	}
	
	public function Add($arFields, $boolRecalc = false)
	{
		foreach($arFields as $k=>$v)
		{
			if(!in_array($k, $this->priceFields)) unset($arFields[$k]);
		}
		if(isset($arFields['EXTRA_ID']) && isset($arFields['CURRENCY'])) unset($arFields['CURRENCY']);
		if(isset($arFields['QUANTITY_FROM']) && (int)$arFields['QUANTITY_FROM'] <= 0) $arFields['QUANTITY_FROM'] = null;
		if(isset($arFields['QUANTITY_TO']) && (int)$arFields['QUANTITY_TO'] <= 0) $arFields['QUANTITY_TO'] = null;
		$arFields = array('fields' => $arFields);
		if($boolRecalc) $arFields['actions']['RECOUNT_PRICES'] = true;
		$result = \Bitrix\Catalog\Model\Price::add($arFields);
		if($result->isSuccess())
		{
			return (int)$result->getId();
		}
		else return false;
	}
	
	public function Update($ID, $arFields, $boolRecalc = false)
	{
		foreach($arFields as $k=>$v)
		{
			if(!in_array($k, $this->priceFields)) unset($arFields[$k]);
		}
		if(isset($arFields['EXTRA_ID']) && (int)$arFields['EXTRA_ID'] <= 0) $arFields['EXTRA_ID'] = null;
		if(isset($arFields['EXTRA_ID']) && isset($arFields['CURRENCY'])) unset($arFields['CURRENCY']);
		if(isset($arFields['QUANTITY_FROM']) && (int)$arFields['QUANTITY_FROM'] <= 0) $arFields['QUANTITY_FROM'] = null;
		if(isset($arFields['QUANTITY_TO']) && (int)$arFields['QUANTITY_TO'] <= 0) $arFields['QUANTITY_TO'] = null;
		$arFields = array('fields' => $arFields);
		if($boolRecalc) $arFields['actions']['RECOUNT_PRICES'] = true;

		$result = false;
		$action = 'iteration';
		$i = 10;
		while($i > 0 && $action=='iteration')
		{
			$action = '';
			$i--;
			try{
				$result = \Bitrix\Catalog\Model\Price::update($ID, $arFields);
				return $result->isSuccess();
			}catch(\Exception $ex){
				$action = $this->CheckExceptions($ex, (bool)($i==0));
			}
		}
		return $result;
		
		/*if($result = \Bitrix\Catalog\Model\Price::update($ID, $arFields))
		{
			return $result->isSuccess();
		}
		else return false;*/
	}
	
	public function Delete($ID)
	{
		if($result = \Bitrix\Catalog\Model\Price::delete($ID))
		{
			return $result->isSuccess();
		}
		else return false;
	}
}