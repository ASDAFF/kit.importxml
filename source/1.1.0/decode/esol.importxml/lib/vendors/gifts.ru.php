<?php
namespace IX;

class Giftsru {
	public static function GetProductName($val)
	{
		return trim(preg_replace('/,[^,]*$/', '', $val));
	}
	
	public static function RemoveLinks($val)
	{
		return preg_replace('/<\/?a(>|\s[^>]*>)/', '', $val);
	}
	
	public static function SetFilters($obj, $url)
	{
		if(!isset($obj->stepparams['cstm_filters']))
		{
			$ob = new \Bitrix\Main\Web\HttpClient(array('disableSslVerification'=>true));
			$res = $ob->get($url);
			$xml = simplexml_load_string($res);
			$json = json_encode($xml);
			$arData = json_decode($json,TRUE);

			$arTypes = array();
			$arTypeVals = array();
			if(is_array($arData['filtertypes']['filtertype']))
			{
				foreach($arData['filtertypes']['filtertype'] as $arType)
				{
					$arTypes[$arType['filtertypeid']] = $arType['filtertypename'];
					$arTypeVals[$arType['filtertypeid']] = array();
					if(is_array($arType['filters']['filter']))
					{
						if(!array_key_exists('0', $arType['filters']['filter'])) $arType['filters']['filter'] = array($arType['filters']['filter']);
						foreach($arType['filters']['filter'] as $arTypeVal)
						{
							$arTypeVals[$arType['filtertypeid']][$arTypeVal['filterid']] = $arTypeVal['filtername'];
						}
					}
				}
			}
			$obj->stepparams['cstm_filters'] = array(
				'types' => $arTypes,
				'typevals' => $arTypeVals
			);
		}
	}
	
	public static function GetFilterNames($obj, $url, $val)
	{
		self::SetFilters($obj, $url);
		$arTypes = $obj->stepparams['cstm_filters']['types'];
		$arTypeVals = $obj->stepparams['cstm_filters']['typevals'];
		if(isset($arTypes[$val])){$val = $arTypes[$val];}else{$val = '';}
		return $val;
	}
	
	public static function GetFilterVals($obj, $url, $val, $filtertypeid)
	{
		self::SetFilters($obj, $url);
		$arTypes = $obj->stepparams['cstm_filters']['types'];
		$arTypeVals = $obj->stepparams['cstm_filters']['typevals'];
		if(isset($arTypeVals[$filtertypeid][$val])){$val = $arTypeVals[$filtertypeid][$val];}else{$val = '';}
		return $val;
	}
}
?>