/* jshint unused:vars, undef:true, jquery:true, browser:true */

(function($, window, undefined) {
'use strict';
var $extra, canApprove, packageID, actions, tokens, i18n;

function setBadgeCount(id, n) {
	var $badge = $('#'+id);
	$badge.text(n.toString());
	if (n === 0) {
		$badge.removeClass('active-badge');
	} else {
		$badge.addClass('active-badge');
	}
}
function setTranslatorText(translator, textToSet, full) {
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


var OtherTranslations = (function() {
	var translator, $parent, others;
	return {
		initialize: function(currentTranslator, extra) {
			translator = currentTranslator;
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
	var translator, $parent, extracted, online;
	function updateCount()
	{
		var f = function(arr) {
			var r = arr.length;
			$.each(arr, function(_, i) {
				r += f(i.comments);
			});
			return r;
		};
		var tot = extracted.length + f(online);
		setBadgeCount('comtra_translation-comments-count', tot);
		return tot;
	}
	return {
		initialize: function(currentTranslator, extra) {
			translator = currentTranslator;
			$parent = $('#comtra_translation-comments');
			extracted = extra.extractedComments;
			online = extra.comments;
			updateCount();
			var $l = $('#comtra_translation-comments-extracted');
			if (extracted.length === 0) {
				$l.hide();
			} else {
				$l.children().not(':first').remove();
				$.each(extracted, function(_, c) {
					$l.append($('<div class="list-group-item" />').text(c));
				});
				$l.show();
			}
		}
	};
})();

var References = (function() {
	var translator, $parent, references;
	return {
		initialize: function(currentTranslator, extra) {
			translator = currentTranslator;
			$parent = $('#comtra_translation-references');
			references = extra.references;
			setBadgeCount('comtra_translation-references-count', references.length);
			$parent.find('.comtra_none,.comtra_some').hide();
			if (references.length === 0) {
				$parent.find('.comtra_none').show();
				return;
			}
			var $some = $parent.find('.comtra_some').empty();
			$.each(references, function(_, ref) {
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
	var translator, $parent, suggestions;
	return {
		initialize: function(currentTranslator, extra) {
			translator = currentTranslator;
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
						setTranslatorText(translator, textToSet, true).focus();
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
	var translator, $parent, glossary;
	return {
		initialize: function(currentTranslator, extra) {
			translator = currentTranslator;
			$parent = $('#comtra_translation-glossary');
			glossary = extra.glossary;
			setBadgeCount('comtra_translation-glossary-count', glossary.length);
			$parent.find('.comtra_none,.comtra_some').hide();
			if (glossary.length === 0) {
				$parent.find('.comtra_none').show();
				return;
			}
			var $some = $parent.find('.comtra_some').empty();
			$.each(glossary, function() {
				var $dt, $dd;
				$some
					.append($dt = $('<dt />').text(this.term))
					.append($dd = $('<dd />'))
				;
				if (this.type !== '') {
					var $type;
					$dt.append($type = $('<span class="label label-default" />'));
					if (this.type in i18n.glossaryTypes) {
						var T = i18n.glossaryTypes[this.type];
						$type.text(T.short).attr('title', T.name + '\n' + T.description);
					} else {
						$type.text(this.type);
					}
				}
				if (this.translation !== '') {
					var textToAdd = this.translation;
					$dd.append($('<a href="#" />')
						.text(this.translation)
						.on('click', function(e) {
							setTranslatorText(translator, textToAdd, false).focus();
							e.preventDefault();
							return false;
						})
					);
				}
			});
			$some.show();
		}
	};
})();

function initializeUI(translator)
{
	translator.UI.$container.find('.ccm-translator-col-translations').after($('#comtra_extra').tab());
}
function loadFullTranslation(translator, translation, cb)
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
		if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
			window.alert(xhr.responseJSON.error.message || xhr.responseJSON.error);
		} else {
			window.alert(errorThrown);
		}
	})
	.always(function() {
		cb(success);
	});
}

function showFullTranslation(translator)
{
	if (!translator.currentTranslationView) {
		$extra.css('visibility', 'hidden');
		return;
	}
	var extra = translator.currentTranslationView.translation._extra;
	OtherTranslations.initialize(translator, extra);
	Comments.initialize(translator, extra);
	References.initialize(translator, extra);
	Suggestions.initialize(translator, extra);
	Glossary.initialize(translator, extra);
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
	$extra = $('#comtra_extra');
	window.ccmTranslator.initialize({
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
};

})(jQuery, window);
