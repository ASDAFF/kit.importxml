<?
namespace Bitrix\EsolImportxml;

class XMLReader
{
	const NONE = 0;
	const ELEMENT = 1;
	const ATTRIBUTE = 2;
	const TEXT = 3;
	const CDATA = 4;
	const ENTITY_REF = 5;
	const ENTITY = 6;
	const PI = 7;
	const COMMENT = 8;
	const DOC = 9;
	const DOC_TYPE = 10;
	const DOC_FRAGMENT = 11;
	const NOTATION = 12;
	const WHITESPACE = 13;
	const SIGNIFICANT_WHITESPACE = 14;
	const END_ELEMENT = 15;
	const END_ENTITY = 16;
	const XML_DECLARATION = 17;

	private $filename = null;
	private $parser = null;
	private $handle = null;
	private $end = false;
	private $arData = array();
	private $depthInner = -1;
	private $inElement = false;
	private $attributes = array();
	private $attributeKey = 0;
	private $arNode = null;
	private $textData = null;
	private $lastNode = null;
	private $index = 0;
	private $cnt = 0;

	public $nodeType = null;
	public $depth = null;
	public $name = null;
	public $value = null;
	public $namespaceURI = null;

	public function __construct()
	{
	}
	
	public function open($filename, $encoding = null, $flags = 0)
	{
		if(!file_exists($filename) || !is_file($filename)) return false;
		if(!($handle = fopen($filename, 'r'))) return false;
		$this->handle = $handle;
		
		$this->filename = $filename;
		$this->parser = xml_parser_create();
		
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
		xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, true);
		xml_set_element_handler($this->parser, array($this, "startElement"), array($this, "endElement"));
		xml_set_character_data_handler($this->parser, array($this, "characterData"));
		
		return true;
	}
	
	public function close()
	{
		if(!isset($this->parser)) return false;
		xml_parser_free($this->parser);
		fclose($this->handle);
		$this->parser = null;
		$this->handle = null;
		return true;
	}
	
	public function read()
	{
		if($this->cnt > 0 && $this->index > $this->cnt - 1) $this->cnt = 0;
		while($this->cnt==0 && !$this->end)
		{
			$this->arData = array();
			$data = fread($this->handle, 8192);
			$this->end = feof($this->handle);
			if(!xml_parse($this->parser, $data, $this->end))
			{
				/*die(sprintf("XML error: %s at line %d",
							xml_error_string(xml_get_error_code($this->parser));
							xml_get_current_line_number($this->parser)));*/
			}
			$this->index = 0;
			$this->cnt = count($this->arData);
		}

		if($this->cnt==0) return false;
		//$this->arNode = $arNode = array_shift($this->arData);
		$this->arNode = $this->arData[$this->index++];
		$this->moveToElement();

		return true;
	}

	public function next()
	{
		$this->moveToElement();
		$depth = $this->depth;
		$nodeName = $this->name;
		while($this->read())
		{
			if($this->depth < $depth) return $this->decrementIndex();
			if($this->depth==$depth)
			{
				if($this->name==$nodeName) return true;
				else return $this->decrementIndex();
			}
		}
		return false;
	}
	
	public function decrementIndex()
	{
		$this->index--;
		return false;
	}

	public function moveToFirstAttribute()
	{
		if(!is_array($this->attributes) || count($this->attributes)==0) return false;
		reset($this->attributes);
		$this->nodeType = self::ATTRIBUTE;
		$this->depth = $this->depthInner;
		$this->value = current($this->attributes);
		$this->name = key($this->attributes);
		$this->namespaceURI = '';
		$this->attributeKey = 1;
		return true;
	}

	public function moveToNextAttribute()
	{
		if(!is_array($this->attributes) || count($this->attributes) < $this->attributeKey + 1) return false;
		$this->nodeType = self::ATTRIBUTE;
		$this->depth = $this->depthInner;
		$this->value = next($this->attributes);
		$this->name = key($this->attributes);
		$this->namespaceURI = '';
		$this->attributeKey++;
		return true;
	}

	public function moveToElement()
	{
		if(!isset($this->arNode)) return false;
		$this->attributeKey = 0;
		$arNode = $this->arNode;
		$this->nodeType = (array_key_exists('nodeType', $arNode) ? $arNode['nodeType'] : null);
		$this->depth = (array_key_exists('depth', $arNode) ? $arNode['depth'] : null);
		$this->name = (array_key_exists('name', $arNode) ? $arNode['name'] : null);
		$this->value = (array_key_exists('value', $arNode) ? $arNode['value'] : null);
		$this->namespaceURI = (array_key_exists('namespaceURI', $arNode) ? $arNode['namespaceURI'] : null);
		$this->attributes = (array_key_exists('attributes', $arNode) ? $arNode['attributes'] : null);
		return true;
	}

	public function startElement($parser, $name, $attrs)
	{
		$this->depthInner++;
		$this->inElement = true;
		$this->textData = '';
		$this->arData[] = array(
			'nodeType' => self::ELEMENT,
			'depth' => $this->depthInner,
			'name' => $name,
			'attributes' => $attrs
		);
		$this->lastNode = $name.'|'.$this->depthInner;
	}

	public function endElement($parser, $name)
	{
		if($this->lastNode==$name.'|'.$this->depthInner /*&& strlen(trim($this->textData)) > 0*/)
		{
			$this->arData[] = array(
				'nodeType' => self::TEXT,
				'depth' => $this->depthInner,
				'value' => $this->textData
			);
		}
		$this->depthInner--;
		$this->inElement = false;
	}

	public function characterData($parser, $data)
	{
		$this->textData .= $data;
		
		/*
		//if(strlen(trim($data))==0) return;
		if(!$this->inElement && strlen(trim($data))==0) return;
		//if(!is_string($data) || strlen($data)==0) return;
		//print_r($data);
		$this->arData[] = array(
			'nodeType' => self::TEXT,
			'depth' => $this->depthInner,
			'value' => $data
		);
		*/
	}
}