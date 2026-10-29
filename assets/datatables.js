var ifcf7_sdt = {

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	configs: {},

	tables: {},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	add: function(config){
		jQuery(function($){
			ifcf7_sdt.load($, config);
		});
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	classes: function($){
		$.extend(true, $.fn.dataTable.ext.classes, {
			container: 'dt-container dt-bootstrap4 bg-light border overflow-hidden pt-3 px-3 rounded mb-3',
			empty: {
				row: 'dt-empty text-left',
			},
			length: {
				container: 'dt-length mb-3 d-flex flex-column flex-md-row align-items-center',
				select: 'custom-select ml-0 ml-md-2 mt-2 mt-md-0 mr-0',
			},
			processing: {
				container: 'dt-processing card position-fixed ml-0 mt-0 shadow-lg',
			},
			search: {
				container: 'dt-search mb-3 d-flex flex-column flex-md-row align-items-center',
				input: 'form-control ml-0 ml-md-2 mt-2 mt-md-0',
			},
		});
		$.extend(true, $.fn.dataTable.Buttons.defaults, {
			dom: {
				button: {
					className: 'btn btn-outline-secondary',
				},
			},
		});
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	criteria: function(instances, config, deformat){
		var criteria = [];
		if(instances.length){
			jQuery.each(instances, function(index, instance){
				criteria.push(instance.getDetails(deformat));
			});
			return criteria;
		}
		// Before the panels are built (the very first request of a saved view).
		jQuery.each(config.searchBuilders || [], function(index, search_builder){
			if(config.criteria && config.criteria[search_builder.group]){
				criteria.push(config.criteria[search_builder.group]);
			}
		});
		return criteria;
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	criteria_by_group: function(instances, config, deformat){
		var criteria = {};
		jQuery.each(instances, function(index, instance){
			if(instance.ifcf7_sdt_group){
				criteria[instance.ifcf7_sdt_group] = instance.getDetails(deformat);
			}
		});
		return criteria;
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	download: function($, dt, node, config, instances){
		var settings = ifcf7_sdt_settings;
		if(!window.confirm(settings.export.confirm)){
			return;
		}
		var html = $(node).html();
		$(node).html(settings.export.waiting).prop('disabled', true);
		setTimeout(function(){
			$(node).html(html).prop('disabled', false);
		}, 5000);
		var columns = ifcf7_sdt.visible_columns(dt);
		var fields = {
			_wpnonce: settings.export.nonce,
			ifcf7_sdt_action: 'export',
			ifcf7_sdt_columns: columns.join(','),
			ifcf7_sdt_criteria: JSON.stringify(ifcf7_sdt.criteria(instances, config, true)),
			ifcf7_sdt_form_id: config.form_id || '',
			ifcf7_sdt_search: dt.search() || '',
		};
		var form = $('<form>', {
			action: settings.export.url,
			method: 'POST',
		});
		$.each(fields, function(name, value){
			form.append($('<input>', {
				name: name,
				type: 'hidden',
				value: value,
			}));
		});
		form.appendTo('body').trigger('submit');
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	load: function($, config){
		if(!$.fn.dataTable){
			return;
		}
		var element = $('#' + config.id);
		if(!element.length || $.fn.dataTable.isDataTable(element)){
			return;
		}
		var settings = $.extend(true, {}, ifcf7_sdt_settings);
		if(!settings.language.url){
			delete settings.language.url; // No translation file for this locale: English it is.
		}
		var instances = [];
		ifcf7_sdt.classes($);
		ifcf7_sdt.configs[config.id] = config;
		config.ifcf7_sdt_instances = instances; // The sidebar footer does reach them through here.
		var buttons = [];
		if(settings.sidebar){
			buttons.push({
				action: function(){
					ifcf7_sdt.sidebar_toggle($, config);
				},
				className: 'ifcf7-sdt-filters', // How its badge does find it back.
				text: ifcf7_sdt.filters_text(0),
				titleAttr: settings.text.filtersTitle,
			});
		}
		var colvis_text = '<i class="fa-solid fa-table-columns"></i> ' + settings.text.columns;
		var colvis;
		if(config.colvisGroups && config.colvisGroups.length){
			// A collection of collections: the groups of the search builders, one list each.
			colvis = {
				buttons: $.map(config.colvisGroups, function(group){
					var button = {
						columns: '.ifcf7-sdt-group-' + group.group + ':not(.ifcf7-sdt-no-colvis)',
						extend: 'colvis',
						text: group.title,
					};
					if(settings.colvisLayout){
						button.collectionLayout = settings.colvisLayout;
					}
					return button;
				}),
				extend: 'collection',
				text: colvis_text,
				titleAttr: settings.text.columns,
			};
		} else {
			colvis = {
				columns: ':not(.ifcf7-sdt-no-colvis)',
				extend: 'colvis',
				text: colvis_text,
				titleAttr: settings.text.columns,
			};
			if(settings.colvisLayout){
				colvis.collectionLayout = settings.colvisLayout;
			}
		}
		buttons.push(colvis);
		if(config.formColumn && !settings.autoSync){ // Automatic: no button needed.
			buttons.push({
				action: function(e, dt){
					ifcf7_sdt.sync($, dt, config, instances);
				},
				text: '<i class="fa-solid fa-arrows-rotate"></i> ' + settings.text.sync,
				titleAttr: settings.text.sync,
			});
		}
		if(settings.export.enabled){
			buttons.push({
				action: function(e, dt, node){
					ifcf7_sdt.download($, dt, node, config, instances);
				},
				text: '<i class="fa-regular fa-file-excel"></i> ' + settings.export.text,
				titleAttr: settings.export.text,
			});
		}
		if(!settings.sidebar && settings.views && settings.views.create){ // With a sidebar, these live in its footer.
			if(settings.views.update){
				buttons.push({
					action: function(e, dt, node){
						ifcf7_sdt.save($, dt, config, instances, $(node));
					},
					className: 'ifcf7-sdt-save-view', // How view_buttons() does find them back.
					text: '<i class="fa-solid fa-floppy-disk"></i> ' + settings.views.save,
					titleAttr: settings.views.save,
				});
			}
			buttons.push({
				action: function(e, dt){
					ifcf7_sdt.view($, dt, config, instances);
				},
				className: 'ifcf7-sdt-create-view',
				text: '<i class="fa-regular fa-bookmark"></i> ' + (settings.views.update ? settings.views.saveAs : settings.views.text),
				titleAttr: settings.views.update ? settings.views.saveAs : settings.views.text,
			});
		}
		// The search box ("f") is the only difference between the two layouts, and it is only there
		// when the ifcf7_sdt_search hook does ask for it.
		var dom = '<"d-flex flex-column flex-md-row justify-content-between align-items-center" <"d-flex flex-column flex-md-row align-items-center mb-3" B i >' + (settings.search ? ' f' : '') + '> <"table-responsive position-relative mb-3 bg-white border" t > <"align-items-center d-flex flex-column flex-md-row justify-content-between" l p >';
		ifcf7_sdt.tables[config.id] = element.DataTable({
			ajax: {
				data: function(data){
					if(config.form_id){
						data.form_id = config.form_id;
					}
					// One criteria group per search builder, all of them ANDed by the backend.
					data.ifcf7_sdt_criteria = ifcf7_sdt.criteria(instances, config, true);
					// DataTables sends six parameters for every single column. With a hundred
					// columns that is some six hundred variables before any criteria: enough to
					// cross the max_input_vars of PHP (1000 by default), which drops the rest of
					// the request silently. Only the data of each column is read server side.
					data.columns = $.map(data.columns || [], function(column){
						return {
							data: column.data,
						};
					});
				},
				dataSrc: function(json){
					// The value dropdowns of the search builders. Caught right here because this
					// does run on the very first response, while the listener of SearchBuilder is
					// only bound once the panels exist, which is later.
					if(json && json.searchBuilder && json.searchBuilder.options){
						config.ifcf7_sdt_options = json.searchBuilder.options;
					}
					return json.data;
				},
				headers: {
					'X-WP-Nonce': settings.nonce,
				},
				type: 'GET',
				url: settings.url,
			},
			buttons: buttons,
			columns: config.columns,
			dom: dom,
			initComplete: function(){
				// The DataTable() call has not returned yet, so tables[] is not set at this point.
				ifcf7_sdt.tables[config.id] = this.api();
				ifcf7_sdt.errors($, this.api());
				ifcf7_sdt.search_builders($, this.api(), config, instances);
			},
			language: settings.language,
			order: config.order,
			orderMulti: false,
			pageLength: config.pageLength,
			processing: true,
			search: {
				// A view does keep whatever text it was saved with: it comes back on its own the
				// day the hook does turn the box on again.
				search: settings.search ? (config.search || '') : '',
			},
			searchDelay: 600,
			serverSide: true,
			stateDuration: 0,
			stateLoadParams: function(dt_settings, data){
				// Several search builders can not share a single state key.
				delete data.searchBuilder;
				if(config.isView || !settings.search){
					// A saved view does define its own filters, and with no box on screen there is
					// no way to clear a text a former state did save.
					delete data.search;
				}
				if(config.viewColumns){
					delete data.columns; // And its own columns, which are not up to the state either.
				}
				if(data.ifcf7_sdt_signature !== config.signature){
					return false; // The columns are not the ones this state was saved for.
				}
			},
			stateSave: config.stateSave,
			stateSaveParams: function(dt_settings, data){
				data.ifcf7_sdt_signature = config.signature;
			},
		});
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	filters_text: function(count){
		var settings = ifcf7_sdt_settings;
		var text = '<i class="fa-solid fa-filter"></i> ' + settings.text.filters;
		if(count){
			text += ' <span class="badge badge-primary">' + count + '</span>';
		}
		return text;
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	filters_badge: function($, table, config, instances){
		var button = $(table.table().container()).find('.ifcf7-sdt-filters');
		if(!button.length){ // No sidebar: the panels are on the page, there is nothing to badge.
			return;
		}
		var text = button.find('.dt-button-text'); // Buttons does wrap its label in there.
		(text.length ? text : button).html(ifcf7_sdt.filters_text(ifcf7_sdt.criteria_count(instances, config)));
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * A view with no criteria at all is the general table under another name: saving one is off
	 * until there is something to save. Both layouts are looked into, the sidebar footer and the
	 * button bar, and the title does say why while it lasts.
	 */
	view_buttons: function($, table, config, instances){
		var settings = ifcf7_sdt_settings;
		if(!settings.views || !settings.views.create){
			return; // No buttons to begin with.
		}
		var buttons = $(table.table().container()).find('.ifcf7-sdt-create-view, .ifcf7-sdt-save-view');
		if(settings.sidebar){
			// Never ifcf7_sdt.sidebar(), which would build one where there is none.
			buttons = buttons.add($('#ifcf7-sdt-sidebar-' + config.id).find('.ifcf7-sdt-create-view, .ifcf7-sdt-save-view'));
		}
		var enabled = (ifcf7_sdt.criteria_count(instances, config) > 0);
		buttons.each(function(){
			var button = $(this);
			if(typeof button.data('ifcf7-sdt-title') === 'undefined'){
				button.data('ifcf7-sdt-title', button.attr('title') || '');
			}
			button.attr('title', enabled ? button.data('ifcf7-sdt-title') : settings.views.noFilters)
				// The class is what Buttons and Bootstrap do style, the property what stops a click.
				.toggleClass('disabled', !enabled)
				.prop('disabled', !enabled);
		});
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * How many criteria are active, every panel together. Reads the instances, or the criteria of a
	 * saved view before the panels are built, the very same way the request itself does.
	 */
	criteria_count: function(instances, config){
		var count = 0;
		jQuery.each(ifcf7_sdt.criteria(instances, config), function(index, details){
			count += ifcf7_sdt.count(details);
		});
		return count;
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	count: function(details){
		var count = 0;
		if(!details || !details.criteria || !details.criteria.length){
			return count;
		}
		jQuery.each(details.criteria, function(index, criteria){
			if(criteria.criteria){
				count += ifcf7_sdt.count(criteria); // Nested groups.
			} else if(ifcf7_sdt.filled(criteria)){
				count ++;
			}
		});
		return count;
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * A criteria row is a filter once the backend would do something with it: a field, a condition,
	 * and the values that very condition does need (none for "empty", two for "between"). Pressing
	 * "+" does add a row to the details right away, so these are the rules of Query::normalize_leaf()
	 * again: whatever it does drop is no filter, neither for the badge nor for the save buttons.
	 */
	filled: function(criteria){
		if(!criteria || !criteria.condition){
			return false;
		}
		// origData is the one SearchBuilder does send, the others are the fallbacks the backend
		// does accept as well.
		if(!criteria.origData && !criteria.dataOrig && !criteria.data){
			return false;
		}
		var required = 1;
		if(jQuery.inArray(criteria.condition, ['null', '!null', 'empty', '!empty']) >= 0){
			required = 0;
		} else if(jQuery.inArray(criteria.condition, ['between', '!between']) >= 0){
			required = 2;
		}
		var values = criteria.value || [];
		for(var index = 0; index < required; index ++){
			var value = (typeof values[index] === 'undefined' || null === values[index]) ? '' : String(values[index]);
			if(!value.replace(/^\s+|\s+$/g, '')){
				return false;
			}
		}
		return true;
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	modal: function($){
		var settings = ifcf7_sdt_settings;
		var modal = $('#ifcf7-sdt-modal');
		if(modal.length){
			return modal;
		}
		modal = $('<div class="modal fade" id="ifcf7-sdt-modal" tabindex="-1" role="dialog" aria-hidden="true">' +
			'<div class="modal-dialog" role="document">' +
				'<div class="modal-content">' +
					'<div class="modal-header">' +
						'<h5 class="modal-title"></h5>' +
						'<button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span></button>' +
					'</div>' +
					'<div class="modal-body">' +
						'<div class="form-group mb-0">' +
							'<label for="ifcf7-sdt-modal-name"></label>' +
							'<input type="text" class="form-control" id="ifcf7-sdt-modal-name" autocomplete="off">' +
						'</div>' +
						'<div class="alert alert-danger mb-0 mt-3 d-none" role="alert"></div>' +
					'</div>' +
					'<div class="modal-footer">' +
						'<button type="button" class="btn btn-outline-secondary" data-dismiss="modal"></button>' +
						'<button type="button" class="btn btn-primary ifcf7-sdt-modal-save"></button>' +
					'</div>' +
				'</div>' +
			'</div>' +
		'</div>');
		modal.find('.modal-title').text(settings.views.text);
		modal.find('label').text(settings.text.name);
		modal.find('.btn[data-dismiss="modal"]').text(settings.text.cancel);
		modal.find('.ifcf7-sdt-modal-save').text(settings.text.save);
		modal.appendTo('body');
		return modal;
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	server_data: function(instance, options){
		if(!options || !instance.s){
			return;
		}
		// SearchBuilder picks these up from the searchBuilder.options of an ajax response, but its
		// own listener is bound at construction: ours are built after the first one has come and
		// gone, so dataSrc catches them instead. Seeding them by hand is what makes the value
		// dropdowns show up right away. Should this ever stop matching SearchBuilder internals,
		// the values fall back to plain inputs.
		instance.s.serverData = options;
		if(instance.s.topGroup && instance.s.topGroup.s){
			instance.s.topGroup.s.serverData = options;
		}
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	sidebar: function($, config){
		var id = 'ifcf7-sdt-sidebar-' + config.id;
		var sidebar = $('#' + id);
		if(sidebar.length){
			return sidebar;
		}
		var settings = ifcf7_sdt_settings;
		var backdrop = $('<div class="ifcf7-sdt-sidebar-backdrop"></div>');
		sidebar = $('<div class="ifcf7-sdt-sidebar" id="' + id + '" tabindex="-1" role="dialog" aria-modal="true" aria-hidden="true">' +
			'<div class="ifcf7-sdt-sidebar-header align-items-center bg-light border-bottom d-flex justify-content-between px-3 py-2">' +
				'<h5 class="mb-0"></h5>' +
				'<button type="button" class="close ifcf7-sdt-sidebar-close"><span aria-hidden="true">&times;</span></button>' +
			'</div>' +
			'<div class="dt-container dt-bootstrap4 ifcf7-sdt-sidebar-body p-3"></div>' +
		'</div>');
		sidebar.find('h5').text(settings.text.filtersTitle);
		sidebar.find('.ifcf7-sdt-sidebar-close').attr('aria-label', settings.text.close).on('click', function(){
			ifcf7_sdt.sidebar_toggle($, config, false);
		});
		if(settings.views && settings.views.create){ // The criteria being saved are right above it.
			var footer = $('<div class="bg-light border-top ifcf7-sdt-sidebar-footer p-3">' +
				'<div class="btn-group d-flex" role="group"></div>' +
			'</div>');
			var group = footer.find('.btn-group');
			if(settings.views.update){ // Overwrites the view being looked at.
				$('<button type="button" class="btn btn-outline-secondary ifcf7-sdt-save-view ifcf7-sdt-sidebar-save w-100"></button>')
					.html('<i class="fa-solid fa-floppy-disk"></i> ' + settings.views.save)
					.attr('title', settings.views.save)
					.on('click', function(){
						ifcf7_sdt.save($, ifcf7_sdt.tables[config.id], config, config.ifcf7_sdt_instances || [], $(this));
					})
					.appendTo(group);
			}
			$('<button type="button" class="btn btn-outline-secondary ifcf7-sdt-create-view ifcf7-sdt-sidebar-view w-100"></button>')
				.html('<i class="fa-regular fa-bookmark"></i> ' + (settings.views.update ? settings.views.saveAs : settings.views.text))
				.attr('title', settings.views.update ? settings.views.saveAs : settings.views.text)
				.on('click', function(){
					ifcf7_sdt.view($, ifcf7_sdt.tables[config.id], config, config.ifcf7_sdt_instances || []);
				})
				.appendTo(group);
			if(settings.views.delete){ // Told apart on purpose: it is the destructive one.
				$('<button type="button" class="btn btn-outline-danger ifcf7-sdt-sidebar-delete w-100"></button>')
					.html('<i class="fa-regular fa-trash-can"></i> ' + settings.views.deleteText)
					.attr('title', settings.views.deleteText)
					.on('click', function(){
						ifcf7_sdt.remove($, $(this));
					})
					.appendTo(group);
			}
			sidebar.append(footer);
		}
		backdrop.on('click', function(){
			ifcf7_sdt.sidebar_toggle($, config, false);
		}).appendTo('body');
		sidebar.data('ifcf7-sdt-backdrop', backdrop).appendTo('body');
		$(document).on('keydown.ifcf7-sdt-' + config.id, function(e){
			if(27 === e.which && sidebar.hasClass('show')){
				ifcf7_sdt.sidebar_toggle($, config, false);
			}
		});
		return sidebar;
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	sidebar_toggle: function($, config, show){
		var sidebar = ifcf7_sdt.sidebar($, config);
		var backdrop = sidebar.data('ifcf7-sdt-backdrop');
		if(typeof show === 'undefined'){
			show = !sidebar.hasClass('show');
		}
		sidebar.toggleClass('show', show).attr('aria-hidden', show ? 'false' : 'true');
		backdrop.toggleClass('show', show);
		$('body').toggleClass('ifcf7-sdt-sidebar-open', show);
		if(show){
			sidebar.trigger('focus');
		}
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	search_builders: function($, table, config, instances){
		var SearchBuilder = $.fn.dataTable.SearchBuilder;
		var settings = ifcf7_sdt_settings;
		if(!SearchBuilder || !config.searchBuilders || !config.searchBuilders.length){
			return;
		}
		var dt_settings = table.settings()[0];
		if(dt_settings.oLanguage && dt_settings.oLanguage.searchBuilder){
			// Otherwise every panel would show the very same title.
			delete dt_settings.oLanguage.searchBuilder.title;
		}
		var sidebar = settings.sidebar ? ifcf7_sdt.sidebar($, config).find('.ifcf7-sdt-sidebar-body') : null;
		var anchor = $(table.table().node()).closest('.table-responsive');
		if(!anchor.length){
			anchor = $(table.table().node());
		}
		$.each(config.searchBuilders, function(index, search_builder){
			try {
				// Every instance does register itself as the one and only search builder of the
				// table, and bails out early when another one did it first.
				delete dt_settings._searchBuilder;
				var instance = new SearchBuilder(table, {
					columns: ifcf7_sdt.panel_columns($, config, search_builder),
					filterChanged: function(){
						ifcf7_sdt.filters_badge($, table, config, instances);
						ifcf7_sdt.view_buttons($, table, config, instances);
						ifcf7_sdt.auto_sync($, table, config, instances);
					},
					i18n: {
						title: search_builder.title,
					},
				});
				var node = $(instance.getNode());
				if(!node.children().length){
					throw new Error('IFCF7 SDT: the "' + search_builder.group + '" search builder was not built.');
				}
				ifcf7_sdt.server_data(instance, config.ifcf7_sdt_options);
				node.addClass('ifcf7-sdt-searchbuilder-' + search_builder.group);
				if(sidebar){
					node.appendTo(sidebar);
				} else {
					node.insertBefore(anchor);
				}
				instance.ifcf7_sdt_group = search_builder.group; // A panel that fails to build must not shift the rest.
				instances.push(instance);
				ifcf7_sdt.select2($, node);
				if(config.criteria && config.criteria[search_builder.group]){
					// The data was already requested with these criteria: no redraw needed.
					instance.rebuild(config.criteria[search_builder.group], false);
				}
			} catch(error){
				window.console && window.console.error(error); // The table is still usable without this panel.
			}
		});
		// Whatever changes the criteria does end up redrawing the table, badge included.
		table.off('draw.ifcf7sdt').on('draw.ifcf7sdt', function(){
			ifcf7_sdt.filters_badge($, table, config, instances);
			ifcf7_sdt.view_buttons($, table, config, instances);
			ifcf7_sdt.auto_sync($, table, config, instances);
		});
		ifcf7_sdt.filters_badge($, table, config, instances);
		ifcf7_sdt.view_buttons($, table, config, instances);
		// The forms picked right now are recorded without syncing: the columns of a saved view are
		// its own, and nothing was changed yet on the general table either.
		config.ifcf7_sdt_forms = ifcf7_sdt.selected_forms($, config, instances);
		config.ifcf7_sdt_forms_key = ifcf7_sdt.forms_key(config.ifcf7_sdt_forms);
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	remove: function($, button){
		var settings = ifcf7_sdt_settings;
		if(!settings.views.delete || !window.confirm(settings.views.confirm)){
			return;
		}
		var html = button.html();
		button.prop('disabled', true);
		$.ajax({
			headers: {
				'X-WP-Nonce': settings.nonce,
			},
			method: 'DELETE',
			url: settings.views.delete,
		}).done(function(response){
			window.location.href = (response && response.url) ? response.url : settings.views.archive;
		}).fail(function(xhr){
			button.html(html).prop('disabled', false);
			window.alert(ifcf7_sdt.error(xhr));
		});
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	save: function($, dt, config, instances, button){
		var settings = ifcf7_sdt_settings;
		if(!dt || !settings.views.update){
			return;
		}
		var html = button.html();
		button.prop('disabled', true);
		$.ajax({
			contentType: 'application/json; charset=utf-8',
			data: JSON.stringify({
				columns: ifcf7_sdt.visible_columns(dt),
				criteria: ifcf7_sdt.criteria_by_group(instances, config, true),
				form_id: config.form_id || '',
				search: dt.search() || '',
			}),
			headers: {
				'X-WP-Nonce': settings.nonce,
			},
			method: 'POST',
			url: settings.views.update,
		}).done(function(){
			button.html('<i class="fa-solid fa-check"></i> ' + settings.views.save);
			setTimeout(function(){
				button.html(html).prop('disabled', false);
				// Two seconds is time enough to clear every criteria: off again if that did happen.
				ifcf7_sdt.view_buttons($, dt, config, instances);
			}, 2000);
		}).fail(function(xhr){
			button.html(html).prop('disabled', false);
			ifcf7_sdt.view_buttons($, dt, config, instances);
			window.alert(ifcf7_sdt.error(xhr));
		});
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	auto_sync: function($, dt, config, instances){
		var forms = ifcf7_sdt.selected_forms($, config, instances);
		var key = ifcf7_sdt.forms_key(forms);
		if(key === config.ifcf7_sdt_forms_key){
			// Only when the forms being filtered by did change: paging or sorting must not undo
			// whatever columns were picked by hand in the meantime.
			return;
		}
		config.ifcf7_sdt_forms = forms; // Read back by the dropdown of the search builders.
		config.ifcf7_sdt_forms_key = key;
		if(!ifcf7_sdt_settings.autoSync || !config.formColumn){
			return;
		}
		ifcf7_sdt.sync($, dt, config, instances);
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	belongs: function($, column, forms){
		if(!column || !column.formIds || !column.formIds.length || !forms){
			return true; // Not a field of a form, or no form picked: it does belong either way.
		}
		var belongs = false;
		$.each(column.formIds, function(index, id){
			if(-1 !== $.inArray(id, forms)){
				belongs = true;
				return false;
			}
		});
		return belongs;
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	panel_columns: function($, config, search_builder){
		var settings = ifcf7_sdt_settings;
		var group = ' ifcf7-sdt-group-' + search_builder.group + ' ';
		// SearchBuilder resolves this one every time it populates a dropdown, so a function is
		// what makes the fields on offer follow the contact forms being filtered by. The classes
		// are the ones of search_builder.selector, matched without going through the DOM: the
		// header cell of a hidden column is not in there.
		return function(index){
			var column = config.columns[index];
			if(!column){
				return false;
			}
			var classes = ' ' + (column.className || '') + ' ';
			if(-1 === classes.indexOf(group) || -1 !== classes.indexOf(' ifcf7-sdt-no-filter ')){
				return false;
			}
			if(!settings.autoSync || !config.formColumn){
				return true;
			}
			return ifcf7_sdt.belongs($, column, config.ifcf7_sdt_forms);
		};
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	forms_key: function(forms){
		return forms ? forms.slice().sort().join(',') : '*';
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	/**
	 * The forms a criteria of the contact form column does mean, by id. Same rules as
	 * Columns::lookup_contact_forms(): the title, or the id itself for "equals" and "not equals",
	 * which is what the older saved views do carry.
	 */
	form_ids: function($, config, condition, value){
		var ids = [];
		var needle = String(value).toLowerCase();
		$.each(config.formTitles || {}, function(id, title){
			var matches = false;
			title = String(title).toLowerCase();
			switch(condition){
				case '=':
				case '!=':
					matches = (title === needle || String(id) === String(value));
					break;
				case 'contains':
				case '!contains':
					matches = (-1 !== title.indexOf(needle));
					break;
				case 'starts':
				case '!starts':
					matches = (0 === title.indexOf(needle));
					break;
				case 'ends':
				case '!ends':
					matches = (title.length >= needle.length && title.indexOf(needle, title.length - needle.length) !== -1);
					break;
			}
			if(matches){
				ids.push(String(id));
			}
		});
		return ids;
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	select2: function($, node){
		var settings = ifcf7_sdt_settings;
		if(!settings.select2 || !$.fn.select2 || !window.MutationObserver){
			return;
		}
		// Only the value dropdowns: the ones for the data and the condition are emptied and
		// repopulated in place by SearchBuilder, which would leave Select2 showing stale options.
		var enhance = function(){
			// Only the ones already sitting in their final container: SearchBuilder does build the
			// value element first and put it in place afterwards, and Select2 inserts its own
			// container right next to the select, which would not travel along with it.
			node.find('div.dtsb-inputCont select.dtsb-value').each(function(){
				var select = $(this);
				if(select.data('select2')){
					return;
				}
				// Select2 keeps the original select around and syncs the value into it, which is
				// what SearchBuilder reads with children('option:selected'), and it does trigger a
				// native change event, which is what SearchBuilder listens to.
				select.select2({
					theme: 'bootstrap',
					width: '100%',
				});
			});
			// And whatever container did lose its select on the way: Select2 always sits right
			// after the one it belongs to, so anything else is a leftover.
			node.find('span.select2-container').each(function(){
				var container = $(this);
				if(!container.prev('select.select2-hidden-accessible').length){
					container.remove();
				}
			});
		};
		// The value dropdown is built anew every time a condition is picked, and there is no event
		// for that: watching the panel is what catches every one of them. Select2 does mutate the
		// dom itself, but the check above makes a second pass harmless.
		new window.MutationObserver(enhance).observe(node[0], {
			childList: true,
			subtree: true,
		});
		enhance();
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	selected_forms: function($, config, instances){
		var excluded = [];
		var included = [];
		var push = function(list, ids){
			$.each(ids, function(index, id){
				if(-1 === $.inArray(id, list)){
					list.push(id); // Two criteria may well mean the same form.
				}
			});
		};
		var walk = function(details){
			if(!details || !details.criteria){
				return;
			}
			$.each(details.criteria, function(index, criteria){
				if(criteria.criteria){
					walk(criteria); // Nested group.
					return;
				}
				if(criteria.origData !== config.formColumn){
					return;
				}
				var value = (criteria.value && criteria.value.length) ? String(criteria.value[0]) : '';
				if(!value){
					return;
				}
				// The criteria carry titles, so which forms they do mean is resolved here the very
				// same way the backend does it (Columns::lookup_contact_forms).
				var ids = ifcf7_sdt.form_ids($, config, criteria.condition, value);
				if(!ids.length){
					return;
				}
				if(-1 !== $.inArray(criteria.condition, ['=', 'contains', 'starts', 'ends'])){
					push(included, ids);
				} else if(-1 !== $.inArray(criteria.condition, ['!=', '!contains', '!starts', '!ends'])){
					push(excluded, ids);
				}
			});
		};
		$.each(instances, function(index, instance){
			walk(instance.getDetails(true));
		});
		if(included.length){
			return included;
		}
		if(excluded.length){
			return $.grep(config.formIds || [], function(id){
				return -1 === $.inArray(id, excluded);
			});
		}
		return null; // No form picked: every field belongs.
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	sync: function($, dt, config, instances){
		var forms = ifcf7_sdt.selected_forms($, config, instances);
		var columns = {};
		$.each(config.columns, function(index, column){
			columns[column.data] = column;
		});
		dt.columns().every(function(){
			var column = columns[this.dataSrc()];
			if(!column || !column.formIds || !column.formIds.length){
				return; // Not a field of a form: its visibility is nobody else's business.
			}
			this.visible(ifcf7_sdt.belongs($, column, forms), false); // Recalculated once, below.
		});
		// No draw(): every row already carries the data of every column, so there is nothing to
		// fetch again. Only the widths need to be worked out.
		dt.columns.adjust();
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	visible_columns: function(dt){
		var columns = [];
		dt.columns().every(function(){
			if(this.visible()){
				columns.push(this.dataSrc());
			}
		});
		return columns;
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	view: function($, dt, config, instances){
		var settings = ifcf7_sdt_settings;
		if(!dt){
			return;
		}
		var request = function(name){
			return $.ajax({
				contentType: 'application/json; charset=utf-8',
				data: JSON.stringify({
					columns: ifcf7_sdt.visible_columns(dt),
					criteria: ifcf7_sdt.criteria_by_group(instances, config, true),
					form_id: config.form_id || '',
					name: name,
					search: dt.search() || '',
				}),
				headers: {
					'X-WP-Nonce': settings.nonce,
				},
				method: 'POST',
				url: settings.views.url,
			});
		};
		var label = settings.views.update ? settings.views.saveAs : settings.views.text;
		if(!$.fn.modal){ // No Bootstrap javascript around: a plain dialog will do.
			var prompted = (window.prompt(label + ' - ' + settings.text.name) || '').trim();
			if(prompted){
				request(prompted).done(function(response){
					window.location.href = response.url;
				}).fail(function(xhr){
					window.alert(ifcf7_sdt.error(xhr));
				});
			}
			return;
		}
		var modal = ifcf7_sdt.modal($);
		modal.find('.modal-title').text(label); // "Save as" when a view is being looked at.
		var alert = modal.find('.alert');
		var input = modal.find('input');
		var save = modal.find('.ifcf7-sdt-modal-save');
		var submit = function(){
			var name = (input.val() || '').trim();
			if(!name){
				input.trigger('focus');
				return;
			}
			alert.addClass('d-none').text('');
			save.prop('disabled', true);
			request(name).done(function(response){
				window.location.href = response.url;
			}).fail(function(xhr){
				alert.removeClass('d-none').text(ifcf7_sdt.error(xhr));
				save.prop('disabled', false);
			});
		};
		alert.addClass('d-none').text('');
		input.val('').off('keydown').on('keydown', function(e){
			if(13 === e.which){
				e.preventDefault();
				submit();
			}
		});
		save.prop('disabled', false).off('click').on('click', submit);
		modal.off('shown.bs.modal').on('shown.bs.modal', function(){
			input.trigger('focus');
		}).modal('show');
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	errors: function($, table){
		table.off('xhr.ifcf7sdt').on('xhr.ifcf7sdt', function(e, settings, json, xhr){
			if(json || !xhr){
				return;
			}
			// DataTables only alerts that "something" went wrong: the body is where the reason is.
			if(window.console){
				window.console.error('IFCF7 SDT ajax error', xhr.status, xhr.responseText);
			}
		});
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

	error: function(xhr){
		if(xhr.responseJSON && xhr.responseJSON.message){
			return xhr.responseJSON.message;
		}
		return xhr.statusText || 'Error';
	},

	// ~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

};
