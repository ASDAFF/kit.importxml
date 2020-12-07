<?php
/**
 * Copyright (c) 4/8/2019 Created By/Edited By ASDAFF asdaff.asad@yandex.ru
 */

namespace Bitrix\KitImportxml;

class ClassManager
{
	protected static $modVersions = array();
	protected static $versionsGeq = array();
	protected $ie = null;
	
	public function __construct($ie)
	{
		$this->ie = $ie;
	}
	
	public function GetProductor()
	{
		if(static::VersionGeqThen('catalog', '17.6.0'))
		{
			return new \Bitrix\KitImportxml\DataManager\ProductD7($this->ie);
		}
		else
		{
			return new \Bitrix\KitImportxml\DataManager\Product($this->ie);
		}
	}
	
	public function GetPricer()
	{
		if(static::VersionGeqThen('catalog', '17.6.0'))
		{
			return new \Bitrix\KitImportxml\DataManager\PriceD7($this->ie);
		}
		else
		{
			return new \Bitrix\KitImportxml\DataManager\Price($this->ie);
		}
	}
	
	public static function GetModuleVersion($module)
	{
		if(!isset(static::$modVersions[$module]))
		{
			if(is_callable(array('\Bitrix\Main\ModuleManager', 'getVersion')))
			{
				static::$modVersions[$module] = \Bitrix\Main\ModuleManager::getVersion($module);
			}
			else static::$modVersions[$module] = '';
		}
		return static::$modVersions[$module];
	}
	
	public static function VersionGeqThen($module, $version)
	{
		$vKey = $module.'--'.$version;
		if(!isset(static::$versionsGeq[$vKey]))
		{
			$version = trim($version);
			$currentVersion = static::GetModuleVersion($module);
			$v1 = explode('.', $version);
			$v2 = explode('.', $currentVersion);
			$geq = false;
			if(count($v1) > 1 && count($v2) > 1)
			{
				$i = 0;
				$geq = null;
				while(!isset($geq) && isset($v1[$i]) && isset($v2[$i]))
				{
					if($v2[$i] < $v1[$i]) $geq = false;
					elseif($v2[$i] > $v1[$i]) $geq = true;
					$i++;
				}
				if(!isset($geq)) $geq = true;
			}
			static::$versionsGeq[$vKey] = $geq;
		}
		return static::$versionsGeq[$vKey];
	}
}