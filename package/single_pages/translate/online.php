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
<div class="container-fluid" id="comtra_translator">
	<div class="page-header">
		<h1>Translating <?php echo $package->getHandle() ?: 'concrete5 core', ' ', $package->getVersionDisplayName(); ?> in <?php echo $locale->getDisplayName(); ?></h1>
	</div>
</div>
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
	    	<div class="comtra_none"><?php echo t('No other translations found.')?></div>
	    	<div class="comtra_some">@todo</div>
		</div>
    	<div role="tabpanel" class="tab-pane" role="tabpanel" id="comtra_translation-comments">
    		<div class="list-group" id="comtra_translation-comments-extracted"><div class="list-group-item active"><?php echo t('Extracted comments'); ?></div></div>
    		<div class="list-group" id="comtra_translation-comments-online"><div class="list-group-item active"><?php echo t('Translators comments'); ?></div></div>
    	</div>
    	<div role="tabpanel" class="tab-pane" role="tabpanel" id="comtra_translation-references">
    		<div class="comtra_none"><?php echo t('No references found for this string.')?></div>
			<div class="comtra_some">@todo</div>
    	</div>
    	<div role="tabpanel" class="tab-pane" role="tabpanel" id="comtra_translation-suggestions">
    		<div class="comtra_none"><?php echo t('No similar translations found for this string.')?></div>
			<div class="comtra_some list-group"><div class="list-group-item active"><?php echo t('Similar translations'); ?></div></div>
    	</div>
    	<div role="tabpanel" class="tab-pane" role="tabpanel" id="comtra_translation-glossary">
    		<div class="comtra_none"><?php echo t('No glossary terms found for this string.')?></div>
			<dl class="comtra_some dl-horizontal"></dl>
    		<?php if($canEditGlossary) { ?>
    			<div style="text-align: right">
    				<a href="#" class="btn btn-default"><?php echo t('New term'); ?></a>
    			</div>
    		<?php } ?>
		</div>
    </div>
</div>
<script>$(document).ready(function() {

window.ccmTranslator.configureFrontend({
	colOriginal: 'col-md-3',
	colTranslations: 'col-md-4'
});
comtraOnlineEditorInitialize({
	packageID: <?php echo $package ? $package->getID() : 'null'; ?>,
	canApprove: <?php echo $canApprove ? 'true' : 'false'; ?>,
	plurals: <?php echo json_encode($pluralCases); ?>,
	translations: <?php echo json_encode($translations); ?>,
	actions: {
		loadTranslation: <?php echo json_encode((string) $this->action('load_translation', $locale->getID())); ?>
	},
	tokens: {
		loadTranslation: <?php echo json_encode($token->generate('comtra-load-translation'.$locale->getID())); ?>
	},
	i18n: {
		glossaryTypes: <?php echo json_encode(GlossaryEntry::getTypesInfo()); ?>
	}
});

});
</script>
