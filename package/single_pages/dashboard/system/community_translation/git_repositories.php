<?php
defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Package\CommunityTranslation\Src\Git\Repository[] $repositories */

?>
<div class="ccm-dashboard-header-buttons">
	<a href="<?php echo View::url('/dashboard/system/community_translation/git_repositories/details', 'add'); ?>" class="btn btn-primary"><?php echo t('Add Git Repository'); ?></a>
</div>

<?php if (empty($repositories)) { ?>
	<div class="alert alert-info">
		<?php echo t('No Git Repository has been defined.'); ?>
	</div>
<?php } else { ?>
    <table class="table">
    	<thead>
    		<tr>
    			<th><?php echo t('Mnemonic name'); ?></th>
    			<th><?php echo t('Package'); ?></th>
    			<th><?php echo t('URL'); ?></th>
    			<th><?php echo t('Root directory'); ?></th>
    			<th><?php echo t('Tags'); ?></th>
				<th><?php echo t('Dev branches'); ?></th>    			
			</tr>
		</thead>
		<tbody><?php foreach ($repositories as $repository) { ?>
			<tr>
				<td><a href="<?php echo URL::to('/dashboard/system/community_translation/git_repositories/details', $repository->getID()); ?>"><?php echo h($repository->getName()); ?></a></td>
				<td><?php echo ($repository->getPackage() === '') ? ('<i>'.t('concrete5 core').'</i>') : h($repository->getPackage()); ?></td>
				<td><a href="<?php echo h($repository->getURL()) ?>" target="_blank"><?php echo h($repository->getURL()) ?></a></td>
				<td><?php
				    if ($repository->getWebRoot() === '') {
				        ?><i><?php echo tc('Directory', 'none'); ?></i><?php
				    } else {
				        ?><code><?php echo h($repository->getWebRoot()); ?></code><?php
				    }
				?></td>
				<td><?php echo h($repository->getTagsFilterDisplayName()); ?></td>
				<td><?php
				    $db = $repository->getDevBranches();
				    if (empty($db)) {
				        ?><i><?php echo tc('Branch', 'none'); ?></i><?php
				    } else {
				        foreach ($db as $branch => $version) {
				            echo t('Branch %s &rarr; version %s', '<code>'.h($branch).'</code>', '<code>'.h($version).'</code>'), '<br />';
				        }
				    }
				?></td>
			</tr>
		<?php } ?></tbody>
	</table>
<?php } ?>

