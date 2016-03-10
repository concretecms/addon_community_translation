<?php
use Concrete\Package\CommunityTranslation\Src\Service\Access;
use Concrete\Package\CommunityTranslation\Src\Glossary\Entry\Entry as GlossaryEntry;

defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Core\Page\View\PageView $this */
/* @var Concrete\Core\Validation\CSRF\Token $token */

/* @var \Concrete\Package\CommunityTranslation\Src\Locale\Locale $locale */
/* @var \Concrete\Package\CommunityTranslation\Src\Package\Package|null $package */
/* @var bool $canApprove */
/* @var bool $canEditGlossary */
/* @var array $pluralCases */
/* @var array $translations */

?>
<nav class="navbar navbar-inverse">
	<div class="container-fluid">
		<div class="navbar-header">
			<a class="navbar-brand" href="<?php echo URL::to('/translate'); ?>"><?php echo h($headerText); ?></a>
		</div>
	</div>
</nav>

<div class="container-fluid" id="comtra_translator"></div>

<div id="comtra_extra" class="col-md-5">
	<ul class="nav nav-tabs">
		<li class="active"><a href="#comtra_translation-others" role="tab" data-toggle="tab"><?php echo t('Other translations'); ?> <span class="badge" id="comtra_translation-others-count"></span></a></li>
		<li><a href="#comtra_translation-comments" role="tab" data-toggle="tab"><?php echo t('Comments'); ?> <span class="badge" id="comtra_translation-comments-count"></span></a></li>
		<li><a href="#comtra_translation-references" role="tab" data-toggle="tab"><?php echo t('References'); ?> <span class="badge" id="comtra_translation-references-count"></span></a></li>
		<li><a href="#comtra_translation-suggestions" role="tab" data-toggle="tab"><?php echo t('Suggestions'); ?> <span class="badge" id="comtra_translation-suggestions-count"></span></a></li>
		<li><a href="#comtra_translation-glossary" role="tab" data-toggle="tab"><?php echo t('Glossary'); ?> <span class="badge" id="comtra_translation-glossary-count"></span></a></li>
	</ul>
	<div class="tab-content">
		<div role="tabpanel" class="tab-pane active" role="tabpanel" id="comtra_translation-others">
			<div class="alert alert-info comtra_none">
				<?php echo t('No other translations found.')?>
			</div>
			<table class="comtra_some table table-striped">
				<thead>
					<tr>
						<th style="width: 1px"><?php echo t('Date'); ?></th>
						<th><?php echo t('Translation'); ?></th>
						<th style="width: 1px"><?php echo t('Action'); ?></th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
		<div role="tabpanel" class="tab-pane" role="tabpanel" id="comtra_translation-comments">
			<div class="alert alert-info comtra_none">
				<?php echo t('No comments found for this string.')?>
			</div>
			<div class="list-group" id="comtra_translation-comments-extracted"><div class="list-group-item active"><?php echo t('Extracted comments'); ?></div></div>
			<div class="list-group" id="comtra_translation-comments-online"><div class="list-group-item active"><?php echo t('Translators comments'); ?></div></div>
			<div style="text-align: right">
				<a href="#" class="btn btn-primary btn-sm" id="comtra_translation-comments-add"><?php echo t('New comment'); ?></a>
			</div>
		</div>
		<div role="tabpanel" class="tab-pane" role="tabpanel" id="comtra_translation-references">
			<div class="alert alert-info comtra_none">
				<?php echo t('No references found for this string.')?>
			</div>
			<div class="comtra_some"></div>
			<div style="text-align: right; margin-top: 20px">
				<a href="#" class="btn btn-primary btn-sm" id="comtra_translation-references-showallplaces"><?php echo t('Show all the places where this string is used'); ?></a>
			</div>
		</div>
		<div role="tabpanel" class="tab-pane" role="tabpanel" id="comtra_translation-suggestions">
			<div class="alert alert-info comtra_none">
				<?php echo t('No similar translations found for this string.')?>
			</div>
			<div class="comtra_some list-group"><div class="list-group-item active"><?php echo t('Similar translations'); ?></div></div>
		</div>
		<div role="tabpanel" class="tab-pane" role="tabpanel" id="comtra_translation-glossary">
			<div class="alert alert-info comtra_none">
				<?php echo t('No glossary terms found for this string.')?>
			</div>
			<dl class="comtra_some dl-horizontal"></dl>
			<?php if($canEditGlossary) { ?>
				<div style="text-align: right">
					<a href="#" class="btn btn-primary btn-sm" id="comtra_translation-glossary-add"><?php echo t('New term'); ?></a>
				</div>
			<?php } ?>
		</div>
	</div>
</div>

<div id="comtra_translation-comments-dialog" class="modal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
				<h4 class="modal-title"><?php echo t('Translation comment'); ?></h4>
			</div>
			<div class="modal-body">
				<form onsubmit="return false">
					<div class="form-group" id="comtra_editcomment-visibility">
						<label><?php echo t('Comment visibility'); ?></label>
    					<div class="radio">
    						<label>
    							<input type="radio" name="comtra_editcomment-visibility" value="locale" />
    							<?php echo t('This is a comment only for %s', $locale->getDisplayName()); ?>
    						</label>
    					</div>
    					<div class="radio">
    						<label>
    							<input type="radio" name="comtra_editcomment-visibility" value="global" />
    							<?php echo t('This is a comment for all languages'); ?>
    						</label>
    					</div>
    				</div>
					<div class="form-group">
						<div class="pull-right small"><a href="http://commonmark.org/help/" target="_blank"><?php echo t('Markdown syntax'); ?></a></div>
						<label for="comtra_editcomment"><?php echo t('Comment'); ?></label>
						<textarea class="form-control" id="comtra_editcomment"></textarea>
					</div>
					<div class="form-group">
						<label for="comtra_editcomment_render"><?php echo t('Preview'); ?></label>
						<div id="comtra_editcomment_render"></div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo t('Cancel'); ?></button>
				<button type="button" class="btn btn-danger" id="comtra_translation-glossary-delete"><?php echo t('Delete'); ?></button>
				<button type="button" class="btn btn-primary"><?php echo t('Save'); ?></button>
			</div>
		</div>
	</div>
</div>

<div id="comtra_allplaces-dialog" class="modal" tabindex="-1">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
				<h4 class="modal-title"><?php echo t('String usage'); ?></h4>
			</div>
			<div class="modal-body">
				<div class="list-group"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary" data-dismiss="modal"><?php echo t('Close'); ?></button>
			</div>
		</div>
	</div>
</div>

<?php if ($canEditGlossary) { ?>
	<div id="comtra_translation-glossary-dialog" class="modal" tabindex="-1">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
					<h4 class="modal-title"><?php echo t('Glossary Term'); ?></h4>
				</div>
				<div class="modal-body">
					<form class="form-horizontal" onsubmit="return false">
						<fieldset>
							<legend><?php echo t('Info shared with all languages'); ?></legend>
							<div class="form-group">
								<label for="comtra_gloentry_term" class="col-sm-2 control-label"><?php echo t('Term'); ?></label>
								<div class="col-sm-10">
									<div class="input-group">
										<input type="text" class="form-control" id="comtra_gloentry_term" maxlength="255" />
										<span class="input-group-addon" id="basic-addon2">*</span>
									</div>
								</div>
							</div>
							<div class="form-group">
								<label for="comtra_gloentry_type" class="col-sm-2 control-label"><?php echo t('Type'); ?></label>
								<div class="col-sm-10">
									<select class="form-control" id="comtra_gloentry_type">
										<option value=""><?php echo tc('Type', 'none'); ?></option>
										<?php foreach (GlossaryEntry::getTypesInfo() as $typeHandle => $typeInfo) { ?>
											<option value="<?php echo h($typeHandle); ?>"><?php echo h($typeInfo['name']); ?></option>
										<?php } ?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label for="comtra_gloentry_termComments" class="col-sm-2 control-label"><?php echo t('Comments'); ?></label>
								<div class="col-sm-10">
									<textarea class="form-control" id="comtra_gloentry_termComments" style="resize: vertical"></textarea>
								</div>
							</div>
						</fieldset>
						<fieldset>
							<legend><?php echo t('Info only for %s', $locale->getDisplayName()); ?></legend>
							<div class="form-group">
								<label for="comtra_gloentry_translation" class="col-sm-2 control-label"><?php echo t('Translation'); ?></label>
								<div class="col-sm-10">
									<input type="text" class="form-control" id="comtra_gloentry_translation" />
								</div>
							</div>
							<div class="form-group">
								<label for="comtra_gloentry_translationComments" class="col-sm-2 control-label"><?php echo t('Comments'); ?></label>
								<div class="col-sm-10">
									<textarea class="form-control" id="comtra_gloentry_translationComments" style="resize: vertical"></textarea>
								</div>
							</div>
						</fieldset>
					</form>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal"><?php echo t('Cancel'); ?></button>
					<button type="button" class="btn btn-danger" id="comtra_translation-glossary-delete"><?php echo t('Delete'); ?></button>
					<button type="button" class="btn btn-primary"><?php echo t('Save'); ?></button>
				</div>
			</div>
		</div>
	</div>
<?php } ?>

<script>$(document).ready(function() {

window.ccmTranslator.configureFrontend({
	colOriginal: 'col-md-3',
	colTranslations: 'col-md-4'
});
comtraOnlineEditorInitialize({
	packageID: <?php echo $package ? $package->getID() : 'null'; ?>,
	canApprove: <?php echo $canApprove ? 'true' : 'false'; ?>,
	pluralRuleByIndex: <?php echo json_encode(array_keys($pluralCases)); ?>,
	plurals: <?php echo json_encode($pluralCases); ?>,
	translations: <?php echo json_encode($translations); ?>,
	canEditGlossary: <?php echo $canEditGlossary ? 'true' : 'false'; ?>,
	actions: {
		saveComment: <?php echo json_encode((string) $this->action('save_comment', $locale->getID())); ?>,
		deleteComment: <?php echo json_encode((string) $this->action('delete_comment', $locale->getID())); ?>,
		loadAllPlaces: <?php echo json_encode((string) $this->action('load_all_places', $locale->getID())); ?>,
		processTranslation: <?php echo json_encode((string) $this->action('process_translation', $locale->getID())); ?>,
		<?php if ($canEditGlossary) { ?>
			saveGlossaryTerm: <?php echo json_encode((string) $this->action('save_glossary_term', $locale->getID())); ?>,
			deleteGlossaryTerm: <?php echo json_encode((string) $this->action('delete_glossary_term', $locale->getID())); ?>,
		<?php } ?>
		loadTranslation: <?php echo json_encode((string) $this->action('load_translation', $locale->getID())); ?>
	},
	tokens: {
		saveComment: <?php echo json_encode($token->generate('comtra-save-comment'.$locale->getID())); ?>,
		deleteComment: <?php echo json_encode($token->generate('comtra-delete-comment'.$locale->getID())); ?>,
		loadAllPlaces: <?php echo json_encode($token->generate('comtra-load-all-places'.$locale->getID())); ?>,
		processTranslation: <?php echo json_encode($token->generate('comtra-process-translation'.$locale->getID())); ?>,
		<?php if ($canEditGlossary) { ?>
			saveGlossaryTerm: <?php echo json_encode($token->generate('comtra-save-glossary-term'.$locale->getID())); ?>,
			deleteGlossaryTerm: <?php echo json_encode($token->generate('comtra-delete-glossary-term'.$locale->getID())); ?>,
		<?php } ?>
		loadTranslation: <?php echo json_encode($token->generate('comtra-load-translation'.$locale->getID())); ?>
	},
	i18n: {
		glossaryTypes: <?php echo json_encode(GlossaryEntry::getTypesInfo()); ?>,
		no_translation_available: <?php echo json_encode(t('no translation available')); ?>,
		Are_you_sure: <?php echo json_encode(t('Are you sure?')); ?>,
		by: <?php echo json_encode(tc('Prefix of an author name', 'by')); ?>,
		Reply: <?php echo json_encode(t('Reply')); ?>,
		Edit: <?php echo json_encode(t('Edit')); ?>,
		Approve: <?php echo json_encode(t('Approve')); ?>,
		Deny: <?php echo json_encode(t('Deny')); ?>,
		Use_this: <?php echo json_encode(tc('Translation', 'Use this')); ?>,
		Comments: <?php echo json_encode(t('Comments')); ?>,
		References: <?php echo json_encode(t('References')); ?>,
		Unused_string: <?php echo json_encode(t('This string is not used in any package.')); ?>,
		Waiting_approval: <?php echo json_encode(t('Waiting approval')); ?>,
		pluralRuleNames: <?php echo json_encode(array(
	        'zero' => tc('PluralCase', 'Zero'),
	        'one' => tc('PluralCase', 'One'),
	        'two' => tc('PluralCase', 'Two'),
	        'few' => tc('PluralCase', 'Few'),
	        'many' => tc('PluralCase', 'Many'),
	        'other' => tc('PluralCase', 'Other'),
		)); ?>
	}
});

});
</script>
