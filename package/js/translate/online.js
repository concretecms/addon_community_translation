/* jshint unused:vars, undef:true, jquery:true, browser:true */

(function($, window, undefined) {
'use strict';
var translator, $extra, canApprove, packageID, actions, tokens, i18n, canEditGlossary;

function setBadgeCount(id, n) {
	var $badge = $('#'+id);
	$badge.text(n.toString());
	if (n === 0) {
		$badge.removeClass('active-badge');
	} else {
		$badge.addClass('active-badge');
	}
}
function setTranslatorText(textToSet, full) {
	var $i = translator.currentTranslationView.getCurrentTextInput();
	var currentValue = $i.val();
	if (full) {
		$i.val(textToSet);
	} else if (textToSet !== '') {
		var native = $i[0];
		if ('selectionStart' in native && 'selectionEnd' in native) {
			var before = currentValue.substring(0, native.selectionStart);
			var after = currentValue.substring(native.selectionEnd);
			native.value = before + textToSet + after;
			native.selectionStart = before.length;
			native.selectionEnd = before.length + textToSet.length;
		} else if (window.document.selection && window.document.selection.createRange) {
			native.focus();
			document.selection.createRange().text = textToSet;
		} else {
			$i.val(textToSet);
		}
	}
	$i.trigger('change');
	return $i;
}
function textToHtml(text) {
	text = text ? text.toString() : '';
	var result = '';
	if (text !== '') {
		var $o = $('<div />');
		$.each(text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n'), function(index, line) {
			if (index > 0) {
				result += '<br />';
			}
			result += $o.text(line).html();
		});
	}
	return result;
}
	
function getAjaxError(args) {
	var xhr = args[0] /*, textStatus = args[1]*/, errorThrown = args[2];
	if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
		return xhr.responseJSON.error.message || xhr.responseJSON.error;
	} else {
		return errorThrown;
	}
}

var OtherTranslations = (function() {
	var $parent, others;
	return {
		initialize: function(extra) {
			$parent = $('#comtra_translation-others');
			others = extra.otherTranslations;
			setBadgeCount('comtra_translation-others-count', others.length);
			$parent.find('.comtra_none,.comtra_some').hide();
			if (others.length === 0) {
				$parent.find('.comtra_none').show();
				return;
			}
			$parent.find('.comtra_some').show();
		}
	};
})();

var Comments = (function() {
	var $parent, extractedCount, online;
	function updateCount()
	{
		var f = function(arr) {
			var r = arr.length;
			$.each(arr, function(foo, i) {
				r += f(i.comments);
			});
			return r;
		};
		var tot = extractedCount + f(online);
		setBadgeCount('comtra_translation-comments-count', tot);
		return tot;
	}
	return {
		initialize: function(extra) {
			$parent = $('#comtra_translation-comments');
			var extracted = extra.extractedComments;
			extractedCount = extracted.length;
			online = extra.comments;
			updateCount();
			var $l = $('#comtra_translation-comments-extracted');
			if (extracted.length === 0) {
				$l.hide();
			} else {
				$l.children().not(':first').remove();
				$.each(extracted, function(foo, c) {
					$l.append($('<div class="list-group-item" />').text(c));
				});
				$l.show();
			}
		}
	};
})();

var References = (function() {
	var $parent, references;
	return {
		initialize: function(extra) {
			$parent = $('#comtra_translation-references');
			references = extra.references;
			setBadgeCount('comtra_translation-references-count', references.length);
			$parent.find('.comtra_none,.comtra_some').hide();
			if (references.length === 0) {
				$parent.find('.comtra_none').show();
				return;
			}
			var $some = $parent.find('.comtra_some').empty();
			$.each(references, function(foo, ref) {
				if ($.isArray(ref)) {
					$some.append($('<a class="list-group-item" target="_blank" />')
						.attr('href', ref[0])
						.attr('title', ref[0])
						.text(ref[1])
					);
				} else {
					$some.append($('<div class="list-group-item" />').text(ref));
				}
			});
			$some.show();
		}
	};
})();

var Suggestions = (function() {
	var $parent, suggestions;
	return {
		initialize: function(extra) {
			$parent = $('#comtra_translation-suggestions');
			suggestions = extra.suggestions;
			setBadgeCount('comtra_translation-suggestions-count', suggestions.length);
			$parent.find('.comtra_none,.comtra_some').hide();
			if (suggestions.length === 0) {
				$parent.find('.comtra_none').show();
				return;
			}
			var $some = $parent.find('.comtra_some');
			$some.children().not(':first').remove();
			$.each(suggestions, function() {
				var textToSet = this.translation;
				$some.append($('<a class="list-group-item" href="#" />')
					.append($('<label class="label label-default" />').text(this.source).attr('title', this.source))
					.append($('<br />'))
					.append($('<span />').text(this.translation))
					.on('click', function(e) {
						setTranslatorText(textToSet, true).focus();
						e.preventDefault();
						return false;
					})
				);
			});
			$some.show();
		}
	};
})();

var Glossary = (function() {
	var $parent, editingEntry, ajaxing = false;
	
	function Entry(data) {
		this.data = data;
		$parent.find('.comtra_some')
			.append(this.$dt = $('<dt />'))
			.append(this.$dd = $('<dd />'))
		;
		this.updated();
		Entry.count++;
	}
	Entry.prototype = {
		dispose: function() {
			this.$dd.remove();
			this.$dt.remove();
			Entry.count--;
		},
		updated: function() {
			this.$dt.empty();
			this.$dd.empty();
			this.$dt.text(this.data.term);
			if (this.data.type !== '') {
				var $type;
				this.$dt.append($type = $('<span class="label label-default" />'));
				if (this.data.type in i18n.glossaryTypes) {
					var T = i18n.glossaryTypes[this.data.type];
					$type.text(T.short).attr('title', T.name + '\n' + T.description);
				} else {
					$type.text(this.data.type);
				}
			}
			if (canEditGlossary) {
				var me = this;
				this.$dt.prepend($('<a href="#"><i class="fa fa-pencil-square-o"></i></a>')
					.on('click', function(e) {
						startEdit(me);
						e.preventDefault();
						return false;
					})
				);
			}
			if (this.data.translation === '') {
				this.$dd.append($('<i />').text(i18n.no_translation_available));
			} else {
				var textToAdd = this.data.translation;
				this.$dd.append($('<a href="#" />')
					.text(textToAdd)
					.on('click', function(e) {
						setTranslatorText(textToAdd, false).focus();
						e.preventDefault();
						return false;
					})
				);
			}
			this.$dt.removeAttr('title').removeAttr('data-original-title');
			if (this.data.termComments !== '') {
				this.$dt.attr('title', textToHtml(this.data.termComments));
				this.$dt.tooltip({
					placement: 'right',
					html: true
				}).tooltip('fixTitle');
			}
			this.$dd.removeAttr('title').removeAttr('data-original-title');
			if (this.data.translationComments !== '') {
				this.$dd.attr('title', textToHtml(this.data.translationComments));
				this.$dd.tooltip({
					placement: 'left',
					html: true
				}).tooltip('fixTitle');
			}
		}
	};
	function updateStatus() {
		setBadgeCount('comtra_translation-glossary-count', Entry.count);
		$parent.find('.comtra_none,.comtra_some').hide();
		if (Entry.count === 0) {
			$parent.find('.comtra_some').hide();
			$parent.find('.comtra_none').show();
		} else {
			$parent.find('.comtra_none').hide();
			$parent.find('.comtra_some').show();
		}
	}
	function startEdit(term) {
		if (ajaxing) {
			return;
		}
		editingEntry = term || null;
		var data = (editingEntry === null) ? null : editingEntry.data;
		$('#comtra_gloentry_term').val(data ? data.term : '');
		$('#comtra_gloentry_type').val(data ? data.type : '');
		$('#comtra_gloentry_termComments').val(data ? data.termComments : '');
		$('#comtra_gloentry_translation').val(data ? data.translation : '');
		$('#comtra_gloentry_translationComments').val(data ? data.translationComments : '');
		var $dlg = $('#comtra_translation-glossary-dialog');
		$dlg.find('.btn-danger')[editingEntry === null ? 'hide' : 'show']();
		$dlg.modal('show');
	}
	function addNew() {
		if (ajaxing) {
			return;
		}
		startEdit(null);
	}
	function deleteEditing() {
		if (ajaxing) {
			return;
		}
		$.ajax({
			cache: false,
			data: {ccm_token: tokens.deleteGlossaryTerm, id: editingEntry.data.id},
			dataType: 'json',
			method: 'POST',
			url: actions.deleteGlossaryTerm
		})
		.done(function() {
			editingEntry.dispose();
			updateStatus();
			ajaxing = false;
			$('#comtra_translation-glossary-dialog').modal('hide');
		})
		.fail(function(xhr, textStatus, errorThrown) {
			window.alert(getAjaxError(arguments));
			ajaxing = false;
		});
	}
	function doneEdit() {
		if (ajaxing) {
			return;
		}
		var send = {};
		if (editingEntry === null) {
			send.id = 'new';
		} else {
			send.id = editingEntry.data.id;
		}
		send.term = $.trim($('#comtra_gloentry_term').val());
		if (send.term === '') {
			$('#comtra_gloentry_term').val('').focus();
			return;
		}
		send.type = $('#comtra_gloentry_type').val();
		send.termComments = $.trim($('#comtra_gloentry_termComments').val());
		send.translation = $.trim($('#comtra_gloentry_translation').val());
		send.translationComments = $.trim($('#comtra_gloentry_translationComments').val());
		ajaxing = true;
		$.ajax({
			cache: false,
			data: $.extend({ccm_token: tokens.saveGlossaryTerm}, send),
			dataType: 'json',
			method: 'POST',
			url: actions.saveGlossaryTerm
		})
		.done(function(data) {
			if (editingEntry === null) {
				new Entry(data);
			} else {
				editingEntry.data = data;
				editingEntry.updated();
			}
			ajaxing = false;
			updateStatus();
			$('#comtra_translation-glossary-dialog').modal('hide');
		})
		.fail(function(xhr, textStatus, errorThrown) {
			window.alert(getAjaxError(arguments));
			ajaxing = false;
		});
	}
	return {
		initialize: function(extra) {
			$parent = $('#comtra_translation-glossary');
			$parent.find('.comtra_some').empty();
			Entry.count = 0;
			$.each(extra.glossary, function() {
				new Entry(this);
			});
			updateStatus();
		},
		addNew: addNew,
		deleteEditing: deleteEditing,
		doneEdit: doneEdit,
		canCloseModal: function() {
			return !ajaxing;
		}
	};
})();

function initializeUI()
{
	translator.UI.$container.find('.ccm-translator-col-translations').after($('#comtra_extra').tab());
}
function loadFullTranslation(foo, translation, cb)
{
	var success = true;
	$.ajax({
		cache: false,
		data: {ccm_token: tokens.loadTranslation, translatableID: translation.id, packageID: packageID},
		dataType: 'json',
		method: 'POST',
		url: actions.loadTranslation
	})
	.done(function(data) {
		if (translation.id !== data.id) {
			return;
		}
		if (data.translations.current === null) {
			delete translation.translations;
			translation.isTranslated = false;
			translation.isApproved = false;
		} else {
			translation.translations = data.translations.current.translations;
			translation.isTranslated = true;
			translation.isApproved = data.translations.current.reviewed;
		}
		translation.translationUpdated();
		translation._extra = data;
		translation._extra.otherTranslations = data.translations.others;
		delete translation._extra.translations;
	})
	.fail(function(xhr, textStatus, errorThrown) {
		success = false;
		window.alert(getAjaxError(arguments));
	})
	.always(function() {
		cb(success);
	});
}
function showFullTranslation(foo)
{
	if (!translator.currentTranslationView) {
		$extra.css('visibility', 'hidden');
		return;
	}
	var extra = translator.currentTranslationView.translation._extra;
	OtherTranslations.initialize(extra);
	Comments.initialize(extra);
	References.initialize(extra);
	Suggestions.initialize(extra);
	Glossary.initialize(extra);
	$extra.css('visibility', 'visible');
}
function saveTranslation(translation, postData, cb)
{
	cb('@todo');
}

window.comtraOnlineEditorInitialize = function(options) {
	var height = Math.max(200, $(window).height() - 340);
	$('#comtra_extra>.tab-content').height((height - $('#comtra_extra>.nav-tabs').height()) + 'px');
	packageID = options.packageID || null;
	canApprove = !!options.canApprove;
	actions = options.actions;
	tokens = options.tokens;
	i18n = options.i18n;
	canEditGlossary = !!options.canEditGlossary;
	$extra = $('#comtra_extra');
	translator = window.ccmTranslator.initialize({
		container: '#comtra_translator',
		height: height,
		plurals: options.plurals,
		translations: options.translations,
		approvalSupport: true,
		canModifyApproved: canApprove,
		saveAction: saveTranslation,
		onUILaunched: initializeUI,
		onBeforeActivatingTranslation: loadFullTranslation,
		onCurrentTranslationChanged: showFullTranslation
	});
	if (canEditGlossary) {
		$('#comtra_translation-glossary-dialog')
			.modal({
				show: false
			})
			.find('.btn-primary').on('click', function() {
				Glossary.doneEdit();
			}).end()
			.find('.btn-danger').on('click', function() {
				if (window.confirm(i18n.Are_you_sure)) {
					Glossary.deleteEditing();
				}
			}).end()
			.on('hide.bs.modal', function(e) {
				if (!Glossary.canCloseModal()) {
					e.preventDefault();
					e.stopImmediatePropagation();
					return false;
				}
			})
		;
		$('#comtra_translation-glossary-add').on('click', function(e) {
			Glossary.addNew();
			e.preventDefault();
			return false;
		});
	}
};

})(jQuery, window);
