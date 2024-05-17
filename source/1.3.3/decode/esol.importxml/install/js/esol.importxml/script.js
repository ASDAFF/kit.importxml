var esolIXModuleName = 'esol.importxml';
var esolIXModuleFilePrefix = 'esol_import_xml';
var EIXPreview = {
	Init: function()
	{
		eval('var params = ' + $('#esol_ix_xml_wrap input[name="settings_json"]').val() + ';');
		eval('var extraparams = ' + $('#esol_ix_xml_wrap input[name="extrasettings_json"]').val() + ';');
		eval('var defparams = ' + $('#esol_ix_xml_wrap input[name="defaultsettings_json"]').val() + ';');
		$('#esol_ix_xml_wrap input[name="settings_json"]').remove();
		$('#esol_ix_xml_wrap input[name="extrasettings_json"]').remove();
		$('#esol_ix_xml_wrap input[name="defaultsettings_json"]').remove();
		this.oldParams = params;
		this.oldExtraparams = extraparams;
		var saveOldTags = (defparams.SAVE_DISAPPEARED_TAGS && defparams.SAVE_DISAPPEARED_TAGS=='Y');
		var configWrap = $('#esol_ix_xml_wrap .esol_ix_xml_settings');

		if(params.GROUPS)
		{
			for(var i in params.GROUPS)
			{
				this.currentTag = this.GetTagByXPath(params.GROUPS[i]);
				if(this.currentTag)
				{
					this.SetBaseElement(i, true);
				}
				else if(saveOldTags)
				{
					configWrap.append('<input name="SETTINGS[OLD_GROUPS][]" value="'+i+'" type="hidden">');
					input = $('<input name="SETTINGS[GROUPS]['+i+']" value="" type="hidden">');
					input.val(params.GROUPS[i]);
					configWrap.append(input);
				}
			}
		}
		if(params.FIELDS)
		{
			var val, xpath, arVals, valObj, option, input;
			var selectNames = ['section_fields'/*, 'subsection_fields'*/, 'element_fields', 'property_fields', 'offproperty_fields', 'ibproperty_fields', 'ibpropval_fields', 'store_fields', 'reststore_fields'];
			var subsectName = 'section_fields';
			for(var i=1; i<5; i++)
			{
				subsectName = 'sub'+subsectName;
				selectNames.push(subsectName);
			}
			for(var j=0; j<selectNames.length; j++)
			{
				var select = $('#esol_ix_xml_wrap select[name="'+selectNames[j]+'"]');
				for(var i in params.FIELDS)
				{
					arVals = params.FIELDS[i].split(';');
					xpath = arVals[0];
					val = arVals[1];
					valObj = this.GetValObjByXPath(xpath);
					if(valObj != false)
					{
						option = select.find('option[value="'+val+'"]');
						if(option.length > 0) this.SetFieldValue(valObj, option[0], i, (typeof extraparams=='object' ? extraparams[i] : ''));
					}
					else if(saveOldTags)
					{
						option = select.find('option[value="'+val+'"]');
						if(option.length > 0)
						{
							configWrap.append('<input name="SETTINGS[OLD_FIELDS][]" value="'+i+'" type="hidden">');
							input = $('<input name="SETTINGS[FIELDS]['+i+']" value="" type="hidden">');
							input.val(xpath+';'+val);
							configWrap.append(input);
							if(typeof extraparams=='object' && extraparams[i] && typeof extraparams[i]=='object')
							{
								input = $('<input name="EXTRASETTINGS['+i+']" value="" type="hidden">');
								input.val(JSON.stringify(extraparams[i]));
								configWrap.append(input);
							}
						}
					}
				}
			}
		}
		
		var sectionSelect = $('#preview_file .esol_ix_section_section select')
		if(typeof sectionSelect.chosen == 'function') sectionSelect.chosen({search_contains: true});
	},
	
	ShowBaseElements: function(link)
	{
		this.currentTag = $(link).closest('.esol_ix_xml_struct_item');
		//var arElems = ['ELEMENT', 'PROPERTY', 'OFFER', 'SECTION', 'SUBSECTION', 'IBPROPERTY'];
		var arElems = ['ELEMENT', 'SECTION', 'IBPROPERTY', 'STORE'];
		
		var parentBE = this.currentTag.closest('.esol_ix_xml_struct_item[data-base-element]').attr('data-base-element');
		if(parentBE)
		{
			parentBE = parentBE.toUpperCase();
			if(parentBE=='ELEMENT') arElems = ['PROPERTY', 'OFFER', 'OFFPROPERTY', 'SECTION', 'RESTSTORE'];
			if(parentBE=='PROPERTY') arElems = [];
			if(parentBE=='OFFER') arElems = ['OFFPROPERTY'];
			if(parentBE=='IBPROPERTY') arElems = ['IBPROPVAL'];
			if(parentBE=='SECTION') arElems = ['ELEMENT', 'SUBSECTION'];
			if(parentBE=='SUBSECTION') arElems = ['ELEMENT', 'SUBSUBSECTION'];
			if(parentBE=='SUBSUBSECTION') arElems = ['SUBSUBSUBSECTION'];
			if(parentBE=='SUBSUBSUBSECTION') arElems = ['SUBSUBSUBSUBSECTION'];
			if(parentBE=='SUBSUBSUBSUBSECTION') arElems = ['SUBSUBSUBSUBSUBSECTION'];
			if(parentBE=='STORE') arElems = [];
			var subsectName = 'SUBSECTION';
			for(var i=2; i<5; i++)
			{
				subsectName = 'SUB'+subsectName;
				if(parentBE==subsectName) arElems = ['SUB'+subsectName];
			}
		}
		if(this.currentTag.attr('data-base-element')) arElems = [];
		
		var existsElems = [];
		var existsObjs = $('#esol_ix_xml_wrap .esol_ix_xml_struct_item[data-base-element]');
		for(var i=0; i<existsObjs.length; i++)
		{
			existsElems.push($(existsObjs[i]).attr('data-base-element').toUpperCase());
		}
		
		var menuItems = [];
		for(var i=0; i<arElems.length; i++)
		{
			if($.inArray(arElems[i], existsElems)!=-1 ||
				((arElems[i]=='OFFER' || arElems[i]=='OFFPROPERTY') && ($('#esol_ix_xml_wrap select[name="offer_fields"]').length == 0)))
			{
				continue;
			}
			menuItems.push({
				TEXT: this.GetGroupTitle(arElems[i]),
				ONCLICK: 'EIXPreview.SetBaseElement("'+arElems[i]+'")'
			});
		}
		
		if(link.OPENER) link.OPENER.SetMenu(menuItems);
		if(menuItems.length > 0)
		{
			BX.adminShowMenu(link, menuItems, {active_class: "bx-adm-scale-menu-butt-active"});
		}
		return true;
	},
	
	GetGroupTitle: function(name)
	{
		if(name.indexOf('SUBSECTION')!=-1) name = 'SUBSECTION';
		return BX.message("ESOL_IX_GROUP_"+name);
	},
	
	SetBaseElement: function(type, firstInit)
	{
		if(type)
		{
			this.UnsetBaseElement(type);
			$(this.currentTag).closest('.esol_ix_xml_struct_item').attr('data-base-element', type.toLowerCase());
			$(this.currentTag).find('> .esol_ix_group_value').html('<input type="hidden" name="SETTINGS[GROUPS]['+type+']" value=""><span class="esol_ix_group_value_inner_'+type.toLowerCase()+'">'+this.GetGroupTitle(type)+'<a href="javascript:void(0)" onclick="return EIXPreview.ShowBaseElemSettings(event, this)" class="esol_ix_group_value_settings inactive" title="'+BX.message("ESOL_IX_BASE_ELEM_SETTINGS")+'"></a><a href="javascript:void(0)" onclick="return EIXPreview.UnsetBaseElement(\''+type+'\')" class="esol_ix_group_value_close" title="'+BX.message("ESOL_IX_REMOVE_FIELD")+'"></a></span>');
			var typeXpath = this.GetXPathByTag(this.currentTag);
			$('#esol_ix_xml_wrap input[name="SETTINGS[GROUPS]['+type+']"]').val(typeXpath);
			this.InitElementFields();
			
			
			if(!firstInit && this.oldParams && typeof this.oldParams=='object')
			{
				var params = this.oldParams;
				var extraparams = this.oldExtraparams
				var oldTypeXpath = params.GROUPS[type];
				
				if(params.FIELDS && oldTypeXpath)
				{
					var val, xpath, arVals, valObj, option;

					var select = $('#esol_ix_xml_wrap select[name="'+type.toLowerCase()+'_fields"]');
					for(var i in params.FIELDS)
					{
						arVals = params.FIELDS[i].split(';');
						xpath = arVals[0];
						val = arVals[1];
						if(xpath.indexOf(oldTypeXpath)==0)
						{
							xpath = typeXpath + xpath.substr(oldTypeXpath.length);
						}
						valObj = this.GetValObjByXPath(xpath);
						if(valObj != false)
						{
							option = select.find('option[value="'+val+'"]');
							if(option.length > 0) this.SetFieldValue(valObj, option[0], i, (typeof extraparams=='object' ? extraparams[i] : ''));
						}
					}
				}
				
				params.GROUPS[type] = null;
				//this.oldParams = null;
			}
		}
	},
	
	InitElementFields: function()
	{
		var obj = this;
		var objValue = $(this.currentTag).find('.esol_ix_str_value:not(.esol_ix_str_value_active)');
		objValue.addClass('esol_ix_str_value_active');
		objValue.find('.esol_ix_str_value_val').bind('click', function(){obj.ShowElementFields(this);}).bind('contextmenu', function(e){return obj.AddFieldContext(e, this);});
		//$(this.currentTag).find('.esol_ix_str_value[data-attr]').bind('contextmenu', function(e){return obj.ShowAttrActions(e, this);});
	},
	
	UnsetBaseElement: function(type)
	{
		if(type)
		{
			var oldInput = $('#esol_ix_xml_wrap input[name="SETTINGS[GROUPS]['+type+']"]');
			if(oldInput.length > 0)
			{
				var parentItem = oldInput.closest('.esol_ix_xml_struct_item');
				parentItem.removeAttr('data-base-element');
				parentItem.find('.esol_ix_xml_struct_item').removeAttr('data-base-element');
				parentItem.find('.esol_ix_group_value').html('');
				var objValue = parentItem.find('.esol_ix_str_value');
				objValue.find('.esol_ix_str_value_field .esol_ix_str_value_close').trigger('click');
				objValue.removeClass('esol_ix_str_value_active');
				objValue.find('.esol_ix_str_value_val').unbind('click').unbind('contextmenu');
				
				var gParentItem = parentItem.closest('.esol_ix_xml_struct_item[data-base-element]');
				if(gParentItem.length > 0)
				{
					this.currentTag = gParentItem;
					this.InitElementFields();
				}
			}
		}
	},
	
	UnsetAutoSettings: function(a)
	{
		var gInputs = $('#esol_ix_xml_wrap input[name^="SETTINGS[GROUPS]["]');
		for(var i=0; i<gInputs.length; i++)
		{
			this.UnsetBaseElement(gInputs[i].name.substr(17, gInputs[i].name.length-18));
		}
		this.oldParams = null;
		$(a).closest('.esol_ix_xml_struct_warning').remove();
	},
	
	GetXPathByTag: function(tag)
	{
		if($(tag).attr('data-attr')) var xpath = '@'+$(tag).attr('data-attr');
		else var xpath = $(tag).attr('data-name');
		while((tag = $(tag).parent()) && tag.hasClass('esol_ix_xml_struct_item'))
		{
			xpath = $(tag).attr('data-name') + '/'+ xpath;
		}
		return xpath;
	},
	
	GetXPathByVal: function(valObj)
	{
		var xpath = this.GetXPathByTag(valObj.closest('.esol_ix_xml_struct_item'));
		if(valObj.attr('data-attr'))
		{
			xpath += '/@'+valObj.attr('data-attr');
		}
		return xpath;
	},
	
	GetTagByXPath: function(xpath)
	{
		var arPath = xpath.split('/');
		var parent = $('#esol_ix_xml_wrap .esol_ix_xml_struct');
		var i = 0;
		while(i < arPath.length && (parent = parent.find('> .esol_ix_xml_struct_item[data-name="'+arPath[i]+'"]')) && parent.length > 0){i++;}
		if(i < arPath.length) return false;
		return parent;
	},
	
	GetValObjByXPath: function(xpath)
	{
		var attr = '';
		var arPath = xpath.split('/');
		if(arPath[arPath.length - 1].substr(0, 1)=='@')
		{
			attr = arPath.pop().substr(1);
			xpath = arPath.join('/');
		}
		var tag = this.GetTagByXPath(xpath);
		if(tag==false) return false;
		
		var valObj = tag.find('> .esol_ix_str_value' + (attr.length > 0 ? '[data-attr="'+attr+'"]' : ':not([data-attr])'));
		if(valObj.length==0) return false;
		return valObj;
	},
	
	ShowElementFields: function(valObj, event)
	{
		var obj = this;
		valObj = $(valObj);
		var copySettings = ((typeof event == 'object') && (event.ctrlKey || event.shiftKey));
		var fieldsCode = valObj.closest('.esol_ix_xml_struct_item[data-base-element]').attr('data-base-element');
		var pSelect = $('#esol_ix_xml_wrap select[name="'+fieldsCode+'_fields"]');
		var select = $(pSelect).clone();
		var options = select[0].options;
		var oldValue = this.GetFieldValue(valObj);
		for(var i=0; i<options.length; i++)
		{
			if(oldValue==options.item(i).value) options.item(i).selected = true;
		}
		
		var chosenId = 'esolix_select_chosen';
		$('#'+chosenId).remove();
		var offset = valObj.offset();
		var div = $('<div></div>');
		div.attr('id', chosenId);
		div.css({
			position: 'absolute',
			left: offset.left,
			top: offset.top
		});
		div.append(select);
		$('body').append(div);
		
		if(typeof select.chosen == 'function') select.chosen({search_contains: true});
		select.bind('change', function(){
			var option = options.item(select[0].selectedIndex);
			var settings = false;
			if(copySettings)
			{
				settings = valObj.prev('.esol_ix_str_value_field').find('.esol_ix_str_value_settings input').val();
				if(settings.length > 0) eval('settings = '+settings+';');
			}
			if(typeof settings == 'object')
			{
				obj.SetFieldValue(valObj, option, false, settings);
			}
			else
			{
				obj.SetFieldValue(valObj, option);
			}
			if(typeof select.chosen == 'function') select.chosen('destroy');
			$('#'+chosenId).remove();
		});
		
		$('body').one('click', function(e){
			e.stopPropagation();
			return false;
		});
		var chosenDiv = select.next('.chosen-container')[0];
		$('a:eq(0)', chosenDiv).trigger('mousedown');
		
		var lastClassName = chosenDiv.className;
		var interval = setInterval( function() {   
			   var className = chosenDiv.className;
				if (className !== lastClassName) {
					select.trigger('change');
					lastClassName = className;
					clearInterval(interval);
				}
			},30);
	},
	
	ShowAttrActions: function(e, valObj)
	{
		return;
		this.currentAttr = $(valObj);
		var linkObj = $(valObj).prev('.esol_ix_str_value_cm');
		if(linkObj.length == 0)
		{
			$(valObj).before('<a href="javascript:void(0)" class="esol_ix_str_value_cm"></a>');
			linkObj = $(valObj).prev('.esol_ix_str_value_cm');
			
			var menuItems = [];
			menuItems.push({
				TEXT: BX.message("ESOL_IX_SHOW_ALL_ATTRIBUTES"),
				ONCLICK: 'EIXPreview.SetGroupTags()'
			});
			BX.adminShowMenu(linkObj[0], menuItems, {active_class: "bx-adm-scale-menu-butt-active"});
		}
		else
		{
			BX.fireEvent(linkObj[0], 'click');
		}
		return false;
	},
	
	SetGroupTags: function()
	{
		var xpath = this.GetXPathByTag(this.currentAttr);
		var post = $(this.currentAttr).closest('form').serialize() + '&ACTION=GET_GROUP_TAGS';
		$.post(window.location.href, post, function(data){
			alert(data);
		});
	},
	
	GetFieldXpath: function(valObj)
	{
		var input = valObj.find('input[name^="SETTINGS[FIELDS]["]');
		if(input.length > 0)
		{
			var arVals = input.val().split(';');
			return arVals[0];
		}
		return '';
	},
	
	GetFieldValue: function(valObj)
	{
		var input = valObj.find('input[name^="SETTINGS[FIELDS]["]');
		if(input.length > 0)
		{
			var arVals = input.val().split(';');
			if(arVals.length==2) return arVals[1];
		}
		return '';
	},
	
	SetFieldValue: function(valObj, option, num, extraparams)
	{
		valObj = $(valObj);
		var valObjParent = valObj.closest('.esol_ix_str_value');
		if((typeof option == 'object') && option.value)
		{
			var textValue = '';
			var optgroup = $(option).closest('optgroup');
			if(optgroup.length > 0)
			{
				textValue = optgroup.attr('label');
				if(textValue.length > 0) textValue += ' - ';
			}
			textValue += option.text;
			var xpath = this.GetXPathByVal(valObjParent);
			
			if(valObj.hasClass('esol_ix_str_value_field'))
			{
				var span = valObj;
				if(!num && num!==0)
				{
					var input = $('input[name^="SETTINGS[FIELDS]["]', span);
					if(input.length > 0)
					{
						num = input.attr('name').replace(/^.*\[(\d+)\]$/, '$1');
					}
				}
			}
			else
			{
				var obj = this;
				var valObjVal = valObjParent.find('.esol_ix_str_value_val');
				if(!valObjVal.hasClass('esol_ix_str_value_val_selected'))
				{
					valObjVal.addClass('esol_ix_str_value_val_selected').unbind('click').unbind('contextmenu');
					//valObjParent.append('<a href="javascript:void(0)" onclick="return EIXPreview.ShowElementFields(this, event)" class="esol_ix_str_value_add" title="'+BX.message("ESOL_IX_ADD_FIELD")+'"></a>');
					var addLink = $('<a href="javascript:void(0)" class="esol_ix_str_value_add" title="'+BX.message("ESOL_IX_ADD_FIELD")+'\r\n\r\n'+BX.message("ESOL_IX_ADD_FIELD_COPY_SETTING")+'"></a>');
					addLink.bind('click', function(e){return EIXPreview.ShowElementFields(this, e)}).bind('contextmenu', function(e){return EIXPreview.AddFieldContext(e, this);});
					valObjParent.append(addLink);
				}
				if(!num && num!==0)
				{
					var inputs = $('#esol_ix_xml_wrap input[name^="SETTINGS[FIELDS]["]');
					var i = 0;
					while($('#esol_ix_xml_wrap input[name="SETTINGS[FIELDS]['+i+']"]').length > 0)
					{
						i++;
					}
					num = i;
				}
				var span = $('<span class="esol_ix_str_value_field'+(this.IsInactiveField(num) ? ' esol_ix_str_value_field_inactive' : '')+'"><input type="hidden" name="SETTINGS[FIELDS]['+num+']" value=""><span></span><a href="javascript:void(0)" onclick="return EIXPreview.ShowFieldSettings(event, this)" class="esol_ix_str_value_settings" id="field_settings_'+num+'" title="'+BX.message("ESOL_IX_FIELD_SETTINGS")+'"><input name="EXTRASETTINGS['+num+']" value="" type="hidden"></a><a href="javascript:void(0)" onclick="return EIXPreview.DeleteFieldValue(event, this)" class="esol_ix_str_value_close" title="'+BX.message("ESOL_IX_REMOVE_FIELD")+'"></a></span>');
				span.insertBefore(valObjParent.find('.esol_ix_str_value_add'));
				$('>span:first', span).bind('contextmenu', function(e){return obj.DeleteFieldContext(e, this);});
				$('a', span).bind('contextmenu', function(e){e.stopPropagation(); return true;});
				//valObjParent.append(span);
				
				var btn = span.find('.esol_ix_str_value_settings');
				if(typeof extraparams=='object')
				{
					if(extraparams.FIELD_NOTE) span.attr('title', extraparams.FIELD_NOTE);
					if(extraparams.UPLOAD_VALUES || extraparams.NOT_UPLOAD_VALUES) btn.addClass("filtered");
					span.find('.esol_ix_str_value_settings input').val(JSON.stringify(extraparams));
				}
				else
				{
					btn.addClass("inactive");
				}
				span.bind('click', function(){obj.ShowElementFields(this);});
			}
			
			if(option.value=='VARIABLE') textValue += ' {'+num+'}';
			span.find('span').html(textValue);
			span.find('input[name^="SETTINGS[FIELDS]["]').val(xpath+';'+option.value);
		}
		else
		{
			if(valObj.hasClass('esol_ix_str_value_field'))
			{
				valObj.find('a.esol_ix_str_value_close').trigger('click');
			}
		}
	},
	
	DeleteFieldValue: function(e, link)
	{
		e.stopPropagation();
		var index = $(link).closest('.esol_ix_str_value_field').find('input[type="hidden"]:first').attr('name').replace(/^.*\[([^\]]*)\]$/, '$1');
		this.RemoveInactiveField(index);
		
		var parent = $(link).closest('.esol_ix_str_value');
		$(link).closest('.esol_ix_str_value_field').remove();
		if(parent.find('.esol_ix_str_value_field').length==0)
		{
			var obj = this;
			parent.find('.esol_ix_str_value_val').removeClass('esol_ix_str_value_val_selected').bind('click', function(){obj.ShowElementFields(this);}).bind('contextmenu', function(e){return obj.AddFieldContext(e, this);});
			parent.find('.esol_ix_str_value_add').remove();
		}
		return false;
	},
	
	ContextFieldValueAction: function(action)
	{
		if(!this.currentFieldWrap) return;
		if(action=='cut' || action=='copy')
		{
			var settings = this.currentFieldWrap.find('.esol_ix_str_value_settings input').val();
			if(settings.length > 0) eval('settings = '+settings+';');
			this.bufferFieldObject = {
				'field': $('input[name^="SETTINGS[FIELDS]"]', this.currentFieldWrap).val().split(';')[1],
				'extrasettings': settings
			};
		}
		if(action=='cut' || action=='delete')
		{
			$('.esol_ix_str_value_close', this.currentFieldWrap).trigger('click');
		}
		
		if(action=='activate' || action=='deactivate')
		{
			var index = $('a.esol_ix_str_value_settings input[type=hidden]', this.currentFieldWrap).attr('name').replace(/^.*\[([^\]]*)\]$/, '$1');
			if(action=='activate')
			{
				$(this.currentFieldWrap).removeClass('esol_ix_str_value_field_inactive');
				this.RemoveInactiveField(index);
			}
			if(action=='deactivate')
			{
				$(this.currentFieldWrap).addClass('esol_ix_str_value_field_inactive');
				this.AddInactiveField(index);
			}
		}
	},
	
	AddInactiveField: function(index)
	{
		var dfInput = $('#esol_ix_xml_wrap input[name="SETTINGS[INACTIVE_FIELDS]"]');
		dfInput.val(dfInput.val() + (dfInput.val().length > 0 ? ';' : '') + index);
	},
	
	RemoveInactiveField: function(index)
	{
		var dfInput = $('#esol_ix_xml_wrap input[name="SETTINGS[INACTIVE_FIELDS]"]');
		var arVals = dfInput.val().split(';');
		var arValsNew = [];
		for(var i=0; i<arVals.length; i++)
		{
			if(arVals[i].length > 0 && arVals[i]!=index) arValsNew.push(arVals[i]);
		}
		dfInput.val(arValsNew.join(';'));
	},
	
	IsInactiveField: function(index)
	{
		var dfInput = $('#esol_ix_xml_wrap input[name="SETTINGS[INACTIVE_FIELDS]"]');
		var arVals = dfInput.val().split(';');
		for(var i=0; i<arVals.length; i++)
		{
			if(arVals[i].length==0) continue;
			if(arVals[i]==index) return true;
		}
		return false;
	},
	
	DeleteFieldContext: function(e, linkObj)
	{
		e.stopPropagation();
		this.currentFieldWrap = $(linkObj).closest('.esol_ix_str_value_field');
		var spanClass = 'esol_ix_str_value_close_context';
		var span = $('span.'+spanClass, this.currentFieldWrap);
		var menuItems = [];
		if(this.currentFieldWrap.hasClass('esol_ix_str_value_field_inactive'))
		{
			menuItems.push({TEXT: BX.message("ESOL_IX_ACTIVATE_FIELD"), ONCLICK: 'EIXPreview.ContextFieldValueAction("activate")'});
		}
		else
		{
			menuItems.push({TEXT: BX.message("ESOL_IX_DEACTIVATE_FIELD"), ONCLICK: 'EIXPreview.ContextFieldValueAction("deactivate")'});
		}
		menuItems.push({TEXT: BX.message("ESOL_IX_CUT_FIELD"), ONCLICK: 'EIXPreview.ContextFieldValueAction("cut")'});
		menuItems.push({TEXT: BX.message("ESOL_IX_COPY_FIELD"), ONCLICK: 'EIXPreview.ContextFieldValueAction("copy")'});
		if(span.length==0)
		{
			span = $('<span class="'+spanClass+'"></span>');
			span.appendTo(this.currentFieldWrap);
			BX.adminShowMenu(span[0], menuItems, {active_class: "bx-adm-scale-menu-butt-active"});
		}
		else
		{
			if(span[0].OPENER) span[0].OPENER.SetMenu(menuItems);
			BX.fireEvent(span[0], 'click');
		}
		return false;
	},
	
	ContextNewFieldAction: function(action)
	{
		if(!this.currentNewFieldBtn) return;
		if(action=='add')
		{
			this.currentNewFieldBtn.trigger('click');
			return;
		}
		if(action=='paste' && this.bufferFieldObject)
		{
			var fieldsCode = this.currentNewFieldBtn.closest('.esol_ix_xml_struct_item[data-base-element]').attr('data-base-element');
			var pSelect = $('#esol_ix_xml_wrap select[name="'+fieldsCode+'_fields"]');
			var select = $(pSelect).clone();
			var option = $('option[value="'+this.bufferFieldObject.field+'"]', select);
			if(option.length > 0)
			{
				this.SetFieldValue(this.currentNewFieldBtn, option[0], false, this.bufferFieldObject.extrasettings);
			}
			return;
		}
		/*if(action=='cut' || action=='copy')
		{
			this.bufferFieldObject = {
				'field': $('input[name^="SETTINGS[FIELDS]"]', this.currentFieldWrap).val().split(';')[1],
				'extrasettings': $('input[name^="EXTRASETTINGS["]', this.currentFieldWrap).val()
			};
		}
		if(action=='cut' || action=='delete')
		{
			$('.esol_ix_str_value_close', this.currentFieldWrap).trigger('click');
		}*/
	},
	
	AddFieldContext: function(e, linkObj)
	{
		if($(linkObj).hasClass('esol_ix_str_value_val_selected')) return true;
		e.stopPropagation();
		this.currentNewFieldBtn = $(linkObj);
		var menuItems = []
		menuItems.push({TEXT: BX.message("ESOL_IX_ADD_FIELD"), ONCLICK: 'EIXPreview.ContextNewFieldAction("add")'});
		if(this.bufferFieldObject) menuItems.push({TEXT: BX.message("ESOL_IX_PASTE_FIELD"), ONCLICK: 'EIXPreview.ContextNewFieldAction("paste")'});
		var spanClass = 'esol_ix_str_value_add_context';
		var span = $('span.'+spanClass, linkObj);
		if(span.length==0)
		{
			span = $('<span class="'+spanClass+'"></span>');
			span.appendTo(linkObj);
			BX.adminShowMenu(span[0], menuItems, {active_class: "bx-adm-scale-menu-butt-active"});
		}
		else
		{
			if(span[0].OPENER) span[0].OPENER.SetMenu(menuItems);
			BX.fireEvent(span[0], 'click');
		}
		return false;
	},	
	
	ShowBaseElements2: function(link)
	{
		var pSelect = $('#esol_ix_xml_wrap select[name="group"]');
		var select = $(pSelect).clone();
		var options = select[0].options;
		/*for(var i=0; i<options.length; i++)
		{
			if(inputVal.value==options.item(i).value) options.item(i).selected = true;
		}*/
		
		var chosenId = 'esolix_select_chosen';
		$('#'+chosenId).remove();
		var offset = $(link).offset();
		var div = $('<div></div>');
		div.attr('id', chosenId);
		div.css({
			position: 'absolute',
			left: offset.left,
			top: offset.top,
			width: 300
		});
		div.append(select);
		$('body').append(div);
		
		if(typeof select.chosen == 'function') select.chosen();
		select.bind('change', function(){
			var option = options.item(select[0].selectedIndex);
			/*if(option.value)
			{
				input.value = option.text;
				input.title = option.text;
				inputVal.value = option.value;
			}
			else
			{
				input.value = '';
				input.title = '';
				inputVal.value = '';
			}*/
			if(typeof select.chosen == 'function') select.chosen('destroy');
			$('#'+chosenId).remove();
		});
		
		$('body').one('click', function(e){
			e.stopPropagation();
			return false;
		});
		var chosenDiv = select.next('.chosen-container')[0];
		$('a:eq(0)', chosenDiv).trigger('mousedown');
		
		var lastClassName = chosenDiv.className;
		var interval = setInterval( function() {   
			   var className = chosenDiv.className;
				if (className !== lastClassName) {
					select.trigger('change');
					lastClassName = className;
					clearInterval(interval);
				}
			},30);
	},
	
	ShowBaseElemSettings: function(e, btn)
	{
		e.stopPropagation();
		
		var form = $(btn).closest('form')[0];
		var parentNode = $(btn).closest('.esol_ix_xml_struct_item[data-base-element]');
		var fieldsCode = parentNode.attr('data-base-element').toUpperCase();
		var map = $('#esol_ix_xml_wrap input[name="SETTINGS['+fieldsCode+'_MAP]"]').val();
		var xpath = $(btn).closest('.esol_ix_group_value').find('input[name="SETTINGS[GROUPS]['+fieldsCode+']"]').val();
		var xpathsMulti = $('#esol_ix_xml_wrap input[name="SETTINGS[XPATHS_MULTI]"]').val();
		var fields = {};
		var inputFields = $('input[name^="SETTINGS[FIELDS]["]', parentNode);
		for(var i=0; i<inputFields.length; i++)
		{
			fields[inputFields[i].name.substr(17, inputFields[i].name.length-18)] = inputFields[i].value;
		}
		var innerGroups = {};
		var inputGroups = $('input[name^="SETTINGS[GROUPS]["]', parentNode);
		for(var i=0; i<inputGroups.length; i++)
		{
			innerGroups[inputGroups[i].name.substr(17, inputGroups[i].name.length-18)] = inputGroups[i].value;
		}
		
		var postData = {'GROUP': fieldsCode, 'XPATH': xpath, 'FIELDS': fields, 'INNER_GROUPS': innerGroups, 'MAP': map, 'XPATHS_MULTI': xpathsMulti};
		
		if(fieldsCode=='PROPERTY')
		{
			var allGroups = {}
			var sectionGroup = $(form).find('input[name="SETTINGS[GROUPS][SECTION]"]');
			if(sectionGroup.length > 0) allGroups.SECTION = {GROUP: sectionGroup.val(), FIELDS: []};
			var inputFields = $('input[name^="SETTINGS[FIELDS]["]', sectionGroup.closest('.esol_ix_xml_struct_item[data-base-element]'));
			for(var i=0; i<inputFields.length; i++)
			{
				allGroups.SECTION.FIELDS[inputFields[i].name.substr(17, inputFields[i].name.length-18)] = inputFields[i].value;
			}
			var elementGroup = $(form).find('input[name="SETTINGS[GROUPS][ELEMENT]"]');
			if(elementGroup.length > 0) allGroups.ELEMENT = {GROUP: elementGroup.val(), FIELDS: []};
			var inputFields = $('input[name^="SETTINGS[FIELDS]["]', elementGroup.closest('.esol_ix_xml_struct_item[data-base-element]'));
			for(var i=0; i<inputFields.length; i++)
			{
				allGroups.ELEMENT.FIELDS[inputFields[i].name.substr(17, inputFields[i].name.length-18)] = inputFields[i].value;
			}
			var sectionInnerGroups = {};
			var inputGroups = $('input[name^="SETTINGS[GROUPS]["]', sectionGroup.closest('.esol_ix_xml_struct_item[data-base-element]'));
			for(var i=0; i<inputGroups.length; i++)
			{
				sectionInnerGroups[inputGroups[i].name.substr(17, inputGroups[i].name.length-18)] = inputGroups[i].value;
			}
			
			postData.ALLGROUPS = allGroups;
			postData.SECTION_INNER_GROUPS = sectionInnerGroups;
			postData.SECTION_MAP = $('#esol_ix_xml_wrap input[name="SETTINGS[SECTION_MAP]"]').val();
		}
		
		var dialogParams={
			'title':BX.message("ESOL_IX_POPUP_BE_SETTINGS_"+fieldsCode),
			'content_url':'/bitrix/admin/'+esolIXModuleFilePrefix+'_group_'+fieldsCode.toLowerCase()+'.php?lang='+BX.message('LANGUAGE_ID')+'&PROFILE_ID='+form.PROFILE_ID.value,
			'width':'900',
			'height':'500',
			'resizable':true,
			'content_post':postData
		};
		var dialog = new BX.CAdminDialog(dialogParams);
			
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})
		]);
			
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			$('input[type=checkbox]', this.DIV).each(function(){
				BX.adminFormTools.modifyCheckbox(this);
			});
		});
			
		dialog.Show();
		
		return false;
	},
	
	ShowFieldSettings: function(e, btn, btn2)
	{
		e.stopPropagation();
		
		var postextra = $('input', btn).val();
		if(btn2 && typeof btn2=='object')
		{
			var parent = $(btn).closest('.esol-ix-select-mapping');
			var title = $(btn).prev('a').html();
			var val = $('input[name$="][ID]"]', parent).val();
			//var name = $(btn).find('input[type=hidden]').attr('name');
			var index = $(btn).prop('id').replace('field_settings_', '');
			var name = 'EXTRASETTINGS['+index+']';
			var xpath = parent.closest('.esol-ix-select-mapping-wrap').attr('data-xpath');
			if(!xpath) xpath = 'none';
			btn = btn2;
		}
		else
		{
			var parent = $(btn).closest('.esol_ix_str_value_field');
			var title = parent.find('>span:eq(0)').html().replace(/\s+\{\d+\}$/, '');
			var val = this.GetFieldValue(parent);
			var xpath = this.GetFieldXpath(parent);
			var name = $(btn).find('input[type=hidden]').attr('name');
			var index = name.replace(/^.*\[([^\]]*)\]$/, '$1');
		}
		
		var form = $(btn).closest('form')[0];
		var poststruct = $('#esol_ix_xml_wrap input[name="struct_base64"]').val();
		var fieldsCode = $(btn).closest('.esol_ix_xml_struct_item[data-base-element]').attr('data-base-element');
		var xPathList = {};
		var groups = $('#esol_ix_xml_wrap input[name^="SETTINGS[GROUPS]["]');
		for(var i=0; i<groups.length; i++)
		{
			var groupCode = groups[i].name.replace(/^.*\[([^\[]*)\]$/, '$1');
			xPathList[groupCode] = groups[i].value;
		}
		
		var dialogParams = {
			'title':BX.message("ESOL_IX_POPUP_FIELD_SETTINGS_TITLE") + ' "' + title + '" {'+index+'}',
			'content_url':'/bitrix/admin/'+esolIXModuleFilePrefix+'_field_settings.php?lang='+BX.message('LANGUAGE_ID')+'&field='+val+'&field_name='+name+'&xpath='+xpath+'&index='+index+'&PROFILE_ID='+form.PROFILE_ID.value,
			'width': '930',
			'height': '420',
			'resizable':true,
			'content_post':{'POSTEXTRA': postextra, 'POSTSTRUCT': poststruct, 'XPATH_LIST': xPathList, 'GROUP': fieldsCode.toUpperCase()}
		};
		var dialog = new BX.CAdminDialog(dialogParams);
			
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})
		]);
			
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			$('input[type=checkbox]', this.DIV).each(function(){
				BX.adminFormTools.modifyCheckbox(this);
			});
			ESettings.BindConversionEvents();
			$('select.esol-ix-select2text').each(function(){
				var s = $(this);
				s.wrap('<div class="esol-ix-select2text-wrap"></div>');
				new Select2Text(s.closest('div.esol-ix-select2text-wrap'), s);
			});
		});
			
		dialog.Show();
		
		return false;
	},
	
	SetExtraParams: function(oid, returnJson)
	{
		var title = '';
		$('#'+oid).removeClass("filtered");
		if(typeof returnJson == 'object')
		{
			if(returnJson.FIELD_NOTE) title = returnJson.FIELD_NOTE;
			if(returnJson.UPLOAD_VALUES || returnJson.NOT_UPLOAD_VALUES) $('#'+oid).addClass("filtered");
			returnJson = JSON.stringify(returnJson);
		}
		$('#'+oid).closest('.esol_ix_str_value_field').attr('title', title);
		if(returnJson.length > 0) $('#'+oid).removeClass("inactive");
		else $('#'+oid).addClass("inactive");
		$('#'+oid+' input').val(returnJson);
		if(BX.WindowManager.Get()) BX.WindowManager.Get().Close();
	},
	
	SetGroupSettings: function(data, group)
	{
		$('#esol_ix_xml_wrap input[name="SETTINGS['+group+'_MAP]"]').val(data.indexOf('esol_map_')==0 ? $('#'+data).html() : data);
		if(BX.WindowManager.Get()) BX.WindowManager.Get().Close();
	}
}

var EProfile = {
	Init: function()
	{
		var select = $('select#PROFILE_ID');
		if(select.length > 0)
		{
			if(typeof select.chosen == 'function')
			{
				setTimeout(function(){$('select#PROFILE_ID').chosen({search_contains: true})}, 500);
			}
			
			if(select.val().length > 0)
			{
				$.post(window.location.href, {'MODE': 'AJAX', 'ACTION': 'DELETE_TMP_DIRS'}, function(data){});
			}
			
			select = select[0]
			/*this.Choose(select[0]);*/
			if(select.value=='new')
			{
				$('#new_profile_name').css('display', '');
			}
			else
			{
				$('#new_profile_name').css('display', 'none');
			}
		
			var obj = this;
			$('select.adm-detail-iblock-list').bind('change', function(){
				$.post(window.location.href, {'MODE': 'AJAX', 'IBLOCK_ID': this.value, 'ACTION': 'GET_UID'}, function(data){
					var fields = $(data).find('select[name="fields[]"]');
					var select = $('select[name="SETTINGS_DEFAULT[ELEMENT_UID][]"]');
					var modeBtn = select.nextAll('.esol-ix-select-view-mode');
					var mode = modeBtn.attr('mode');
					if(mode=='chosen') modeBtn.trigger('click');
					obj.SetNewUid(select, fields);
					fields.attr('name', select.attr('name'));
					select.replaceWith(fields);
					if(mode=='chosen') modeBtn.trigger('click');
					
					var fields2 = $(data).find('select[name="fields_sku[]"]');
					var select2 = $('select[name="SETTINGS_DEFAULT[ELEMENT_UID_SKU][]"]');
					var modeBtn2 = select2.nextAll('.esol-ix-select-view-mode');
					var mode2 = modeBtn2.attr('mode');
					if(mode2=='chosen') modeBtn2.trigger('click');
					obj.SetNewUid(select2, fields2);
					fields2.attr('name', select2.attr('name'));
					select2.replaceWith(fields2);
					if(mode2=='chosen') modeBtn2.trigger('click');
					if(fields2[0].options.length > 0)
					{
						$('#element_uid_sku').show();
						$('.kda-sku-block.heading').show();
					}
					else
					{
						$('#element_uid_sku').hide();
						$('.kda-sku-block').hide();
						$('.kda-sku-block.heading .esol_ix_head_more').removeClass('show');
					}
					
					var fields = $(data).find('select[name="properties[]"]');
					var select = $('select[name="SETTINGS_DEFAULT[ELEMENT_PROPERTIES_REMOVE][]"]');
					fields.val(select.val());
					fields.attr('name', select.attr('name'));
					if(typeof $('select.kda-chosen-multi').chosen == 'function') $('select.kda-chosen-multi').chosen('destroy');
					select.replaceWith(fields);
					if(typeof $('select.kda-chosen-multi').chosen == 'function') $('select.kda-chosen-multi').chosen({width: '300px'});
				});
			});
			
			var select = $('select[name="SETTINGS_DEFAULT[ELEMENT_UID][]"]');
			if(select.length > 0 && !select.val()) select[0].options[0].selected = true;
			/*$('select.chosen').chosen();*/
			if(typeof $('select.kda-chosen-multi').chosen == 'function')
			{
				$('select.kda-chosen-multi').chosen({width: '300px'});
				this.AddSelectViewModeBtn(select);
				var select2 = $('select[name="SETTINGS_DEFAULT[ELEMENT_UID_SKU][]"]');
				this.AddSelectViewModeBtn(select2);
			}
			this.ToggleAdditionalSettings();
			
			$('#dataload input[type="checkbox"][data-confirm]').bind('change', function(){
				if(this.checked && !confirm(this.getAttribute('data-confirm')))
				{
					this.checked = false;
				}
			});
			
			$('#dataload input[type="checkbox"][data-confirm-disable]').bind('change', function(){
				if(!this.checked && !confirm(this.getAttribute('data-confirm-disable')))
				{
					this.checked = true;
				}
			});
		}
	},
	
	SetNewUid: function(oldSelect, newSelect)
	{
		var i, j, option, find,
			oldOptions = $('option', oldSelect),
			newOptions = $('option', newSelect);
		for(i=0; i<oldOptions.length; i++)
		{
			option = oldOptions[i];
			if(!option.selected) continue;
			find = false;
			j = 0;
			while(!find && j<newOptions.length)
			{
				if(option.value==newOptions[j].value)
				{
					newOptions[j].selected = true;
					find = true;
				}
				j++;
			}
			j = 0;
			while(!find && j<newOptions.length)
			{
				if(option.text==newOptions[j].text)
				{
					newOptions[j].selected = true;
					find = true;
				}
				j++;
			}
		}
		
	},
	
	AddSelectViewModeBtn: function(select)
	{
		if(select.nextAll('.esol-ix-select-view-mode').length == 0)
		{
			select.after('<a href="javascript:void(0)" onclick="EProfile.ChangeSelectViewMode(this)" class="esol-ix-select-view-mode" title="'+BX.message("ESOL_IX_SELECT_FAST_VIEW")+'"></a>');
			var minput = select.prevAll('input[type="hidden"][name*="SHOW_MODE_"]');
			if(minput.val()=='chosen') setTimeout(function(){select.nextAll('.esol-ix-select-view-mode').trigger('click');}, 200);
		}
	},
	
	ChangeSelectViewMode: function(a)
	{
		var select = $(a).parent().find('select:eq(0)');
		if(select.length > 0 && typeof select.chosen == 'function')
		{
			var minput = select.prevAll('input[type="hidden"][name*="SHOW_MODE_"]');
			if($(a).attr('mode')!='chosen')
			{
				select.chosen({search_contains: true, placeholder_text: BX.message("ESOL_IX_SELECT_NOT_CHOSEN")});
				$(a).attr('title', BX.message("ESOL_IX_SELECT_STANDARD_VIEW"));
				$(a).attr('mode', 'chosen');
				minput.val('chosen');
			}
			else
			{
				select.chosen('destroy');
				$(a).attr('title', BX.message("ESOL_IX_SELECT_FAST_VIEW"));
				$(a).attr('mode', '');
				minput.val('');
			}
		}
	},
	
	Choose: function(select)
	{
		/*if(select.value=='new')
		{
			$('#new_profile_name').css('display', '');
		}
		else
		{
			$('#new_profile_name').css('display', 'none');
		}*/
		$('form#dataload input[name="submit_btn"], form#dataload input[name="saveConfigButton"]').prop('disabled', true);
		var id = (typeof select == 'object' ? select.value : select);
		var query = window.location.search.replace(/PROFILE_ID=[^&]*&?/, '');
		if(query.length < 2) query = '?';
		if(query.length > 1 && query.substr(query.length-1)!='&') query += '&';
		query += 'PROFILE_ID=' + id;
		window.location.href = query;
	},
	
	Delete: function()
	{
		var obj = this;
		var select = $('select#PROFILE_ID');
		var option = select[0].options[select[0].selectedIndex];
		var id = option.value;
		$.post(window.location.href, {'MODE': 'AJAX', 'ID': id, 'ACTION': 'DELETE_PROFILE'}, function(data){
			obj.Choose('');
		});
	},
	
	Copy: function()
	{
		var obj = this;
		var select = $('select#PROFILE_ID');
		var option = select[0].options[select[0].selectedIndex];
		var id = option.value;
		$.post(window.location.href, {'MODE': 'AJAX', 'ID': id, 'ACTION': 'COPY_PROFILE'}, function(data){
			eval('var res = '+data+';');
			obj.Choose(res.id);
		});
	},
	
	ShowRename: function()
	{
		var select = $('select#PROFILE_ID');
		var option = select[0].options[select[0].selectedIndex];
		var name = option.innerHTML;
		var prefix = '['+option.value+'] ';
		if(name.indexOf(prefix)==0) name = name.substr(prefix.length);
		
		var tr = $('#new_profile_name');
		var input = $('input[type=text]', tr);
		input.val(name);
		if(!input.attr('init_btn'))
		{
			input.after('&nbsp;<input type="button" onclick="EProfile.Rename();" value="OK">');
			input.attr('init_btn', 1);
		}
		tr.css('display', '');
	},
	
	Rename: function()
	{
		var select = $('select#PROFILE_ID');
		var option = select[0].options[select[0].selectedIndex];
		var id = option.value;
		
		var tr = $('#new_profile_name');
		var input = $('input[type=text]', tr);
		var value = $.trim(input.val());
		if(value.length==0) return false;
		
		tr.css('display', 'none');
		option.innerHTML = '['+id+'] '+value;
		if(typeof select.chosen == 'function')
		{
			$('select#PROFILE_ID').trigger("chosen:updated");
		}
		
		$.post(window.location.href, {'MODE': 'AJAX', 'ID': id, 'NAME': value, 'ACTION': 'RENAME_PROFILE'}, function(data){});
	},
	
	ToggleAvailStatOption: function(available)
	{
		var statChb = $('#dataload input[type="checkbox"][name="SETTINGS_DEFAULT[STAT_SAVE]"]');
		if(statChb.length==0) return;
		if(available)
		{
			$('#dataload input[type="hidden"][name="SETTINGS_DEFAULT[STAT_SAVE]"]').remove();
			statChb.prop('disabled', false);
			if(statChb.attr('data-oldval'))
			{
				statChb.prop('checked', statChb.attr('data-oldval')=='1');
			}
		}
		else
		{
			statChb.attr('data-oldval', (statChb.prop('checked') ? '1' : '0'));
			statChb.prop('checked', true);
			statChb.prop('disabled', true);
			statChb.before('<input type="hidden" name="SETTINGS_DEFAULT[STAT_SAVE]" value="Y">');
		}
	},
	
	ShowCron: function()
	{
		var dialog = new BX.CAdminDialog({
			'title':BX.message("ESOL_IX_POPUP_CRON_TITLE"),
			'content_url':'/bitrix/admin/'+esolIXModuleFilePrefix+'_cron_settings.php?lang='+BX.message('LANGUAGE_ID'),
			'width':'800',
			'height':'400',
			'resizable':true});
			
		dialog.SetButtons([
			dialog.btnCancel/*,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})*/
		]);
		
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			$('input[type=checkbox]', this.DIV).each(function(){
				BX.adminFormTools.modifyCheckbox(this);
			});
			if(typeof $('select.esol-chosen-multi').chosen == 'function')
			{
				$('select.esol-chosen-multi').chosen({search_contains: true, placeholder_text: BX.message("ESOL_IX_CRON_CHOOSE_PROFILE")});
			}
		});
			
		dialog.Show();
	},
	
	SaveCron: function(btn)
	{
		var obj = this;
		var form = $(btn).closest('form');
		var action = form[0].getAttribute('action');
		$.post(action, form.serialize()+'&subaction='+btn.name, function(data){
			$('#esol-ix-cron-result').html(data);
			obj.UpdateCronRecords(action);
			if($('input[name="recordkey"]', form).val().length > 0)
			{
				$('input[name="recordkey"]', form).val('');
				var addBtn = $('input[name="add"]', form);
				addBtn.val(addBtn.attr('data-name-add'));
				$('select[name="PROFILE_ID[]"]', form).val('').trigger('chosen:updated').trigger('change');
				$('select[name="agent_period_type"]', form).val('daily').trigger('chosen:updated').trigger('change');
			}
		});
	},
	
	EditCronRecord: function(btn, key)
	{
		$('#esol-ix-cron-result').html('');
		btn = $(btn);
		var obj = this;
		var form = btn.closest('form');
		$('select[name="PROFILE_ID[]"]', form).val(btn.attr('data-profiles').split(',')).trigger('chosen:updated').trigger('change');
		$('select[name="agent_period_type"]', form).val('expert').trigger('chosen:updated').trigger('change');
		$('input[name="agent_period_expert"]', form).val(btn.attr('data-time'));
		$('input[name="agent_php_path"]', form).val(btn.attr('data-phppath'));
		$('input[name="recordkey"]', form).val(key);
		var addBtn = $('input[name="add"]', form);
		addBtn.val(addBtn.attr('data-name-change'));
		form.closest('.bx-core-adm-dialog-content').animate({scrollTop: 0}, 500);
	},

	DeleteFromCron: function(btn, key)
	{
		var obj = this;
		var form = $(btn).closest('form');
		var action = form[0].getAttribute('action');
		$.post(action, 'action=deleterecord&key='+encodeURIComponent(key), function(data){
			$('#esol-ix-cron-result').html('');
			obj.UpdateCronRecords(action);
		});
	},
	
	UpdateCronRecords: function(action)
	{
		$.get(action, function(data){
			$('#esol-ix-cron-records_wrap').html($(data).find('#esol-ix-cron-records_wrap').html());
		});
	},
	
	ShowMassUploader: function()
	{
		var dialog = new BX.CAdminDialog({
			'title':BX.message("ESOL_IX_TOOLS_IMG_LOADER_TITLE"),
			'content_url':'/bitrix/admin/'+esolIXModuleFilePrefix+'_mass_uploader.php?lang='+BX.message('LANGUAGE_ID'),
			'width':'900',
			'height':'450',
			'resizable':true});
			
		this.massUploaderDialog = dialog;
		this.MassUploaderSetButtons();
			
		dialog.Show();
	},
	
	MassUploaderSetButtons: function()
	{
		var dialog = this.massUploaderDialog;
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})
		]);
	},
	
	RemoveProccess: function(link, id)
	{
		var post = {
			'MODE': 'AJAX',
			'PROCCESS_PROFILE_ID': id,
			'ACTION': 'REMOVE_PROCESS_PROFILE'
		};
		
		$.ajax({
			type: "POST",
			url: window.location.href,
			data: post,
			success: function(data){
				var parent = $(link).closest('.kda-proccess-item');
				if(parent.parent().find('.kda-proccess-item').length <= 1)
				{
					parent.closest('.adm-info-message-wrap').hide();
				}
				parent.remove();
			}
		});
	},
	
	ContinueProccess: function(link, id)
	{
		var parent = $(link).closest('div');
		parent.append('<form method="post" action="" style="display: none;">'+
						'<input type="hidden" name="PROFILE_ID" value="'+id+'">'+
						'<input type="hidden" name="STEP" value="3">'+
						'<input type="hidden" name="PROCESS_CONTINUE" value="Y">'+
						'<input type="hidden" name="sessid" value="'+$('#sessid').val()+'">'+
					  '</form>');
		parent.find('form')[0].submit();
	},
	
	ToggleAdditionalSettings: function(link)
	{
		if(link) link = $(link);
		else link = $('.esol_ix_head_more');
		if(link.length==0) return;
		$(link).each(function(){
			var tr = $(this).closest('tr');
			var show = $(this).hasClass('show');
			while((tr = tr.next('tr:not(.heading)')) && tr.length > 0)
			{
				if(show) tr.hide();
				else tr.show();
			}
			if(show) $(this).removeClass('show');
			else $(this).addClass('show');
		});
	},
	
	RadioChb: function(chb1, chb2name, confirmMessage)
	{
		if(chb1.checked)
		{
			if(!confirmMessage || confirm(confirmMessage))
			{
				var form = $(chb1).closest('form');
				if(typeof chb2name=='object')
				{
					for(var i=0; i<chb2name.length; i++)
					{
						if(form[0][chb2name[i]])
						{
							form[0][chb2name[i]].checked = false;
							$(form[0][chb2name[i]]).trigger('change');
						}
					}
				}
				if(form[0][chb2name])
				{
					form[0][chb2name].checked = false;
					$(form[0][chb2name]).trigger('change');
				}
			}
			else
			{
				chb1.checked = false;
			}
		}
	},
	
	OpenMissignElementFields: function(link)
	{
		var form = $(link).closest('form');
		var iblockId = $('select[name="SETTINGS_DEFAULT[IBLOCK_ID]"]', form).val();
		var input = $(link).prev('input[type=hidden]');
		
		var dialogParams = {
			'title':BX.message(input.attr('id').indexOf('OFFER_')==0 ? "ESOL_IX_POPUP_MISSINGOFFER_FIELDS_TITLE" : "ESOL_IX_POPUP_MISSINGELEM_FIELDS_TITLE"),
			'content_url':'/bitrix/admin/'+esolIXModuleFilePrefix+'_missignelem_fields.php?lang='+BX.message('LANGUAGE_ID')+'&IBLOCK_ID='+iblockId+'&INPUT_ID='+input.attr('id'),
			'content_post': {OLDDEFAULTS: input.val()},
			'width':'800',
			'height':'400',
			'resizable':true
		};
		var dialog = new BX.CAdminDialog(dialogParams);
			
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})
		]);
			
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			$('input[type=checkbox]', this.DIV).each(function(){
				BX.adminFormTools.modifyCheckbox(this);
			});
			if(typeof $('select.esol-ix-chosen-multi').chosen == 'function') $('select.esol-ix-chosen-multi').chosen();
		});
			
		dialog.Show();
		
		return false;
	},
	
	OpenMissignElementFilter: function(link)
	{
		var obj = this;
		var form = $(link).closest('form');
		var iblockId = $('select[name="SETTINGS_DEFAULT[IBLOCK_ID]"]', form).val();
		
		var dialogParams = {
			'title':BX.message("ESOL_IX_POPUP_MISSINGELEM_FILTER_TITLE"),
			'content_url':'/bitrix/admin/'+esolIXModuleFilePrefix+'_missignelem_filter.php?lang='+BX.message('LANGUAGE_ID')+'&IBLOCK_ID='+iblockId+'&PROFILE_ID='+$('#PROFILE_ID').val(),
			'content_post': {OLDFILTER: $('#CELEMENT_MISSING_FILTER').val()},
			'width':'800',
			'height':'400',
			'resizable':true
		};
		var dialog = new BX.CAdminDialog(dialogParams);
			
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					$('#esol-ix-filter').find('tr[id*="_filter_row_"]:hidden').find('input,select,textarea').val('').trigger('change');
					$.post('/bitrix/admin/'+esolIXModuleFilePrefix+'_missignelem_filter.php?lang='+BX.message('LANGUAGE_ID'), $('#esol-ix-filter').serialize(), function(data){
						$('#CELEMENT_MISSING_FILTER').val($.trim(data));
						BX.WindowManager.Get().Close();
					});
				}
			})
		]);
		
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			if(document.getElementById('kda-ee-sheet-efilter'))
			{
				new EsolIXFilter('efilter');
			}
			
			setTimeout(function(){
				$('.find_form_inner select[name*="find_el_vtype_"]').bind('change', function(){
					var div = $(this.parentNode).next();
					if(this.value.length > 0 && this.value.indexOf('empty')!=-1) div.hide();
					else div.show();
				}).trigger('change');
			}, 500);
		});
			
		dialog.Show();
		
		return false;
	},
	
	ShowEmailForm: function()
	{
		var pid = $('#PROFILE_ID').val();
		var dialog = new BX.CAdminDialog({
			'title':BX.message("ESOL_IX_POPUP_SOURCE_EMAIL"),
			'content_url':'/bitrix/admin/'+esolIXModuleFilePrefix+'_source_email.php?lang='+BX.message('LANGUAGE_ID')+'&PROFILE_ID='+pid,
			'content_post': {EMAIL_SETTINGS: $('.esol-ix-file-choose input[name="SETTINGS_DEFAULT[EMAIL_DATA_FILE]"]').val()},
			'width':'900',
			'height':'450',
			'resizable':true});
			
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			
		});
		
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})
		]);
			
		dialog.Show();
	},
	
	CheckEmailConnectData: function(link)
	{
		var form = $(link).closest('form');
		var post = form.serialize()+'&action=checkconnect';
		$.ajax({
			type: "POST",
			url: form.attr('action'),
			data: post,
			success: function(data){
				eval('var res = '+data+';');
				if(res.result=='success') $('#connect_result').html('<div class="success">'+BX.message("ESOL_IX_SOURCE_EMAIL_SUCCESS")+'</div>');
				else $('#connect_result').html('<div class="fail">'+BX.message("ESOL_IX_SOURCE_EMAIL_FAIL")+'</div><div class="fail_note">'+BX.message("ESOL_IX_SOURCE_EMAIL_FAIL_NOTE")+'</div>');
				
				if(res.folders)
				{
					var select = $('select[name="EMAIL_SETTINGS[FOLDER]"]', form);
					var oldVal = select.val();
					$('option', select).remove();
					for(var i in res.folders)
					{
						var option = $('<option>'+res.folders[i]+'</option>');
						option.attr('value', i);
						select.append(option);
					}
					select.val(oldVal);
				}
			},
			error: function(){
				$('#connect_result').html('<div class="fail">'+BX.message("ESOL_IX_SOURCE_EMAIL_FAIL")+'</div>');
			},
			timeout: 5000
		});
	},
	
	ShowFileAuthForm: function()
	{
		var pid = $('#PROFILE_ID').val();
		var post = '';
		var json = $('.esol-ix-file-choose input[name="EXT_DATA_FILE"]').val();
		if(json && json.substr(0,1)=='{')
		{
			//eval('post = {AUTH_SETTINGS: '+json+'};');
			post = {AUTH_SETTINGS: json};
		}
		var dialog = new BX.CAdminDialog({
			'title':BX.message("ESOL_IX_POPUP_SOURCE_LINKAUTH"),
			'content_url':'/bitrix/admin/'+esolIXModuleFilePrefix+'_source_linkauth.php?lang='+BX.message('LANGUAGE_ID')+'&PROFILE_ID='+pid,
			'content_post': post,
			'width':'900',
			'height':'450',
			'resizable':true});
			
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			
		});
		
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})
		]);
			
		dialog.Show();
	},
	
	SetLinkAuthParams: function(jData)
	{
		if($('.esol-ix-file-choose input[name="EXT_DATA_FILE"]').length == 0)
		{
			$(".esol-ix-file-choose").prepend('<input type="hidden" name="EXT_DATA_FILE" value="">');
		}
		$('.esol-ix-file-choose input[name="EXT_DATA_FILE"]').val(JSON.stringify(jData));
		$('.esol-ix-file-choose input[name="SETTINGS_DEFAULT[EMAIL_DATA_FILE]"]').val('');
		BX.WindowManager.Get().Close();
	},
	
	LauthAddVar: function(link)
	{
		var tr = $(link).closest('tr').prev('tr.esol-ix-lauth-var');
		var newTr = tr.clone();
		newTr.find('input').val('');
		tr.after(newTr);
	},
	
	CheckLauthConnectData: function(link)
	{
		var form = $(link).closest('form');
		var post = form.serialize()+'&action=checkconnect';
		$.ajax({
			type: "POST",
			url: form.attr('action'),
			data: post,
			success: function(data){
				eval('var res = '+data+';');
				if(res.result=='success') $('#connect_result').html('<div class="success">'+BX.message("ESOL_IX_SOURCE_LAUTH_SUCCESS")+'</div>');
				else $('#connect_result').html('<div class="fail">'+BX.message("ESOL_IX_SOURCE_LAUTH_FAIL")+'</div>');
			},
			error: function(){
				$('#connect_result').html('<div class="fail">'+BX.message("ESOL_IX_SOURCE_LAUTH_FAIL")+'</div>');
			},
			timeout: 20000
		});
	},
	
	LauthLoadParams: function(link)
	{
		var form = $(link).closest('form');
		var post = form.serialize()+'&action=loadparams';
		$.ajax({
			type: "POST",
			url: form.attr('action'),
			data: post,
			success: function(data){
				if(data.length==0) return;
				eval('var res = '+data+';');
				if(typeof res!='object') return;
				
				var varInputs = $('input[name="vars[]"]', form);
				var emptyVals = true;
				for(var i=0; i<varInputs.length; i++)
				{
					if($.trim($(varInputs[i]).val()).length > 0) emptyVals = false;
				}
				if(emptyVals && typeof res.VARS=='object')
				{
					var countVars = varInputs.length;
					while(countVars < res.VARS.length)
					{
						$('td.esol-ix-lauth-addvar a', form).trigger('click');
						countVars++;
					}
					varInputs = $('input[name="vars[]"]', form);
					for(var i=0; i<varInputs.length; i++)
					{
						if(res.VARS[i]) $(varInputs[i]).val(res.VARS[i]);
					}
				}
				var postAuthInput = $('input[name="AUTH_SETTINGS[POSTPAGEAUTH]"]', form);
				if($.trim(postAuthInput.val()).length == 0 && res.LOC)
				{
					postAuthInput.val(res.LOC);
				}
			},
			timeout: 8000
		});
	},
	
	OpenCalcPriceForm: function(link)
	{
		var obj = this;
		var form = $(link).closest('form');
		var iblockId = $('select[name="SETTINGS_DEFAULT[IBLOCK_ID]"]', form).val();
		
		var dialogParams = {
			'title': BX.message("ESOL_IX_POPUP_CALULATE_PRICE_TITLE"),
			'content_url': '/bitrix/admin/'+esolIXModuleFilePrefix+'_price_calculating.php?lang='+BX.message('LANGUAGE_ID')+'&IBLOCK_ID='+iblockId,
			'width': '960',
			'height': '460',
			'resizable': true
		};
		var dialog = new BX.CAdminDialog(dialogParams);
			
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('JS_CORE_WINDOW_SAVE'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					this.disableUntilError();
					this.parentWindow.PostParameters();
					//this.parentWindow.Close();
				}
			})
		]);
			
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			$('input[type=checkbox]', this.DIV).each(function(){
				BX.adminFormTools.modifyCheckbox(this);
			});
			$('select.esol-ix-chosen-multi').chosen();
			var calcForm = $('#esol-ix-price-calculating-form');
			if(calcForm.length > 0) obj.GetUseCalcTypes(calcForm);
		});
			
		dialog.Show();
		
		return false;
	},
	
	AddNewCalcType: function(link)
	{
		var oldWrap = $(link).closest('.esol-ix-price-calculating-wrap');
		var form = oldWrap.closest('form');
		var wraps = $('.esol-ix-price-calculating-wrap', form);
		var useTypes = this.GetUseCalcTypes(form);
		
		var index = 1;
		while($('.esol-ix-price-calculating-wrap[data-index="'+index+'"]', form).length > 0)
		{
			index++;
		}
		var newWrap = oldWrap.clone(true);
		wraps.hide();
		newWrap.insertAfter(oldWrap);
		newWrap.attr('data-index', index);
		$('input,select', newWrap).each(function(){
			if(this.name=='price' || this.name=='quantity') return;
			this.name = this.name.replace(/^([^\[]*)_\d+($|\[)/, '$1$2');
			this.name = this.name.replace(/^([^\[]*)($|\[)/, '$1_'+index+'$2');
		});
		$('select[name$="[QUANTITY_CALC]"]', newWrap).closest('tr').remove();
		
		var priceInput = $('select[name$="[PRICE_TYPE]"]', newWrap);
		$('option', priceInput).remove();
		var newOptions = $('select[name="SHARE_PRICE_TYPE"] option', form);
		for(var i=0; i<newOptions.length; i++)
		{
			if(!useTypes[newOptions[i].value]) priceInput.append($(newOptions[i]).clone());
		}
		
		if($('option', priceInput).length <= 1) $('.esol-ix-price-calculating-ptype-new', form).hide();
		$('a.esol-ix-delete-row', newWrap).trigger('click');
		newWrap.show();
		this.GetUseCalcTypes(form);
	},
	
	GetUseCalcTypes: function(form)
	{
		var useTypes = {}, cntTypes = 0;
		var selTypes = $('select[name$="[PRICE_TYPE]"]', form);
		var newOptions = $('select[name="SHARE_PRICE_TYPE"] option', form);
		for(var i=0; i<selTypes.length; i++)
		{
			useTypes[selTypes[i].value] = $(selTypes[i]).closest('.esol-ix-price-calculating-wrap').attr('data-index');
			cntTypes++;
		}
		var value = '';
		for(var i=0; i<selTypes.length; i++)
		{
			value = selTypes[i].value;
			$('option', selTypes[i]).remove();
			for(var j=0; j<newOptions.length; j++)
			{
				if(newOptions[j].value==value || !useTypes[newOptions[j].value]) $(selTypes[i]).append($(newOptions[j]).clone());
			}
			$(selTypes[i]).val(value);
		}
		if(cntTypes > 1)
		{
			$('.esol-ix-price-calculating-ptypes', form).each(function(){
				var curType = $(this).closest('.esol-ix-price-calculating-wrap').find('select[name$="[PRICE_TYPE]"]').val();
				var links = '';
				for(var i in useTypes)
				{
					links += '<a '+(i==curType ? 'class="esol-ix-price-calculating-ptype-active"' : '')+' href="javascript:void(0)" onclick="EProfile.ChangeCalcType(this, \''+useTypes[i]+'\')">'+$('select[name="SHARE_PRICE_TYPE"] option[value="'+i+'"]', form).text()+'</a><a href="javascript:void(0)" class="esol-ix-price-calculating-ptype-delete" onclick="EProfile.RemoveCalcType(this, \''+useTypes[i]+'\')" title="'+BX.message('KDA_IE_OPTIONS_REMOVE')+'"></a> ';
				}
				$(this).html(links);
			});
		}
		else
		{
			$('.esol-ix-price-calculating-ptypes', form).html('');
		}
		return useTypes;
	},
	
	ChangeTypeTypeSelect: function(select)
	{
		$(select).closest('.esol-ix-price-calculating-wrap').find('.esol-ix-price-calculating-ptypes a.esol-ix-price-calculating-ptype-active').html(select.options.item(select.selectedIndex).text);
	},
	
	ChangeCalcType: function(link, index)
	{
		var form = $(link).closest('form');
		this.GetUseCalcTypes(form);
		$('.esol-ix-price-calculating-wrap', form).hide();
		$('.esol-ix-price-calculating-wrap[data-index="'+index+'"]', form).show();
	},
	
	RemoveCalcType: function(link, index)
	{
		var form = $(link).closest('form');
		$('.esol-ix-price-calculating-wrap[data-index="'+index+'"]', form).remove();
		if($('.esol-ix-price-calculating-wrap:visible', form).length==0)
		{
			$('.esol-ix-price-calculating-wrap:first', form).show();
		}
		this.GetUseCalcTypes(form);
		$('.esol-ix-price-calculating-ptype-new', form).show();
	},
	
	RelTablePriceRowAdd: function(link)
	{
		var tbl = $(link).prev('table');
		var index = 0;
		while($('tr[data-index="'+index+'"]', tbl).length > 0) index++;
		var tr = $('tr:last', tbl).clone();
		tr.attr('data-index', index);
		$('td:lt(2) input', tr).remove();
		var extraField = $('input[name*="[extra]"]', tr);
		extraField.prop('name', extraField.prop('name').replace(/\[\d+\]\[extra\]/, '['+index+'][extra]')).val('');
		$('a', tr).each(function(){this.innerHTML = this.getAttribute('data-default-text');});
		tr.appendTo(tbl);
	},
	
	RelTablePriceRowRemove: function(link)
	{
		var tr = $(link).closest('tr');
		var tbl = tr.closest('table');
		if($('tr', tbl).length > 2) tr.remove();
		else
		{
			$('input', tr).not('[name*="[extra]"]').remove();
			$('a', tr).each(function(){this.innerHTML = this.getAttribute('data-default-text');});
		}
	},
	
	RelTablePriceShowSelect: function(link, fname, hideLabel)
	{
		var iblockId = $(link).closest('table').attr('data-iblock-id');
		var parentDiv = $(link).closest('.esol-ix-select-mapping');
		var indexWrap = $(link).closest('.esol-ix-price-calculating-wrap').attr('data-index');
		var mapName = 'MAP'+(indexWrap && indexWrap > 0 ? '_'+indexWrap : '');
		var index = $(link).closest('tr').attr('data-index');
		var parentForm = $(link).closest('div.esol-ix-price-calculating-iblock');
		var selectObj = parentForm.find('select[name="'+fname+'"]').clone();
		selectObj.val($('input:first', parentDiv).val());
		parentDiv.append(selectObj);
		selectObj.bind('change', function(){
			var selectedOption = this.options.item(this.selectedIndex);
			var fieldName = '';
			var optgroup = $(selectedOption).closest('optgroup');
			if(optgroup.length > 0 && !hideLabel)
			{
				fieldName = optgroup.attr('label');
				if(fieldName.length > 0) fieldName += ' - ';
			}
			fieldName += selectedOption.text;
			link.innerHTML = fieldName;
			$('input[name^="'+mapName+'['+iblockId+']["]', parentDiv).remove();
			if(this.value.length > 0)
			{
				parentDiv.prepend('<input type="hidden" name="'+mapName+'['+iblockId+']['+index+']['+fname+']" value="">');
				$('input[name="'+mapName+'['+iblockId+']['+index+']['+fname+']"]', parentDiv).val(this.value);
			}
			if(typeof selectObj.chosen == 'function') selectObj.chosen('destroy');
			$(this).remove();
			$(link).show();
		});
		if(typeof selectObj.chosen == 'function') selectObj.chosen({search_contains: true});
		$(link).hide();
		
		if(selectObj.next('.chosen-container').length > 0)
		{
			$('body').one('click', function(e){
				e.stopPropagation();
				return false;
			});
			var chosenDiv = selectObj.next('.chosen-container')[0];
			$('a:eq(0)', chosenDiv).trigger('mousedown');
			
			var lastClassName = chosenDiv.className;
			var interval = setInterval( function() {   
				   var className = chosenDiv.className;
					if (className !== lastClassName) {
						selectObj.trigger('change');
						lastClassName = className;
						clearInterval(interval);
					}
				},50);
		}
	},
	
	SetNotUpdataFile: function(obj)
	{
		if($('#dataload #chb_not_update_file_import').length==0)
		{
			$('#dataload').prepend('<input type="hidden" name="CHB_NOT_UPDATE_FILE_IMPORT" value="Y" id="chb_not_update_file_import">');
			$('#bx-admin-prefix .bx-core-popup-menu-item-icon.adm-menu-upload-not-update').addClass('adm-menu-upload-not-update-active');
		}
		else
		{
			$('#dataload #chb_not_update_file_import').remove();
			$('#bx-admin-prefix .bx-core-popup-menu-item-icon.adm-menu-upload-not-update').removeClass('adm-menu-upload-not-update-active');
		}
	},
	
	ShowExtraModeChbs: function(link)
	{
		var wrap = $(link).closest('.esol-extra-mode-chbs-wrap');
		if(wrap.hasClass('esol-extra-mode-chbs-wrap-active'))
		{
			wrap.removeClass('esol-extra-mode-chbs-wrap-active');
			$('td>input[type="checkbox"]', wrap).prop('disabled', false);
			$('td>input[type="hidden"]', wrap).val('N');
		}
		else
		{
			wrap.addClass('esol-extra-mode-chbs-wrap-active');
			$('td>input[type="checkbox"]', wrap).prop('disabled', true);
			$('td>input[type="hidden"]', wrap).val('Y');
		}
	}
}

var EProfileList = {
	ShowOldParamsWindow: function(id)
	{
		var windowUrl = window.location.href;
		if(windowUrl.indexOf('?')==-1) windowUrl = windowUrl+'?lang='+BX.message('LANGUAGE_ID');
		windowUrl = windowUrl+'&pid='+id;
		var dialogParams = {
			'title':BX.message("ESOL_IX_POPUP_RESTORE_PROFILES_TITLE"),
			'content_url':windowUrl+'&action=showoldparams',
			'width':'600',
			'height':'200',
			'resizable':true
		};
		var dialog = new BX.CAdminDialog(dialogParams);
		dialog.SetButtons([
			dialog.btnClose,
			new BX.CWindowButton(
			{
				title: BX.message('ESOL_IX_POPUP_RESTORE_PROFILES_SAVE_BTN'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					var btn = this;
					btn.disable();
					
					$.ajax({
						url: windowUrl+'&action=saveoldparams',
						type: 'POST',
						data: (new FormData(document.getElementById('restore_profile_params'))),
						mimeType:"multipart/form-data",
						contentType: false,
						cache: false,
						processData:false,
						success: function(data, textStatus, jqXHR)
						{
							if(data && data.substr(0, 1)=='{' && data.substr(data.length-1)=='}')
							{
								eval('var result = '+data+';');
							}
							else
							{
								var result = false;
							}
							
							if(typeof result == 'object')
							{
								if(result.MESSAGE) alert(result.MESSAGE);
								if(result.TYPE=='SUCCESS')
								{
									dialog.Close()
								}
							}
							btn.enable();
						},
						error: function(data, textStatus, jqXHR)
						{
							btn.enable();
						}
					});
				}
			})
		]);
		
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			if(!document.getElementById('restore_point'))
			{
				//this.PARAMS.buttons[1].disable();
				$('#savebtn').remove();
			}
		});
		dialog.Show();
	},
	
	ShowRestoreWindow: function()
	{
		var windowUrl = '/bitrix/admin/'+esolIXModuleFilePrefix+'_restore_profiles.php?lang='+BX.message('LANGUAGE_ID');
		var dialogParams = {
			'title':BX.message("ESOL_IX_POPUP_RESTORE_PROFILES_TITLE"),
			'content_url':windowUrl,
			'width':'700',
			'height':'300',
			'resizable':true
		};
		var dialog = new BX.CAdminDialog(dialogParams);
		this.restoreDialog = dialog;
		this.RestoreDialogButtonsSet();		
			
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			$('input[type=checkbox]', this.DIV).each(function(){
				BX.adminFormTools.modifyCheckbox(this);
			});
			
			var newForm = $('#restore_profiles');
			$('input[type=file]', newForm).bind('change', function(){
				var listWrapRow = $('#esol_restore_profile_list_row');
				var listWrap = $('#esol_restore_profile_list');
				listWrapRow.hide();
				listWrap.html('');
				if(!this.value) return;
				$.ajax({
					url: windowUrl+'&action=getprofilesfromfile',
					type: 'POST',
					data: (new FormData(newForm[0])),
					mimeType:"multipart/form-data",
					contentType: false,
					cache: false,
					processData:false,
					success: function(data, textStatus, jqXHR)
					{
						eval('var res='+data);
						if((typeof res!='object') || res.TYPE!='SUCCESS' || (typeof res.PROFILES!='object')) return;
						listWrapRow.show();
						listWrap.append('<div><input type="checkbox" name="PARAMS[IDS][]" value="ALL" id="kda_ie_restoreprofile_all" checked> <label for="kda_ie_restoreprofile_all">'+BX.message('KDA_IE_POPUP_RESTORE_PROFILES_ALL')+'</label></div>');
						for(var i=0; i<res.PROFILES.length; i++)
						{
							listWrap.append('<div style="padding-left: 15px;"><input type="checkbox" name="PARAMS[IDS][]" value="'+res.PROFILES[i].ID+'" id="kda_ie_restoreprofile_'+res.PROFILES[i].ID+'" checked> <label for="kda_ie_restoreprofile_'+res.PROFILES[i].ID+'">'+res.PROFILES[i].NAME+'</label></div>');
						}
						$('#kda_ie_restoreprofile_all').bind('change', function(){
							var id = this.id;
							var checked = this.checked;
							$(this).closest('#esol_restore_profile_list').find('input[type="checkbox"]').each(function(){
								if(!this.id || this.id!=id) this.checked = checked;
							});
						});
					}
				});
			});
		});
			
		dialog.Show();
	},
	
	RestoreDialogButtonsSet: function(fireEvents)
	{
		var dialog = this.restoreDialog;
		dialog.SetButtons([
			dialog.btnCancel,
			new BX.CWindowButton(
			{
				title: BX.message('ESOL_IX_POPUP_RESTORE_PROFILES_SAVE_BTN'),
				id: 'savebtn',
				name: 'savebtn',
				className: BX.browser.IsIE() && BX.browser.IsDoctype() && !BX.browser.IsIE10() ? '' : 'adm-btn-save',
				action: function () {
					var btn = this;
					btn.disable();
					
					$.ajax({
						url: '/bitrix/admin/'+esolIXModuleFilePrefix+'_restore_profiles.php?lang='+BX.message('LANGUAGE_ID'),
						type: 'POST',
						data: (new FormData(document.getElementById('restore_profiles'))),
						mimeType:"multipart/form-data",
						contentType: false,
						cache: false,
						processData:false,
						success: function(data, textStatus, jqXHR)
						{
							if(data && data.substr(0, 1)=='{' && data.substr(data.length-1)=='}')
							{
								eval('var result = '+data+';');
							}
							else
							{
								var result = false;
							}
							
							if(typeof result == 'object')
							{
								if(result.MESSAGE) alert(result.MESSAGE);
								if(result.TYPE=='SUCCESS')
								{
									setTimeout(function(){
										window.location.href = window.location.href;
									}, 1000);
								}
							}
							btn.enable();
						},
						error: function(data, textStatus, jqXHR)
						{
							btn.enable();
						}
					});
				}
			})
		]);
		
		if(fireEvents)
		{
			BX.onCustomEvent(dialog, 'onWindowRegister');
		}
	},
}

var EImport = {
	params: {},

	Init: function(post, params)
	{
		BX.scrollToNode($('#resblock .adm-info-message')[0]);
		this.wait = BX.showWait();
		this.post = post;
		if(typeof params == 'object') this.params = params;
		this.SendData();
		this.pid = post.PROFILE_ID;
		this.idleCounter = 0;
		this.errorStatus = false;
		var obj = this;
		setTimeout(function(){obj.SetTimeout();}, 3000);
		obj.UpdateTime();
	},
	
	UpdateTime: function()
	{
		if($('#progressbar').hasClass('end') || !document.getElementById('execution_time')) return;
		var timeBegin = parseInt($('#execution_time').attr('data-time-begin'));
		if(!timeBegin)
		{
			timeBegin = (new Date()).getTime();
			$('#execution_time').attr('data-time-begin', timeBegin);
		}
		var days = 0, hours = 0, minutes = 0, seconds = 0;
		var seconds = Math.round(((new Date()).getTime() - timeBegin) / 1000);
		if(seconds >= 60){minutes = Math.floor(seconds/60); seconds = seconds%60;}
		if(minutes >= 60){hours = Math.floor(minutes/60); minutes = minutes%60;}
		if(hours >= 24){days = Math.floor(hours/60); hours = hours%60;}
		$('#execution_time').html((days > 0 ? days+' '+BX.message("ESOL_IX_TIME_DAYS")+' ' : '')+(hours > 0 ? hours+' '+BX.message("ESOL_IX_TIME_HOURS")+' ' : '')+(minutes > 0 ? minutes+' '+BX.message("ESOL_IX_TIME_MINUTES")+' ' : '')+seconds+' '+BX.message("ESOL_IX_TIME_SECONDS"));
		var obj = this;
		setTimeout(function(){obj.UpdateTime();}, 1000);
	},
	
	SetTimeout: function()
	{
		if($('#progressbar').hasClass('end')) return;
		var obj = this;
		this.timer = setTimeout(function(){obj.GetStatus();}, 2000);
	},
	
	GetStatus: function()
	{
		var obj = this;
		$.ajax({
			type: "GET",
			url: '/upload/tmp/'+esolIXModuleName+'/'+this.pid+'.txt?hash='+(new Date()).getTime(),
			success: function(data){
				var finish = false;
				if(data && data.substr(0, 1)=='{' && data.substr(data.length-1)=='}')
				{
					try {
						eval('var result = '+data+';');
					} catch (err) {
						var result = false;
					}
				}
				else
				{
					var result = false;
				}
				
				if(typeof result == 'object')
				{
					if(result.action!='finish')
					{
						obj.UpdateStatus(result);
					}
					else
					{
						obj.UpdateStatus(result, true);
						var finish = true;
					}
				}
				if(!finish) obj.SetTimeout();
			},
			error: function(){
				obj.SetTimeout();
			},
			timeout: 5000
		});
	},
	
	UpdateStatus: function(result, end)
	{
		if($('#progressbar').hasClass('end')) return;
		if(end && this.timer) clearTimeout(this.timer);
		
		if(typeof result == 'object')
		{
			result.total_file_line = parseInt(result.total_file_line);
			result.xmlCurrentRow = parseInt(result.xmlCurrentRow);
			if(result.total_file_line < result.xmlCurrentRow) result.total_file_line = result.xmlCurrentRow;
			if(!result.total_file_line) result.total_file_line = 1;
			
			if(end && (parseInt(result.total_read_line) < parseInt(result.total_file_line)))
			{
				result.total_read_line = result.total_file_line;
			}
			
			var paramTag;
			for(var i in result)
			{
				if(!i.match(/^[A-Za-z0-9_]+$/)) continue;
				paramTag = $('#esol_ix_result_wrap #'+i);
				if(paramTag.length==0) continue;
				paramTag.html(result[i]);
				if(result[i] > 0) paramTag.closest('span').addClass('esol-ix-result-item-full');
			}
			
			var span = $('#progressbar .presult span');

			if(result.curstep && span.attr('data-'+result.curstep))
			{
				span.html(span.attr('data-'+result.curstep));
			}
			if(end)
			{
				span.css('visibility', 'hidden');
				$('#progressbar .presult').removeClass('load');
				$('#progressbar').addClass('end');
			}
			var percent = Math.abs(Math.round((Math.max(result.total_read_line, result.xmlCurrentRow) / result.total_file_line) * 100));
			if(percent >= 100) percent = 99;
			if(end) percent = 100;
			$('#progressbar .presult b').html(percent+'%');
			$('#progressbar .pline').css('width', percent+'%');
			
			var statLink = document.getElementById('esol_ix_stat_profile_link');
			if(statLink && !statLink.getAttribute('data-init'))
			{
				statLink.setAttribute('data-init', 1);
				if(statLink && result.loggerExecId)
				{
					statLink.href = statLink.href.replace(/find_exec_id=(&|$)/, 'find_exec_id='+result.loggerExecId);
					statLink.parentNode.style.display = 'block';
				}
				var rollbackLink = document.getElementById('esol_ix_rollback_profile_link');
				if(rollbackLink && result.loggerExecId)
				{
					rollbackLink.href = rollbackLink.href.replace(/PROFILE_EXEC_ID=(&|$)/, 'PROFILE_EXEC_ID='+result.loggerExecId);
					rollbackLink.parentNode.style.display = 'block';
				}
			}
			
			if(this.tmpparams && this.tmpparams.total_read_line==result.total_read_line)
			{
				this.idleCounter++;
			}
			else
			{
				this.idleCounter = 0;
			}
			this.tmpparams = result;
		}
		
		/*if(this.idleCounter > 10 && this.errorStatus)
		{
			var obj = this;
			for(var i in obj.tmpparams)
			{
				obj.params[i] = obj.tmpparams[i];
			}
			obj.SendDataSecondary();
		}*/
	},
	
	SendData: function()
	{
		var post = this.post;
		post.ACTION = 'DO_IMPORT';
		post.stepparams = this.params;
		var obj = this;
		
		$.ajax({
			type: "POST",
			url: window.location.href,
			data: post,
			success: function(data){
				obj.errorStatus = false;
				obj.OnLoad(data);
			},
			error: function(data){
				if(data && data.responseText)
				{
					if(data.responseText.indexOf("[Error]")!=-1 || data.responseText.indexOf("[ErrorException]")!=-1 || data.responseText.indexOf("Query Error")!=-1)
					{
						$('#block_error').show();
						$('#res_error').append('<div>'+data.responseText+'</div>');
					}
				}
				obj.errorStatus = true;
				$('#block_error_import').show();
				var timeBlock = document.getElementById('esol_ix_auto_continue_time');
				if(timeBlock)
				{
					timeBlock.innerHTML = '';
					obj.TimeoutOnAutoConinue();
				}
			},
			timeout: (post.STEPS_TIME ? ((Math.min(3600, post.STEPS_TIME) + 120) * 1000) : 180000)
		});
	},
	
	TimeoutOnAutoConinue: function()
	{
		var obj = this;
		var timeBlock = document.getElementById('esol_ix_auto_continue_time');
		var time = timeBlock.innerHTML;
		if(time.length==0)
		{
			timeBlock.innerHTML = 30;
		}
		else
		{
			time = parseInt(time) - 1;
			timeBlock.innerHTML = time;
			if(time < 1)
			{
				//$('#kda_ie_continue_link').trigger('click');

				$.ajax({
					type: "POST",
					url: window.location.href,
					data: {'MODE': 'AJAX', 'PROCCESS_PROFILE_ID': obj.pid, 'ACTION': 'GET_PROCESS_PARAMS'},
					success: function(data){
						if(data && data.substr(0, 1)=='{' && data.substr(data.length-1)=='}')
						{
							try {
								eval('var params = '+data+';');
							} catch (err) {
								var params = false;
							}
							if(typeof params == 'object')
							{
								obj.params = params;
							}
						}
						$('#block_error_import').hide();
						obj.errorStatus = false;
						obj.SendDataSecondary();
					},
					error: function(){
						timeBlock.innerHTML = '';
						obj.TimeoutOnAutoConinue();
					}
				});
				return;
			}
		}
		setTimeout(function(){obj.TimeoutOnAutoConinue();}, 1000);
	},
	
	SendDataSecondary: function()
	{
		var obj = this;
		if(this.post.STEPS_DELAY)
		{
			setTimeout(function(){
				obj.SendData();
			}, parseInt(this.post.STEPS_DELAY) * 1000);
		}
		else
		{
			obj.SendData();
		}
	},
	
	OnLoad: function(data)
	{
		data = $.trim(data);
		var returnLabel = '<!--module_return_data-->';
		if(data.indexOf(returnLabel)!=-1)
		{
			data = $.trim(data.substr(data.indexOf(returnLabel) + returnLabel.length));
			var returnLabel2 = returnLabel.replace('<!--', '<!--/');
			if(data.indexOf(returnLabel2)!=-1)
			{
				data = $.trim(data.substr(0, data.indexOf(returnLabel2)));
			}
		}
		if(data.indexOf('{')!=0)
		{
			if(data.indexOf("'bitrix_sessid':'")!=-1)
			{
				var sessid = data.substr(data.indexOf("'bitrix_sessid':'") + 17);
				sessid = sessid.substr(0, sessid.indexOf("'"));
				if(sessid.length > 0) this.post.sessid = sessid;
			}
			else if(data.indexOf(".settings.php")!=-1 || data.indexOf("[Error]")!=-1 || data.indexOf("MySQL Query Error")!=-1)
			{
				$('#block_error').show();
				$('#res_error').append('<div>'+data+'</div>');
			}
			var obj = this;
			setTimeout(function(){obj.SendDataSecondary();}, 5000);
			return true;
		}
		try {
			eval('var result = '+data+';');
		} catch (err) {
			var result = false;
		}
		if(typeof result == 'object')
		{
			if(result.sessid)
			{
				$('#sessid').val(result.sessid);
				this.post.sessid = result.sessid;
			}
			
			if(typeof result.errors == 'object' && result.errors.length > 0)
			{
				$('#block_error').show();
				for(var i=0; i<result.errors.length; i++)
				{
					$('#res_error').append('<div>'+result.errors[i]+'</div>');
				}
			}
			
			if(result.action=='continue')
			{
				this.UpdateStatus(result.params);
				this.params = result.params;
				this.SendDataSecondary();
				return true;
			}
		}
		else
		{
			this.SendDataSecondary();
			return true;
		}

		this.UpdateStatus(result.params, true);
		BX.closeWait(null, this.wait);
		/*$('#res_continue').hide();
		$('#res_finish').show();*/
	
		return false;
	}
}

var ESettings = {
	AddValue: function(link)
	{
		var div = $(link).prev('div').clone(true);
		$('input, select', div).val('').show();
		$(link).before(div);
	},
	
	OnValChange: function(select)
	{
		var input = $(select).next('input');
		var val = $(select).val();
		if(val.substr(0, 1) == '{')
		{
			input.hide();
			input.val(val);
		}
		else
		{
			if(input.val().substr(0, 1) == '{') input.val('');
			input.show();
		}
	},
	
	AddMargin: function(link)
	{
		var div = $(link).closest('td').find('.esol-ix-settings-margin:eq(0)');
		if(!div.is(':visible'))
		{
			div.show();
		}
		else
		{
			var div2 = div.clone(true);
			$('input', div2).val('');
			$('select', div2).prop('selectedIndex', 0);
			$(link).before(div2);
		}
	},
	
	RemoveMargin: function(link)
	{
		var divs = $(link).closest('td').find('.esol-ix-settings-margin');
		if(divs.length > 1)
		{
			$(link).closest('.esol-ix-settings-margin').remove();
		}
		else
		{
			$('input', divs).val('');
			$('select', divs).prop('selectedIndex', 0);
			divs.hide();
		}
	},
	
	ShowMarginTemplateBlock: function(link)
	{
		$('#margin_templates_load').hide();
		var div = $('#margin_templates');
		div.toggle();
	},
	
	ShowMarginTemplateBlockLoad: function(link, action)
	{
		$('#margin_templates').hide();
		var div = $('#margin_templates_load');
		if(action == 'hide') div.hide();
		else div.toggle();
	},
	
	SaveMarginTemplate: function(input, message)
	{
		var div = $(input).closest('div');
		var tid = $('select[name=MARGIN_TEMPLATE_ID]', div).val();
		var tname = $('input[name=MARGIN_TEMPLATE_NAME]', div).val();
		if(tid.length==0 && tname.length==0) return false;
		
		var wm = BX.WindowManager.Get();
		var url = wm.PARAMS.content_url;
		var params = wm.GetParameters().replace(/(^|&)action=[^&]*($|&)/, '&').replace(/^&+/, '').replace(/&+$/, '')
		params += '&action=save_margin_template&template_id='+tid+'&template_name='+tname;
		$.post(url, params, function(data){
			var jData = $(data);
			$('#margin_templates').replaceWith(jData.find('#margin_templates'));
			$('#margin_templates_load').replaceWith(jData.find('#margin_templates_load'));
			alert(message);
		});
		
		return false;
	},
	
	LoadMarginTemplate: function(input)
	{
		var div = $(input).closest('div');
		var tid = $('select[name=MARGIN_TEMPLATE_ID]', div).val();
		if(tid.length==0) return false;
		
		var wm = BX.WindowManager.Get();
		var url = wm.PARAMS.content_url;
		var params = wm.GetParameters().replace(/(^|&)action=[^&]*($|&)/, '&').replace(/^&+/, '').replace(/&+$/, '')
		params += '&action=load_margin_template&template_id='+tid;
		var obj = this;
		$.post(url, params, function(data){
			var jData = $(data);
			$('#settings_margins').replaceWith(jData.find('#settings_margins'));
			obj.ShowMarginTemplateBlockLoad('hide');
		});
		
		return false;
	},
	
	RemoveMarginTemplate: function(input, message)
	{
		var div = $(input).closest('div');
		var tid = $('select[name=MARGIN_TEMPLATE_ID]', div).val();
		if(tid.length==0) return false;
		
		var wm = BX.WindowManager.Get();
		var url = wm.PARAMS.content_url;
		var params = wm.GetParameters().replace(/(^|&)action=[^&]*($|&)/, '&').replace(/^&+/, '').replace(/&+$/, '')
		params += '&action=delete_margin_template&template_id='+tid;
		$.post(url, params, function(data){
			var jData = $(data);
			$('#margin_templates').replaceWith(jData.find('#margin_templates'));
			$('#margin_templates_load').replaceWith(jData.find('#margin_templates_load'));
			alert(message);
		});
		
		return false;
	},
	
	BindConversionEvents: function()
	{
		$('.esol-ix-settings-conversion').each(function(){
			var parent = this;
			$('select.field_cell', parent).bind('change', function(){
				if(this.value=='ELSE' || this.value=='LOADED' || this.value=='DUPLICATE')
				{
					$('select.field_when', parent).hide();
					$('input.field_from', parent).hide();
				}
				else
				{
					$('select.field_when', parent).show();
					$('input.field_from', parent).show();
				}
			}).trigger('change');
		});
	},
	
	AddConversion: function(link, event)
	{
		var prevDiv = $(link).prev('.esol-ix-settings-conversion');
		if(!prevDiv.is(':visible'))
		{
			prevDiv.show();
		}
		else
		{
			var div = prevDiv.clone();
			div.removeAttr('data-events-init');
			$('input', div).attr('id', '');
			if(typeof event == 'object' && (event.ctrlKey || event.shiftKey))
			{
				$('select, input', prevDiv).each(function(){
					$(this.tagName.toLowerCase()+'[name="'+this.name+'"]', div).val(this.value);
				});
			}
			else
			{
				$('input', div).not('.choose_val').val('');
				$('select', div).prop('selectedIndex', 0);
			}
			$(link).before(div);
		}
		ESettings.BindConversionEvents();
		return false;
	},
	
	RemoveConversion: function(link)
	{
		var div = $(link).closest('.esol-ix-settings-conversion');
		if($(link).closest('td').find('.esol-ix-settings-conversion').length > 1)
		{
			div.remove();
		}
		else
		{
			$('input', div).not('.choose_val').val('');
			$('select', div).prop('selectedIndex', 0);
			div.hide();
		}
	},
	
	ConversionUp: function(link)
	{
		var div = $(link).closest('.esol-ix-settings-conversion');
		var prev = div.prev('.esol-ix-settings-conversion');
		if(prev.length > 0)
		{
			div.insertBefore(prev);
		}
	},
	
	ConversionDown: function(link)
	{
		var div = $(link).closest('.esol-ix-settings-conversion');
		var next = div.next('.esol-ix-settings-conversion');
		if(next.length > 0)
		{
			div.insertAfter(next);
		}
	},
	
	ShowChooseVal: function(btn)
	{
		var field = $(btn).prev('input')[0];
		this.focusField = field;
		var arLines = [];
		var id = btn.id;
		if(!id)
		{
			while((id = 'kda_btn_'+(Math.floor(Math.random()*100000000000)+1)) && document.getElementById(id)){}
			btn.id = id;
		}
		arLines.push({'HTML':'<input type="text" placeholder="'+BX.message("ESOL_IX_INPUT_FAST_SEARCH")+'" id="'+id+'_search" class="esol_btn_fast_search">'});
		
		if(admKDASettingMessages.CURRENT_VALUE)
		{
			arLines.push({'TEXT':admKDASettingMessages.CURRENT_VALUE,'TITLE':'#VAL# - '+admKDASettingMessages.CURRENT_VALUE,'ONCLICK':'ESettings.SetUrlVar(\'#VAL#\')'});
		}
		
		if(admKDASettingMessages.AVAILABLE_TAGS)
		{
			var tags = admKDASettingMessages.AVAILABLE_TAGS;
			for(var i in tags)
			{
				arLines.push({'TEXT':tags[i],'TITLE':'{'+i+'}','ONCLICK':'ESettings.SetUrlVar(\'{'+i.replace(/'/g, "\\'")+'}\')'});
			}
		}
		if(admKDASettingMessages.VALUES && typeof admKDASettingMessages.VALUES=='object')
		{
			var values = admKDASettingMessages.VALUES;
			var menuValsItems = [];
			for(var i=0; i<values.length; i++)
			{
				menuValsItems.push({
					TEXT: values[i],
					TITLE: values[i],
					ONCLICK: 'ESettings.SetUrlVar(this)'
				});
			}
			arLines.push({'TEXT':BX.message("ESOL_IX_PROP_VALUES"),MENU: menuValsItems});
		}
		if(admKDASettingMessages.RATES && typeof admKDASettingMessages.RATES=='object')
		{
			var rates = admKDASettingMessages.RATES;
			var menuValsItems = [];
			for(var i in rates)
			{
				menuValsItems.push({
					TEXT: rates[i],
					TITLE: rates[i],
					ONCLICK: 'ESettings.SetUrlVar(\'#'+i+'#\')'
				});
			}
			arLines.push({'TEXT':BX.message("ESOL_IX_CURRENCY_RATES"),MENU: menuValsItems});
		}
		else
		{
			for(var key in admKDASettingMessages)
			{
				if(key.indexOf('RATE_')==0)
				{
					var currency = key.substr(5);
					arLines.push({'TEXT':admKDASettingMessages[key],'TITLE':'#'+currency+'# - '+admKDASettingMessages[key],'ONCLICK':'ESettings.SetUrlVar(\'#'+currency+'#\')'});
				}
			}
		}
		arLines.push({'TEXT':admKDASettingMessages.HASH_FILEDS,'TITLE':'#HASH# - '+admKDASettingMessages.HASH_FILEDS,'ONCLICK':'ESettings.SetUrlVar(\'#HASH#\')'});
		arLines.push({'TEXT':admKDASettingMessages.IFILELINK,'TITLE':'#FILELINK# - '+admKDASettingMessages.IFILELINK,'ONCLICK':'ESettings.SetUrlVar(\'#FILELINK#\')'});
		arLines.push({'TEXT':admKDASettingMessages.IFILEDATE,'TITLE':'#FILEDATE# - '+admKDASettingMessages.IFILEDATE,'ONCLICK':'ESettings.SetUrlVar(\'#FILEDATE#\')'});
		arLines.push({'TEXT':admKDASettingMessages.IDATETIME,'TITLE':'#DATETIME# - '+admKDASettingMessages.IDATETIME,'ONCLICK':'ESettings.SetUrlVar(\'#DATETIME#\')'});
		
		BX.adminShowMenu(btn, arLines, '');
		if(!$('#'+id+'_search').attr('data-init'))
		{
			$('#'+id+'_search').unbind('click').bind('click', function(e){
				e.stopPropagation();
				return false;
			}).unbind('keyup change').bind('keyup change', function(e){
				var val = $.trim($(this).val()).toLowerCase();
				$(this).closest('.bx-core-popup-menu').find('.bx-core-popup-menu-item:gt(0)').each(function(){
					if(val.length==0) $(this).show();
					else 
					{
						var textobj = $('.bx-core-popup-menu-item-text', this);
						var stext = textobj.html().toLowerCase();
						if(textobj.length==0 || stext.indexOf(val)!=-1 || stext.indexOf('<b>')!=-1) $(this).show();
						else $(this).hide();
					}
				});
			}).attr('data-init', '1');
		}
		//$('#'+id+'_search').blur().focus();
	},
	
	ShowExtraChooseVal: function(btn)
	{
		var field = $(btn).prev('input')[0];
		this.focusField = field;
		var arLines = [];
		var id = btn.id;
		if(!id)
		{
			while((id = 'kda_btn_'+(Math.floor(Math.random()*100000000000)+1)) && document.getElementById(id)){}
			btn.id = id;
		}
		arLines.push({'HTML':'<input type="text" placeholder="'+BX.message("ESOL_IX_INPUT_FAST_SEARCH")+'" id="'+id+'_search" class="esol_btn_fast_search">'});
		for(var k in admKDASettingMessages.EXTRAFIELDS)
		{
			arLines.push({'TEXT':'<b>'+admKDASettingMessages.EXTRAFIELDS[k].TITLE+'</b>', 'HTML':'<b>'+admKDASettingMessages.EXTRAFIELDS[k].TITLE+'</b>', 'TITLE':'','ONCLICK':'javascript:void(0)'});
			for(var k2 in admKDASettingMessages.EXTRAFIELDS[k].FIELDS)
			{
				arLines.push({'TEXT':admKDASettingMessages.EXTRAFIELDS[k].FIELDS[k2], 'TITLE':'#'+k2+'# - '+admKDASettingMessages.EXTRAFIELDS[k].FIELDS[k2],'ONCLICK':'ESettings.SetUrlVar(\'#'+k2+'#\')'});
			}
		}
		if(admKDASettingMessages.AVAILABLE_TAGS)
		{
			arLines.push({'TEXT':'<b>'+BX.message("ESOL_IX_VALS_FROM_FILE")+'</b>', 'HTML':'<b>'+BX.message("ESOL_IX_VALS_FROM_FILE")+'</b>', 'TITLE':'','ONCLICK':'javascript:void(0)'});
			if(admKDASettingMessages.CURRENT_VALUE)
			{
				arLines.push({'TEXT':admKDASettingMessages.CURRENT_VALUE,'TITLE':'#VAL# - '+admKDASettingMessages.CURRENT_VALUE,'ONCLICK':'ESettings.SetUrlVar(\'#VAL#\')'});
			}
			var tags = admKDASettingMessages.AVAILABLE_TAGS;
			for(var i in tags)
			{
				arLines.push({'TEXT':tags[i],'TITLE':'{'+i+'}','ONCLICK':'ESettings.SetUrlVar(\'{'+i+'}\')'});
			}
		}
		BX.adminShowMenu(btn, arLines, '');
		if(!$('#'+id+'_search').attr('data-init'))
		{
			$('#'+id+'_search').unbind('click').bind('click', function(e){
				e.stopPropagation();
				return false;
			}).unbind('keyup change').bind('keyup change', function(e){
				var val = $.trim($(this).val()).toLowerCase();
				$(this).closest('.bx-core-popup-menu').find('.bx-core-popup-menu-item:gt(0)').each(function(){
					if(val.length==0) $(this).show();
					else 
					{
						var textobj = $('.bx-core-popup-menu-item-text', this);
						var stext = textobj.html().toLowerCase();
						if(textobj.length==0 || stext.indexOf(val)!=-1 || stext.indexOf('<b>')!=-1) $(this).show();
						else $(this).hide();
					}
				});
			}).attr('data-init', '1');
		}
		//$('#'+id+'_search').blur().focus();
	},
	
	AddProfileDescription: function(link)
	{
		var tr = $(link).closest('tr');
		tr.hide();
		tr.next('tr').show();
	},
	
	ShowPHPExpression: function(link)
	{
		var div = $(link).next('.esol-ix-settings-phpexpression');
		if(div.is(':visible')) div.hide();
		else div.show();
	},
	
	SetUrlVar: function(id)
	{
		if(typeof id=='object') id = id.title;
		var obj_ta = this.focusField;
		//IE
		if (document.selection)
		{
			obj_ta.focus();
			var sel = document.selection.createRange();
			sel.text = id;
			//var range = obj_ta.createTextRange();
			//range.move('character', caretPos);
			//range.select();
		}
		//FF
		else if (obj_ta.selectionStart || obj_ta.selectionStart == '0')
		{
			var startPos = obj_ta.selectionStart;
			var endPos = obj_ta.selectionEnd;
			var caretPos = startPos + id.length;
			obj_ta.value = obj_ta.value.substring(0, startPos) + id + obj_ta.value.substring(endPos, obj_ta.value.length);
			obj_ta.setSelectionRange(caretPos, caretPos);
			obj_ta.focus();
		}
		else
		{
			obj_ta.value += id;
			obj_ta.focus();
		}

		BX.fireEvent(obj_ta, 'change');
		obj_ta.focus();
		$('.esol_btn_fast_search').val('').trigger('change');
	},
	
	AddDefaultProp: function(select)
	{
		if(!select.value) return;
		var parent = $(select).closest('tr');
		var inputName = 'DEFAULTS['+select.value+']';
		if($(parent).closest('table').find('input[name="'+inputName+'"]').length > 0) return;
		var tmpl = parent.prev('tr.esol-ix-list-settings-defaults');
		var tr = tmpl.clone();
		tr.css('display', '');
		$('.adm-detail-content-cell-l', tr).html(select.options[select.selectedIndex].innerHTML+':');
		$('input[type=text]', tr).attr('name', inputName);
		tr.insertBefore(tmpl);
		$(select).val('').trigger('chosen:updated');
	},
	
	RemoveDefaultProp: function(link)
	{
		$(link).closest('tr').remove();
	},
	
	RemoveLoadingRange: function(link)
	{
		$(link).closest('div').remove();
	},
	
	AddNewLoadingRange: function(link)
	{
		var div = $(link).prev('div');
		var newRange = div.clone().insertBefore(div);
		newRange.show();
	},
	
	ExportConvCSV: function(link)
	{
		var wm = BX.WindowManager.Get();
		var url = wm.PARAMS.content_url;
		var formId = 'esol-ix-tmpcsvform';
		var form = $(link).closest('form');
		var inputs = $('input[name*="[CONVERSION]"], select[name*="[CONVERSION]"], textarea[name*="[CONVERSION]"], input[name*="[EXTRA_CONVERSION]"], select[name*="[EXTRA_CONVERSION]"], textarea[name*="[EXTRA_CONVERSION]"]', form);
		var newForm = $('<form method="post" target="_blank" id="'+formId+'" style="display: none;"></form>');
		newForm.attr('action', url);
		var tmpInput;
		for(var i=0; i<inputs.length; i++)
		{
			tmpInput = $('<input type="hidden">');
			tmpInput.attr('name', inputs[i].name.replace(/^.*\[(CONVERSION|EXTRA_CONVERSION)\]/, '$1'));
			tmpInput.val($(inputs[i]).val());
			newForm.append(tmpInput);
		}
		newForm.append('<input type="hidden" name="action" value="export_conv_csv">');
		$('#'+formId).remove();
		form.after(newForm);
		newForm.trigger('submit');
		
		return false;
	},
	
	ImportConvCSV: function(link)
	{
		var wm = BX.WindowManager.Get();
		var url = wm.PARAMS.content_url;
		var formId = 'esol-ix-tmpcsvform-import';
		var form = $(link).closest('form');
		var newForm = $('<form method="post" id="'+formId+'" style="display: none;"><input type="hidden" name="POSTSTRUCT" value=""><input type="hidden" name="POSTXPATH" value=""><input type="file" name="import_file"><input type="hidden" name="action" value="import_conv_csv"></form>');
		newForm.attr('action', url);
		$('input[name="POSTSTRUCT"]', newForm).val($('input[name="POSTSTRUCT"]', form).val());
		$('input[name="POSTXPATH"]', newForm).val($('input[name="POSTXPATH"]', form).val());
		$('#'+formId).remove();
		form.after(newForm);
		$('input[type=file]', newForm).bind('change', function(){
			if(!this.value) return;
			$.ajax({
				url: newForm.attr('action'),
				type: 'POST',
				data: (new FormData(newForm[0])),
				mimeType:"multipart/form-data",
				contentType: false,
				cache: false,
				processData:false,
				success: function(data, textStatus, jqXHR)
				{
					var objData = $(data);
					var w0 = objData.find('#esol-ix-conv-wrap0');
					var w1 = objData.find('#esol-ix-conv-wrap1');
					if(w0.length > 0) $('#esol-ix-conv-wrap0').replaceWith(w0);
					if(w1.length > 0) $('#esol-ix-conv-wrap1').replaceWith(w1);
					ESettings.BindConversionEvents();
				}
			});
		}).trigger('click');
	},
	
	ShowValuesFromFile: function(link, prefileId, xpath, parentXpath)
	{
		var wait = BX.showWait();
		//$.post(window.location.href, {'MODE': 'AJAX', 'ACTION': 'GET_XPATH_VALUES', 'XPATH': xpath, 'PARENT_XPATH': parentXpath, 'PROFILE_ID': prefileId}, function(data){
		$.post(window.location.href, $(link).closest('form').serialize()+'&MODE=AJAX&ACTION=GET_XPATH_VALUES&XPATH='+xpath+'&PARENT_XPATH='+parentXpath+'&PROFILE_ID='+prefileId, function(data){
			eval('var res = '+data+';');
			if(typeof res == 'object')
			{
				var vals = '';
				for(var i=0; i<res.length; i++)
				{
					vals += res[i].replace("\n", " ")+"\r\n";
				}
				var td = $(link).closest('td');
				if($('textarea', td).length > 0) $('textarea', td).val(vals);
				else
				{
					td.prepend('<textarea readonly>'+vals+'</textarea>');
					$('div', td).show();
					$(link).remove();
				}
			}
			BX.closeWait(null, wait);
		});
	},
	
	ShowPropertyMap: function(btn)
	{
		var form = $(btn).closest('form');
		var input = $('input[name="MAP[CHECK_SECTIONS]"]', form);
		input.val(input.val()=='Y' ? 'N' : 'Y');
		data = form.serialize();
		data = data.replace('action=save', 'action=reload');
		var action = form.attr('action');
		var wait = BX.showWait();
		$.post(action, data, function(htmlData){
			var newForm = $('<div>'+htmlData+'</div>').find('#group_property_form');
			if(newForm.length==1)
			{
				$('#group_property_form').replaceWith(newForm);
				$('#group_property_form input[type=checkbox]').each(function(){
					BX.adminFormTools.modifyCheckbox(this);
				});
			}
			BX.closeWait(null, wait);
		})
	},
	
	JuxtaposeProps: function(btn)
	{
		var parentForm = $(btn).closest('form');
		var selectObj = parentForm.find('select[name="section"]');
		if(selectObj.length==0) return;
		selectObj = selectObj[0];
		var optCount = selectObj.options.length;
		var opt, arOpts = {};
		for(var i=0; i<optCount; i++)
		{
			opt = selectObj.options.item(i);
			if(opt.value.substr(0, 7)!='IP_PROP') continue;
			arOpts[opt.text.replace(/\s+\[[^\]]*\]\s*$/, '')] = opt;
		}
		var index = 0;
		while(document.getElementById('esol_mapping_'+index)) index++;
		$('#esol_propgroup_tbl .esol-ix-select-mapping').each(function(){
			var parentDiv = $(this);
			if($('input', parentDiv).length > 0) return;
			var fName = parentDiv.closest('tr').find('td:first').html();
			if(arOpts[fName])
			{
				var fieldName = '';
				var optgroup = $(arOpts[fName]).closest('optgroup');
				if(optgroup.length > 0)
				{
					fieldName = optgroup.attr('label');
					if(fieldName.length > 0) fieldName += ' - ';
				}
				fieldName += arOpts[fName].text;
				$('a:first', parentDiv).html(fieldName);
				parentDiv.prepend('<input id="esol_mapping_'+index+'" type="hidden" name="MAP[MAP]['+index+'][XML_ID]" value=""><input type="hidden" name="MAP[MAP]['+index+'][ID]" value="">');
				$('input[name="MAP[MAP]['+index+'][XML_ID]"]', parentDiv).val(parentDiv.attr('data-xml-id'));
				$('input[name="MAP[MAP]['+index+'][ID]"]', parentDiv).val(arOpts[fName].value);
				parentDiv.addClass('esol-ix-select-mapping-full');
				$('a.esol-ix-select-mapping-settings', parentDiv).prop('id', 'field_settings_0'+index).addClass('inactive').prepend('<input type="hidden" name="MAP[MAP]['+index+'][EXTRA]" value="">');
				index++;
			}
		});
	},
	
	JuxtaposeSections: function(btn)
	{
		var parentForm = $(btn).closest('form');
		var selectObj = parentForm.find('select[name="section"]');
		if(selectObj.length==0) return;
		selectObj = selectObj[0];
		var optCount = selectObj.options.length;
		var opt, arOpts = {};
		for(var i=0; i<optCount; i++)
		{
			opt = selectObj.options.item(i);
			if(!opt.value.match(/^\d+$/)) continue;
			arOpts[opt.text.replace(/\s+\[[^\]]*\]\s*(\/|$)/g, '$1').replace(/\s*\/\s*/g, '/').replace(/(^\s*|\s*$)/, '')] = opt;
		}
		
		var index = 0;
		while(document.getElementById('esol_mapping_'+index)) index++;
		$('#esol_propgroup_tbl .esol-ix-select-mapping').each(function(){
			var parentDiv = $(this);
			if($('input', parentDiv).length > 0) return;
			var fName = parentDiv.closest('tr').find('td:first').html();
			fName = fName.replace(/\s+\[[^\]]*\]\s*(\/|$)/g, '$1').replace(/\s*\/\s*/g, '/').replace(/(^\s*|\s*$)/, '')
			if(arOpts[fName])
			{
				var fieldName = arOpts[fName].text;
				$('a:first', parentDiv).html(fieldName);
				parentDiv.prepend('<input id="esol_mapping_'+index+'" type="hidden" name="MAP[MAP]['+index+'][XML_ID]" value=""><input type="hidden" name="MAP[MAP]['+index+'][ID]" value="">');
				$('input[name="MAP[MAP]['+index+'][XML_ID]"]', parentDiv).val(parentDiv.attr('data-xml-id'));
				$('input[name="MAP[MAP]['+index+'][ID]"]', parentDiv).val(arOpts[fName].value);
				parentDiv.addClass('esol-ix-select-mapping-full');
				$('a.esol-ix-select-mapping-settings', parentDiv).prop('id', 'field_settings_0'+index).addClass('inactive').prepend('<input type="hidden" name="MAP[MAP]['+index+'][EXTRA]" value="">');
				index++;
			}
		});
	},
	
	ShowSelectMapping: function(link, showGroup)
	{
		var parentDiv = $(link).closest('.esol-ix-select-mapping');
		var parentWrap = parentDiv.closest('.esol-ix-select-mapping-wrap');
		var parentForm = $(link).closest('form');
		var selectObj = parentForm.find('select[name="section"]').clone();
		selectObj.val($('input[name$="][ID]"]', parentDiv).val());
		parentDiv.append(selectObj);
		selectObj.bind('change', function(){
			var selectedOption = this.options.item(this.selectedIndex);
			var fieldName = '';
			var optgroup = $(selectedOption).closest('optgroup');
			if(optgroup.length > 0 && showGroup)
			{
				fieldName = optgroup.attr('label');
				if(fieldName.length > 0) fieldName += ' - ';
			}
			fieldName += selectedOption.text
			link.innerHTML = fieldName;
			$('input[name^="MAP[MAP]["]', parentDiv).remove();
			if(this.value.length > 0)
			{
				var index = 0;
				while(document.getElementById('esol_mapping_'+index)) index++;
				parentDiv.prepend('<input id="esol_mapping_'+index+'" type="hidden" name="MAP[MAP]['+index+'][XML_ID]" value=""><input type="hidden" name="MAP[MAP]['+index+'][ID]" value="">');
				$('input[name="MAP[MAP]['+index+'][XML_ID]"]', parentDiv).val(parentDiv.attr('data-xml-id'));
				$('input[name="MAP[MAP]['+index+'][ID]"]', parentDiv).val(this.value);
				if(this.value!='NOT_LOAD')
				{
					parentDiv.addClass('esol-ix-select-mapping-full');
					$('a.esol-ix-select-mapping-settings', parentDiv).prop('id', 'field_settings_0'+index).addClass('inactive').prepend('<input type="hidden" name="MAP[MAP]['+index+'][EXTRA]" value="">');
				}
				else
				{
					parentDiv.removeClass('esol-ix-select-mapping-full');
					$('a.esol-ix-select-mapping-settings', parentDiv).removeProp('id');
				}
			}
			else
			{
				parentDiv.removeClass('esol-ix-select-mapping-full');
				$('a.esol-ix-select-mapping-settings', parentDiv).removeProp('id');
			}
			if(typeof selectObj.chosen == 'function') selectObj.chosen('destroy');
			$(this).remove();
			$(link).show();
			if(this.value.length==0 && $('.esol-ix-select-mapping', parentWrap).length > 1) parentDiv.remove();
		});
		if(typeof selectObj.chosen == 'function') selectObj.chosen({search_contains: true});
		$(link).hide();
		
		if(selectObj.next('.chosen-container').length > 0)
		{
			$('body').one('click', function(e){
				e.stopPropagation();
				return false;
			});
			var chosenDiv = selectObj.next('.chosen-container')[0];
			$('a:eq(0)', chosenDiv).trigger('mousedown');
			
			var lastClassName = chosenDiv.className;
			var interval = setInterval( function() {   
				   var className = chosenDiv.className;
					if (className !== lastClassName) {
						selectObj.trigger('change');
						lastClassName = className;
						clearInterval(interval);
					}
				},50);
		}
	},
	
	AddSelectMappingField: function(link)
	{
		var parentWrap = $(link).closest('.esol-ix-select-mapping-wrap');
		var newField = $('.esol-ix-select-mapping:last', parentWrap).clone();
		newField.removeClass('esol-ix-select-mapping-full');
		$('input', newField).remove();
		$('a:first', newField).html(parentWrap.attr('data-nc-message'));
		newField.appendTo(parentWrap);
	},
	
	ShowSelectMappingSettings: function(e, btn)
	{
		return EIXPreview.ShowFieldSettings(e, btn, $('#esol_ix_xml_wrap .esol_ix_group_value_inner_'+($(btn).attr('data-group') ? $(btn).attr('data-group') : 'property')+' .esol_ix_group_value_settings'));
	}
}

var EHelper = {
	ShowHelp: function(index)
	{
		var dialog = new BX.CAdminDialog({
			'title':BX.message("ESOL_IX_POPUP_HELP_TITLE"),
			'content_url':'/bitrix/admin/'+esolIXModuleFilePrefix+'_popup_help.php?lang='+BX.message('LANGUAGE_ID'),
			'width':'900',
			'height':'450',
			'resizable':true});
			
		BX.addCustomEvent(dialog, 'onWindowRegister', function(){
			$('#esol-ix-help-faq > li > a').bind('click', function(){
				var div = $(this).next('div');
				if(div.is(':visible')) div.stop().slideUp();
				else div.stop().slideDown();
				return false;
			});
			
			if(index > 0)
			{
				$('#esol-ix-help-tabs .esol-ix-tabs-heads a:eq('+parseInt(index)+')').trigger('click');
			}
		});
			
		dialog.Show();
	},
	
	SetTab: function(link)
	{
		var parent = $(link).closest('.esol-ix-tabs');
		var heads = $('.esol-ix-tabs-heads a', parent);
		var bodies = $('.esol-ix-tabs-bodies > div', parent);
		var index = 0;
		for(var i=0; i<heads.length; i++)
		{
			if(heads[i]==link)
			{
				index = i;
				break;
			}
		}
		heads.removeClass('active');
		$(heads[index]).addClass('active');
		
		bodies.removeClass('active');
		$(bodies[index]).addClass('active');
	}
}

var EsolIxOptions = {
	AddRels: function(oLink)
	{
		var table = $(oLink).closest('td').find('table');
		var maxIndex = 0;
		var trs = $('tr[data-index]', table);
		for(var i=0; i<trs.length; i++)
		{
			if(parseInt($(trs[i]).attr('data-index')) > maxIndex) maxIndex = parseInt($(trs[i]).attr('data-index'));
		}
		maxIndex++;
		var tr = $('tr:last', table).clone();
		tr.attr('data-index', maxIndex);
		var newSelect = $('select', $(oLink).closest('div')).clone();
		newSelect.attr('name', $('select:last', tr).attr('name'));
		$('select:last', tr).replaceWith(newSelect);
		var arSelect = $('select', tr);
		for(var i=0; i<arSelect.length; i++)
		{
			$(arSelect[i]).val('');
			arSelect[i].name = arSelect[i].name.replace(/\[[_\d]+\]/, '['+maxIndex+']');
		}
		
		table.append(tr);
	},
	
	ReloadProps: function(oSelect)
	{
		var val = oSelect.value;
		var tr = $(oSelect).closest('tr');
		var newSelect = $(oSelect).closest('table').closest('td').find('.esol-ix-options-rels select').clone();
		newSelect.attr('name', $('select:last', tr).attr('name'));
		if(val.length > 0) $('optgroup[data-id!="'+val+'"]', newSelect).remove();
		$('select:last', tr).replaceWith(newSelect);
	},
	
	RemoveRel: function(oLink)
	{
		if($(oLink).closest('table').find('tr').length > 2)
		{
			$(oLink).closest('tr').remove();
		}
		else
		{
			$(oLink).closest('tr').find('select').val('').trigger('change');
		}
	}
}

function EsolIXFilter(prefix)
{
	//this.listIndex = listIndex;
	this.prefix = prefix;
	this.Fields = [],
	this.MaxFieldIndex = 0,
	this.MaxFCountIndex = 0,
	
	this.Init = function()
	{
		var obj = this;
		//this.filterBlock = $('#kda-ee-sheet-'+this.prefix+'-'+this.listIndex);
		this.filterBlock = $('#kda-ee-sheet-'+this.prefix);
		if(this.filterBlock.length==0) return false;
		this.filterBlock.attr('data-cond', 'ALL');
		$('a.kda-ee-cfilter-add-field', this.filterBlock).bind('click', function(e){
			e.stopPropagation();
			obj.AddField();
			return false;
		});
		
		var oldFilter = $('input[name="OLD_FILTER"]', this.filterBlock).val();
		if(oldFilter)
		{
			eval('var filter = '+oldFilter);
			if(typeof filter=='object')
			{
				for(var i in filter)
				{
					if(i.indexOf('_')!=-1) continue;
					this.AddField(filter, i);
				}
			}
		}
	},
	
	this.AddField = function(filterData, filterKey)
	{
		//var fieldPrefix = 'SETTINGS['+this.prefix.toUpperCase()+']'+'['+this.listIndex+']';
		var fieldPrefix = this.prefix.toUpperCase();
		var field = new EsolIXFilterField(this.filterBlock, fieldPrefix, this.MaxFieldIndex++, filterData, filterKey);
		this.Fields.push(field);
	}
	
	this.Init();
}

function EsolIXFilterField(filterBlock, fieldPrefix, fieldIndex, filterData, filterKey)
{
	this.Init = function(filterBlock, fieldPrefix, fieldIndex, filterData, filterKey)
	{
		this.fieldIndex = fieldIndex;
		this.fieldPrefixOrig = fieldPrefix;
		this.fieldPrefix = fieldPrefix+'['+this.fieldIndex+']';
		this.filterBlock = filterBlock;
		this.filterType = this.filterBlock.attr('data-type');
		var filterCond = this.filterBlock.attr('data-cond');
		this.block = $('<div class="kda-ee-cfilter-field">'+(filterCond ? '<div class="kda-ee-cfilter-field-condlabel">'+BX.message("KDA_EE_CONDITION_GROUP_BTN_"+filterCond)+'</div>' : '')+'</div>');
		this.block.appendTo($('>.kda-ee-cfilter-field-list', this.filterBlock));
		this.inGroup = (this.filterBlock.closest('.kda-ee-cfilter-group').length > 0);
		$('.kda-ee-cfilter-field-condlabel', this.block).bind('click', function(){
			var s = $(this).closest('.kda-ee-cfilter-group').prev('.kda-ee-cfilter-cond').find('select');
			if(s.length==1)
			{
				var o = $('option', s);
				var idx = (s.prop('selectedIndex') + 1)%o.length;
				s.prop('selectedIndex', idx).trigger('change');
			}
		});
		
		this.block.append('<div class="kda-ee-cfilter-select"></div>');
		this.fieldBlock = $('.kda-ee-cfilter-select', this.block);
		
		var select = $('select[name="S_FIELD"]', this.filterBlock.closest('.kda-ee-sheet-cfilter')).clone();
		if(this.inGroup)
		{
			$('option[value^="PARENT_"], option[value^="OFFER_"], option[value^="PSECTION_"]'+(this.filterType=='e' ? ', option[value^="ISECT_"]' : ''), select).remove();
		}
		select.removeAttr('id').attr('name', this.fieldPrefix+'[FIELD]');
		new FilterSelect2Text(this.fieldBlock, select, true);
		this.fieldType = 'STRING';
		var obj = this;
		select.bind('change', function(){obj.ChangeField($(this));});
		this.block.append('<a href="#" class="kda-ee-cfilter-close" title="'+BX.message("KDA_EE_REMOVE_BTN")+'"></a>');
		$('>a.kda-ee-cfilter-close', this.block).bind('click', function(e){
			e.stopPropagation();
			obj.Remove();
			return false;
		});
		
		if(typeof filterData=='object' && typeof filterData[filterKey]=='object')
		{
			this.filterData = filterData;
			this.filterKey = filterKey;
			for(var i in filterData[filterKey])
			{
				this.SetFieldVal('[name="'+this.fieldPrefix+'['+i+']'+(typeof filterData[filterKey][i]=='object' ? '[]' : '')+'"]', this.block, filterData[filterKey][i], 5000);
				//$('[name="'+this.fieldPrefix+'['+i+']"]', this.block).val(filterData[filterKey][i]).trigger('chosen:updated').trigger('change');
			}
			this.filterData = null;
			this.filterKey = null;
		}
	};
	
	this.SetFieldVal = function(selector, parentObj, val, time)
	{
		var input = $(selector, parentObj);
		if(input.length==0)
		{
			var obj = this;
			if(time > 0) setTimeout(function(){obj.SetFieldVal(selector, parentObj, val, time-200);}, 200);
			return;
		}
		
		chb = false;
		for(var i=0; i<input.length; i++)
		{
			if(input[i].type && (input[i].type=='checkbox' || input[i].type=='radio'))
			{
				if(input[i].checked != (input[i].value==val))
				{
					$(input[i]).trigger('click').trigger('change');
				}
				chb = true;
			}
		}
		if(chb) return;
		
		input.val(val);
		if(input[0].tagName=='SELECT')
		{
			if(input.val()==null) input.val('');
			ip = input.closest('.kda-ee-select');
			if(ip.length > 0 && ip.not(':visible')) ip.show();
			input.trigger('chosen:updated').trigger('change');
		}
		else
		{
			input.trigger('change');
		}
	};
	
	this.CreateGroup = function()
	{
		var obj = this;
		this.SubFields = [];
		this.MaxSubFieldIndex = 0;
		
		this.condBlock = $('<div class="kda-ee-cfilter-cond"></div>');
		this.condBlock.appendTo(this.block);
		var select = $('<select name="'+this.fieldPrefix+'[COND]'+'"><option value="ANY">'+BX.message("KDA_EE_CONDITION_GROUP_ANY")+'</option><option value="ALL">'+BX.message("KDA_EE_CONDITION_GROUP_ALL")+'</option></select>');
		new FilterSelect2Text(this.condBlock, select);
		select.bind('change', function(){
			if(!obj.subFilterBlock) return;
			if(this.value=='ANY') obj.subFilterBlock.removeClass('kda-ee-cfilter-group-all').addClass('kda-ee-cfilter-group-any');
			if(this.value=='ALL') obj.subFilterBlock.removeClass('kda-ee-cfilter-group-any').addClass('kda-ee-cfilter-group-all');
			obj.subFilterBlock.attr('data-cond', this.value);
			$('>.kda-ee-cfilter-field-list>.kda-ee-cfilter-field>.kda-ee-cfilter-field-condlabel', obj.subFilterBlock).html(BX.message("KDA_EE_CONDITION_GROUP_BTN_"+this.value));
			//EsolMEFilter.UpdateCount();
		});
		
		this.subFilterBlock = $('<div class="kda-ee-cfilter-group"><div class="kda-ee-cfilter-field-list"></div><a class="kda-ee-cfilter-add-field" href="javascript:void(0)">'+this.filterBlock.find('>a.kda-ee-cfilter-add-field').text()+'</a></div>');
		this.subFilterBlock.attr('data-type', this.filterBlock.attr('data-type'));
		this.subFilterBlock.attr('data-cond', 'OR');
		this.subFilterBlock.appendTo(this.block);
		$('a.kda-ee-cfilter-add-field', this.subFilterBlock).bind('click', function(e){
			e.stopPropagation();
			obj.AddSubField();
			return false;
		});
		select.trigger('change');
		
		if(typeof this.filterData=='object')
		{
			for(var i in this.filterData)
			{
				if(i.indexOf(this.filterKey+'_')!=0 || i.substr(this.filterKey.length+1).indexOf('_')!=-1) continue;
				this.AddSubField(this.filterData, i);
			}
		}
		else
		{
			$('a.kda-ee-cfilter-add-field', this.subFilterBlock).trigger('click');
		}
	};
	
	this.AddSubField = function(filterData, filterKey)
	{
		var field = new EsolIXFilterField(this.subFilterBlock, this.fieldPrefixOrig, this.fieldIndex+'_'+this.MaxSubFieldIndex++, filterData, filterKey);
		this.SubFields.push(field);
	};
	
	this.ChangeField = function(select)
	{
		var obj = this;
		if(this.fieldCode==select.val()) return;
		this.fieldCode = select.val();
		this.fieldCond = false;
		var option = $('option', select).eq(select.prop('selectedIndex'));
		this.fieldType = option.attr('data-type');
		
		$('div.kda-ee-cfilter-cond', this.block).remove();
		$('div.kda-ee-cfilter-value', this.block).remove();
		$('div.kda-ee-cfilter-group', this.block).remove();
		if(this.fieldCode.length==0) return;
		if(this.fieldCode=='GROUP')
		{
			this.CreateGroup();
			return;
		}
		
		this.condBlock = $('<div class="kda-ee-cfilter-cond"></div>');
		this.condBlock.appendTo(this.block);
		var select = $(this.GetConditions(this.fieldPrefix+'[COND]'));
		new FilterSelect2Text(this.condBlock, select);
		select.bind('change', function(){obj.ChangeCond($(this));}).trigger('change');
	};
	
	this.GetConditions = function(fname)
	{
		var conditions = {
			'EQ': BX.message("KDA_EE_CONDITION_EQ"),
			'NEQ': BX.message("KDA_EE_CONDITION_NEQ"),
			'LT': BX.message("KDA_EE_CONDITION_LT"),
			'LEQ': BX.message("KDA_EE_CONDITION_LEQ"),
			'GT': BX.message("KDA_EE_CONDITION_GT"),
			'GEQ': BX.message("KDA_EE_CONDITION_GEQ"),
			'CONTAINS': BX.message("KDA_EE_CONDITION_CONTAINS"),
			'NOT_CONTAINS': BX.message("KDA_EE_CONDITION_NOT_CONTAINS"),
			'BEGIN_WITH': BX.message("KDA_EE_CONDITION_BEGIN_WITH"),
			'NOT_BEGIN_WITH': BX.message("KDA_EE_CONDITION_NOT_BEGIN_WITH"),
			'ENDS_WITH': BX.message("KDA_EE_CONDITION_ENDS_WITH"),
			'EMPTY': BX.message("KDA_EE_CONDITION_EMPTY"),
			'NOT_EMPTY': BX.message("KDA_EE_CONDITION_NOT_EMPTY"),
			'LAST_N_DAYS': BX.message("KDA_EE_CONDITION_LAST_N_DAYS"),
			'NOT_LAST_N_DAYS': BX.message("KDA_EE_CONDITION_NOT_LAST_N_DAYS"),
			'DAY': BX.message("KDA_EE_CONDITION_DAY"),
			'WEEK': BX.message("KDA_EE_CONDITION_WEEK"),
			'MONTH': BX.message("KDA_EE_CONDITION_MONTH"),
			'QUARTER': BX.message("KDA_EE_CONDITION_QUARTER"),
			'YEAR': BX.message("KDA_EE_CONDITION_YEAR"),
		};
		var condKeys = ['EQ', 'NEQ', 'CONTAINS', 'NOT_CONTAINS', 'BEGIN_WITH', 'NOT_BEGIN_WITH', 'ENDS_WITH', 'LT', 'LEQ', 'GT', 'GEQ', 'EMPTY', 'NOT_EMPTY'];
		if(this.fieldType=='SECTION') condKeys = ['EQ', 'NEQ', 'EMPTY', 'NOT_EMPTY'];
		if(this.fieldType=='LIST') condKeys = ['EQ', 'NEQ', 'EMPTY', 'NOT_EMPTY'];
		if(this.fieldType=='FILE') condKeys = ['EMPTY', 'NOT_EMPTY'];
		if(this.fieldType=='NUMBER') condKeys = ['EQ', 'NEQ', 'LT', 'LEQ', 'GT', 'GEQ', 'EMPTY', 'NOT_EMPTY'];
		if(this.fieldType=='ID') condKeys = ['EQ', 'NEQ', 'LT', 'LEQ', 'GT', 'GEQ'];
		if(this.fieldType=='BOOLEAN') condKeys = ['EQ'];
		if(this.fieldType=='DATE') condKeys = ['DAY', 'WEEK', 'MONTH', 'QUARTER', 'YEAR', 'EQ', 'NEQ', 'LT', 'LEQ', 'GT', 'GEQ', 'EMPTY', 'NOT_EMPTY', 'LAST_N_DAYS', 'NOT_LAST_N_DAYS'];
		
		this.conditions = {};
		for(var i=0; i<condKeys.length; i++)
		{
			this.conditions[condKeys[i]] = conditions[condKeys[i]];
		}
		
		var condOptions = '<select name="'+fname+'">';
		for(var k in this.conditions)
		{
			condOptions += '<option value="'+k+'">'+this.conditions[k]+'</option>';
		}
		condOptions += '</select>';
		return condOptions;
	};
	
	this.ChangeCond = function(select)
	{
		var obj = this;
		if(this.fieldCond==select.val()) return;
		this.fieldCond = select.val();
		$('div.kda-ee-cfilter-value', this.block).remove();
		this.valueBlock = $('<div class="kda-ee-cfilter-value"></div>');
		this.valueBlock.appendTo(this.block);
		
		var method = 'SetCond' + this.GetMethodName(this.fieldCond);
		var method2 = 'SetCond' + this.GetMethodName(this.fieldCond+'_'+this.fieldType);
		if(this[method] && typeof this[method]=='function')
		{
			this[method]();
		}
		else if(this[method2] && typeof this[method2]=='function')
		{
			this[method2]();
		}
		else
		{
			this.SetCondDefault();
		}
	};
	
	this.OnAfterChangeCond = function()
	{
		var inputs = $('input, select', this.valueBlock);
		if(inputs.length > 0)
		{
			inputs.bind('change', function(){
				//EsolMEFilter.UpdateCount();
			});
		}
		//else EsolMEFilter.UpdateCount();
	};
	
	this.GetMethodName = function(val)
	{
		var parts = val.split('_');
		for(var i=0; i<parts.length; i++)
		{
			parts[i] = parts[i].substr(0, 1).toUpperCase() + parts[i].substr(1).toLowerCase();
		}
		return parts.join('');
	};
	
	this.SetCondDefault = function()
	{
		this.valueBlock.append('<input type="text" name="'+this.fieldPrefix+'[VALUE]" value="">');
		this.OnAfterChangeCond();
	};
	
	this.SetCondDayDate = this.SetCondMonthDate = this.SetCondQuarterDate = this.SetCondYearDate = function()
	{
		this.valueBlock.append('<select name="'+this.fieldPrefix+'[VALUE]">'+
				'<option value="previous">'+BX.message("KDA_EE_CONDITION_DATE_PREVIOUS")+'</option>'+
				'<option value="current">'+BX.message("KDA_EE_CONDITION_DATE_CURRENT")+'</option>'+
				'<option value="next">'+BX.message("KDA_EE_CONDITION_DATE_NEXT")+'</option>'+
			'</select>');
	};
	
	this.SetCondWeekDate = function()
	{
		this.valueBlock.append('<select name="'+this.fieldPrefix+'[VALUE]">'+
				'<option value="previous">'+BX.message("KDA_EE_CONDITION_DATE_PREVIOUS_F")+'</option>'+
				'<option value="current">'+BX.message("KDA_EE_CONDITION_DATE_CURRENT_F")+'</option>'+
				'<option value="next">'+BX.message("KDA_EE_CONDITION_DATE_NEXT_F")+'</option>'+
			'</select>');
	};
	
	this.SetCondEqDate = this.SetCondNeqDate = this.SetCondLtDate = this.SetCondLeqDate = this.SetCondGtDate = this.SetCondGeqDate = function()
	{
		this.SetCondDefault();
		
		this.valueBlock.find('input[name="'+this.fieldPrefix+'[VALUE]"]').bind('click', function(){
			BX.calendar({node: this, field: this});
		});
	};
	
	this.SetCondEqBoolean = function()
	{
		var div = $('<div class="kda-ee-filter-value-select"></div>');
		div.appendTo(this.valueBlock);
		var option, select = $('<select name="'+this.fieldPrefix+'[VALUE]"></select>');
		select.append('<option value="">'+BX.message("KDA_EE_CHOOSE_VALUE")+'</option>');
		select.append('<option value="Y">'+BX.message("KDA_EE_VALUE_YES")+'</option>');
		select.append('<option value="N">'+BX.message("KDA_EE_VALUE_NO")+'</option>');
		var selectParent = $('<div class="kda-ee-select"></div>');
		selectParent.appendTo(div);
		select.appendTo(selectParent);
		if(typeof select.chosen == 'function') select.chosen({search_contains: true, placeholder_text: BX.message("KDA_EE_CHOOSE_VALUE")});
		this.OnAfterChangeCond();
	};
	
	this.SetCondEmpty = this.SetCondNotEmpty = function()
	{
		this.OnAfterChangeCond();
	};
	
	this.SetCondEqList = this.SetCondNeqList = function(single, callback)
	{
		if(!callback || !this[callback] || typeof this[callback] != 'function') callback = 'SetCondListCallback';
		var valsInputName = 'FVALS_'+this.fieldCode;
		var valsInput = this.filterBlock.find('input[name="'+valsInputName+'"]');
		if(valsInput.length > 0)
		{
			this[callback](valsInput.val(), single);
		}
		else
		{
			var obj = this;
			$.post(window.location.href, 'MODE=AJAX&ACTION=GET_FILTER_FIELD_VALS&FIELD='+this.fieldCode+/*'&ETYPE='+$('#ETYPE').val()+*/'&IBLOCK_ID='+$('input[name="IBLOCK_ID"]', this.filterBlock.closest('.kda-ee-sheet-cfilter')).val(), function(data){
				var newInput = $('<input type="hidden" name="'+valsInputName+'" value="">');
				newInput.val(data);
				obj.filterBlock.find('input[name="IBLOCK_ID"]').after(newInput);
				obj[callback](data, single);
			});
		}
	};
	
	this.SetCondListCallback = function(data, single)
	{
		var result = {};
		data = $.trim(data);
		if(data && data.substr(0, 1)=='{' && data.substr(data.length-1)=='}')
		{
			eval('result = '+data+';');
		}
		
		$('div.kda-ee-filter-value-select', this.valueBlock).remove();
		var div = $('<div class="kda-ee-filter-value-select"></div>');
		div.appendTo(this.valueBlock);
		var option, select = $('<select name="'+this.fieldPrefix+'[VALUE][]" multiple></select>');
		if(single) select = $('<select name="'+this.fieldPrefix+'[VALUE]"></select>');
		select.append('<option value="">'+BX.message("KDA_EE_CHOOSE_VALUE")+'</option>');
		if(result.values)
		{
			for(var i=0; i<result.values.length; i++)
			{
				option = $('<option value="">'+result.values[i].value+'</option>');
				option.attr('value', result.values[i].key);
				option.appendTo(select);
			}
		}
		var selectParent = $('<div class="kda-ee-select"></div>');
		selectParent.appendTo(div);
		select.appendTo(selectParent);
		if(typeof select.chosen == 'function') select.chosen({search_contains: true, placeholder_text: BX.message("KDA_EE_CHOOSE_VALUE"), width: '350px'});
		this.OnAfterChangeCond();
	};
	
	this.SetCondEqSection = this.SetCondNeqSection = function(single)
	{
		this.SetCondEqList(single, 'SetCondSectionCallback');
	};
	
	this.SetCondSectionCallback = function(data, single)
	{
		var result = {};
		data = $.trim(data);
		if(data && data.substr(0, 1)=='{' && data.substr(data.length-1)=='}')
		{
			eval('result = '+data+';');
		}
		
		$('div.kda-ee-cfilter-value-select', this.valueBlock).remove();
		var div = $('<div class="kda-ee-cfilter-value-select"></div>');
		div.appendTo(this.valueBlock);
		var option, select = $('<select name="'+this.fieldPrefix+'[VALUE][]" multiple></select>');
		if(single) select = $('<select name="'+this.fieldPrefix+'[VALUE]"></select>');
		select.append('<option value="">'+BX.message("KDA_EE_CHOOSE_VALUE")+'</option>');
		if(result.values)
		{
			for(var i=0; i<result.values.length; i++)
			{
				option = $('<option value="">'+result.values[i].value+'</option>');
				option.attr('value', result.values[i].key);
				option.appendTo(select);
			}
		}
		var selectParent = $('<div class="kda-ee-select"></div>');
		selectParent.appendTo(div);
		select.appendTo(selectParent);
		if(typeof select.chosen == 'function') select.chosen({search_contains: true, placeholder_text: BX.message("KDA_EE_CHOOSE_VALUE"), width: '350px'});
		
		if(this.filterType=='e' || this.filterType=='s')
		{
			var chbId = (this.fieldPrefix+'[INCLUDE_SUBSECTIONS]').replace('/[\[\]]/g', '_');
			$('div.kda-ee-cfilter-value-chb', this.valueBlock).remove();
			this.valueBlock.append('<div class="kda-ee-cfilter-value-chb"><input type="checkbox" name="'+this.fieldPrefix+'[INCLUDE_SUBSECTIONS]" value="Y" id="'+chbId+'"><label for="'+chbId+'">'+BX.message("KDA_EE_INCLUDE_SUBSECTIONS")+'</label></div>');
		}
		
		this.OnAfterChangeCond();
	}
	
	this.SetCondEqSectionSection = this.SetCondNeqSectionSection = function()
	{
		this.SetCondEqSection(true);
	}
	
	this.Remove = function()
	{
		this.block.remove();
		//EsolMEFilter.UpdateCount();
	};
	
	this.Init(filterBlock, fieldPrefix, fieldIndex, filterData, filterKey);
}

function FilterSelect2Text(div, select)
{
	this.Init = function(div, select)
	{
		this.div = div;
		this.select = select;
		this.selectParent = $('<div class="kda-ee-select"></div>');
		this.selectParent.appendTo(this.div);
		this.select.appendTo(this.selectParent);
		this.div.append('<a href="#" class="kda-ee-actiontext">&nbsp;</a>');
		$('.kda-ee-actiontext', this.div).css('visibility', 'hidden');
		var obj = this;
		if(typeof this.select.chosen == 'function') this.select.chosen({search_contains: true}).bind('change', function(){obj.Change();}).trigger('change');
	};
	
	this.Change = function()
	{
		//if(!$(this.selectParent).is(':visible')) return;
		if(this.select.val()==null || this.select.val().length==0) return;
		this.selectParent.hide();
		var actionText = $('option', this.select).eq(this.select.prop('selectedIndex')).text();
		$('.kda-ee-actiontext', this.div).remove();
		this.div.append('<a href="#" class="kda-ee-actiontext">'+actionText+'</a>');
		var obj = this;
		$('.kda-ee-actiontext', this.div).bind('click', function(e){
			e.stopPropagation();
			if($('option', obj.select).length > 1)
			{
				//$(this).remove();
				$(this).css('visibility', 'hidden');
				obj.selectParent.show();
				/*$('body').one('click', function(e){
					e.stopPropagation();
					return false;
				});*/
				var chosenDiv = obj.select.next('.chosen-container')[0];
				$('a:eq(0)', chosenDiv).trigger('mousedown');
				
				var lastClassName = chosenDiv.className;
				var interval = setInterval( function() {   
					   var className = chosenDiv.className;
						if (className !== lastClassName) {
							obj.select.trigger('change');
							lastClassName = className;
							clearInterval(interval);
						}
					},30);
			}
			return false;
		});
	}
	
	this.Init(div, select);
}

function Select2Text(div, select)
{
	this.Init = function(div, select)
	{
		this.div = div;
		this.select = select;
		this.selectParent = $('<div class="esol-ix-select"></div>');
		this.selectParent.appendTo(this.div);
		this.select.appendTo(this.selectParent);
		var obj = this
		if(typeof this.select.chosen == 'function') this.select.chosen({search_contains: true}).bind('change', function(){obj.Change();}).trigger('change');
	};
	
	this.Change = function()
	{
		if(!$(this.selectParent).is(':visible')) return;
		if(this.select.val().length==0) return;
		this.selectParent.hide();
		var actionText = $('option', this.select).eq(this.select.prop('selectedIndex')).text();
		$('.esol-ix-actiontext', this.div).remove();
		this.div.append('<a href="#" class="esol-ix-actiontext">'+actionText+'</a>');
		var obj = this;
		$('.esol-ix-actiontext', this.div).bind('click', function(e){
			e.stopPropagation();
			if($('option', obj.select).length > 1)
			{
				$(this).remove();
				obj.selectParent.show();
				$('body').one('click', function(e){
					e.stopPropagation();
					return false;
				});
				var chosenDiv = obj.select.next('.chosen-container')[0];
				$('a:eq(0)', chosenDiv).trigger('mousedown');
				
				var lastClassName = chosenDiv.className;
				var interval = setInterval( function() {   
					   var className = chosenDiv.className;
						if (className !== lastClassName) {
							obj.select.trigger('change');
							lastClassName = className;
							clearInterval(interval);
						}
					},30);
			}
			return false;
		});
	}
	
	this.Init(div, select);
}

$(document).ready(function(){
	/*Bug fix with excess jquery*/
	var anySelect = $('select:eq(0)');
	if(typeof anySelect.chosen!='function')
	{
		var jQuerySrc = $('script[src*="/bitrix/js/main/jquery/"]').attr('src');
		if(jQuerySrc)
		{
			$.getScript(jQuerySrc, function(){
				$.getScript('/bitrix/js/'+esolIXModuleName+'/chosen/chosen.jquery.min.js');
			});
		}
	}
	/*/Bug fix with excess jquery*/
	
	$('.esol-ix-legend-subtitle a').bind('click', function(e){
		e.stopPropagation();
		$(this).closest('.esol-ix-legend-subtitle').toggleClass('esol-ix-legend-subtitle-open');
		return false;
	});
		
	if($('#preview_file').length > 0)
	{
		var post = $('#preview_file').closest('form').serialize() + '&ACTION=SHOW_REVIEW_LIST';
		$.post(window.location.href, post, function(data){
			$('#preview_file').html(data);
			EIXPreview.Init();
		});
	}

	EProfile.Init();
	
	var findProfileSelect = $('#filter_find_form select[name="find_profile_id"]');
	if(findProfileSelect.length > 0 && typeof findProfileSelect.chosen == 'function')
	{
		findProfileSelect.chosen({search_contains: true, placeholder_text: BX.message("ESOL_IX_SELECT_NOT_CHOSEN"), width: '300px'});
		findProfileSelect.closest('.adm-filter-main-table').addClass('adm-filter-main-table-chosen');
		findProfileSelect.closest('.adm-filter-content').addClass('adm-filter-content-chosen');
		findProfileSelect.closest('.adm-filter-item-center').addClass('adm-filter-item-center-chosen');
		findProfileSelect.closest('.adm-select-wrap').addClass('adm-select-wrap-chosen');
	}
	
	if($('#esol-ix-updates-message').length > 0)
	{
		$.post('/bitrix/admin/'+esolIXModuleFilePrefix+'.php?lang='+BX.message('LANGUAGE_ID'), 'MODE=AJAX&ACTION=SHOW_MODULE_MESSAGE', function(data){
			data = $(data);
			var inner = $('#esol-ix-updates-message-inner', data);
			if(inner.length > 0 && inner.html().length > 0)
			{
				$('#esol-ix-updates-message-inner').replaceWith(inner);
				$('#esol-ix-updates-message').show();
			}
		});
	}
});