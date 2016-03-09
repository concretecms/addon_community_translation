<?php
defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Package\CommunityTranslation\Src\Git\Repository;

/* @var Concrete\Core\Page\View\PageView $this */
/* @var Concrete\Core\Validation\CSRF\Token $token */
/* @var Concrete\Core\Form\Service\Form $form */

/* @var Repository|null $repository */

if ($repository !== null) {
    ?>
    <form id="comtra-delete" method="post" class="hide" action="<?php echo $view->action('deleteRepository', $repository->getID()); ?>">
		<?php $token->output('comtra-repository-delete'.$repository->getID()); ?>
		<input type="hidden" name="repositoryID" value="<?php echo $repository->getID(); ?>" />
	</form>
	<?php
}
?>
<form method="post" class="form-horizontal" action="<?php echo $view->action('save'); ?>" onsubmit="if (this.already) return false; this.already = true">

	<?php echo $token->output('comtra-repository-save'); ?>
	<input type="hidden" name="repositoryID" value="<?php echo ($repository === null) ? 'new' : $repository->getID(); ?>" />

	<div class="row">
		<div class="form-group">
			<label class="control-label col-sm-3"><?php echo t('Mnemonic name'); ?></label>
			<div class="col-sm-7">
				<div class="input-group">
					<?php echo $form->text('name', ($repository === null) ? '' : $repository->getName(), array('required' => 'required', 'maxlength' => 100)); ?>
					<span class="input-group-addon"><i class="fa fa-asterisk"></i></span>
				</div>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="form-group">
			<label class="control-label col-sm-3"><?php echo t('Package handle'); ?></label>
			<div class="col-sm-7">
				<?php echo $form->text('package', ($repository === null) ? '' : $repository->getPackage(), array('maxlength' => 64, 'placeholder' => t('Leave empty for concrete5 core'))); ?>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="form-group">
			<label class="control-label col-sm-3"><?php echo t('Repository URL'); ?></label>
			<div class="col-sm-7">
				<div class="input-group">
					<?php echo $form->url('url', ($repository === null) ? '' : $repository->getURL(), array('required' => 'required', 'maxlength' => 255)); ?>
					<span class="input-group-addon"><i class="fa fa-asterisk"></i></span>
				</div>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="form-group">
			<div class="col-sm-3 control-label"><label class="launch-tooltip" data-html="true" title="<?php echo t('This is the path to the directory in the git repository that contains the translatable strings.<br />For concrete5 core it could be %s', '<code>web</code>'); ?>"><?php echo t('Root directory'); ?></label></div>
			<div class="col-sm-7">
				<?php echo $form->text('webroot', ($repository === null) ? '' : $repository->getWebRoot(), array('maxlength' => 255)); ?>
			</div>
		</div>
	</div>

	<div class="row">
		<div class="form-group">
			<div class="col-sm-3 control-label"><label class="launch-tooltip" title="<?php echo t('The tags that satisfy this criteria will be fetched just once.'); ?>"><?php echo t('Parse tags'); ?></label></div>
			<div class="col-sm-7">
				<?php
				$ptx = ($repository === null) ? null : $repository->getTagsFilterExpanded();
				switch (($ptx === null) ? '' : implode('', $ptx)) {
				    case '<0':
				        $parsetags = 1;
				        break;
				    case '':
				        $parsetags = 2;
				        break;
				    default:
				        $parsetags = 3;
				        break;
				}
				?>
				<label><?php echo $form->radio('parsetags', '1', $parsetags === 1)?> <?php echo tc('Tags', 'none'); ?></label><br />
				<label><?php echo $form->radio('parsetags', '2', $parsetags === 2)?> <?php echo tc('Tags', 'all'); ?></label><br />
				<label><?php echo $form->radio('parsetags', '3', $parsetags === 3)?> <?php echo tc('Tags', 'filter'); ?></label>
    			<span id="comtra-parsetags-filter" style="display: none">
    				<?php echo $form->select('parsetagsOperator', array('&lt;' => '&lt;', '&lt;=' => '&le;', '=' => '=', '&gt;=' => '&ge;', '>' => '&gt;', ), h(($ptx === null) ? '>=' : $ptx['operator']), array('style' => 'width: 60px; display: inline')); ?>
    				<?php echo $form->text('parsetagsVersion', ($ptx === null) ? '1.0' : $ptx['version'], array('style' => 'width: 100px; display: inline')); ?>
    			</span>
    		</div>
		</div>
	</div>

	<div class="row">
		<div class="form-group">
			<div class="col-sm-3 control-label"><label class="launch-tooltip" title="<?php echo t('These branches should be fetched periodically in order to extract new strings while the development progresses'); ?>"><?php echo t('Development branches'); ?></label></div>
			<div class="col-sm-7" id="comtra-devbranches"></div>
		</div>
	</div>

    <div class="ccm-dashboard-form-actions-wrapper">
    	<div class="ccm-dashboard-form-actions">
    		<a href="<?php echo URL::to('/dashboard/system/community_translation/git_repositories'); ?>" class="btn btn-default pull-left"><?php echo t('Cancel'); ?></a>
    		<div class="pull-right">
    			<?php if ($repository !== null) { ?>
	    			<a href="#" id="comtra-delete-btn" class="btn btn-danger"><?php echo t('Delete'); ?></a>
	    		<?php } ?>
    			<input type="submit" class="btn btn-primary ccm-input-submit" value="<?php echo ($repository === null) ? t('Create') : t('Update'); ?>">
    		</div>
    	</div>
    </div>
</form>
<div class="hide" id="comtra-devbranches-template">
	<div class="comtra-devbranches-pair" style="white-space: nowrap">
		<?php echo t('Branch'); ?> <input type="text" name="branch[]" />
		&rArr;
		<?php echo t('Version'); ?> <input type="text" name="version[]" />
		<a href="#" onclick="$(this).closest('div').remove();$('#comtra-devbranches').trigger('change'); return false;"><i class="fa fa-times" style="color: red"></i></a>
	</div>
</div>
<script>
$(document).ready(function() {

<?php if ($repository !== null) { ?>
    $('a#comtra-delete-btn').on('click', function(e) {
    	if (window.confirm(<?php echo json_encode(t('Are you sure?')); ?>)) {
    		$('form#comtra-delete').submit();
    	}
    	e.preventDefault();
    	return false;
    });
<?php } ?>
$('input[name="parsetags"]').on('change', function() {
	var f = $('input[name="parsetags"]:checked').val() === '3';
	$('#comtra-parsetags-filter')[f ? 'show' : 'hide']('fast');
	if (f) {
 		$('#parsetagsVersion').attr('required', 'required').attr('pattern', '[0-9]+(\.[0-9]+)*');
	} else {
		$('#parsetagsVersion').removeAttr('required').removeAttr('pattern');
	}
}).trigger('change');
function addDevBranchPair(branch, version) {
	var $clone = $($('#comtra-devbranches-template').html());
	var $texts = $clone.find('input');
	$texts[0].value = branch;
	$texts[1].value = version;
	$('#comtra-devbranches').append($clone);
}
<?php
$branches = $this->post('branch');
$branches = is_array($branches) ? array_values($branches) : array();
$versions = $this->post('version');
$versions = is_array($versions) ? array_values($versions) : array();
if (!empty($branches) && count($branches) === count($versions)) {
    foreach (array_keys($branches) as $i) {
        ?>addDevBranchPair(<?php echo json_encode($branches[$i]); ?>, <?php echo json_encode($versions[$i]); ?>);<?php
    }
}
elseif ($repository !== null) {
    foreach ($repository->getDevBranches() as $branch => $version) {
        ?>addDevBranchPair(<?php echo json_encode($branch); ?>, <?php echo json_encode($version); ?>);<?php
    }
}
?>
$('#comtra-devbranches').on('change', function() {
	var someEmpty = false;
	$('#comtra-devbranches .comtra-devbranches-pair').each(function() {
		var $texts = $(this).find('input');
		if ($.trim($texts[0].value) === '' && $.trim($texts[1].value) === '') {
			$texts.removeAttr('required');
			someEmpty = true;
		} else {
			$texts.attr('required', 'required');
		}
	});
	if (!someEmpty) {
		addDevBranchPair('', '');
	}
}).trigger('change');

});
</script>