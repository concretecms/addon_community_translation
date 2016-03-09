<?php
defined('C5_EXECUTE') or die('Access Denied.');

use Concrete\Package\CommunityTranslation\Src\Service\Access;

/* @var Concrete\Core\Page\View\PageView $this */
/* @var Concrete\Core\Validation\CSRF\Token $token */
/* @var Concrete\Core\Localization\Service\Date $dh */

/* @var array $globalAdmins */
/* @var array $admins */
/* @var array $translators */
/* @var array $aspiring */

$uh = Core::make('community_translation/user');
/* @var Concrete\Package\CommunityTranslation\Src\Service\User $uh */

id(new Area('Opening'))->display($c);

?><h1><?php echo t('%s Translation Team', $locale->getDisplayName()); ?></h1><?php

if (!empty($globalAdmins)) {
    ?>
    <div class="well">
    	<h4><?php echo t('Maintainers'); ?></h4>
    	<?php
    	foreach ($globalAdmins as $i => $u) {
    	    if ($i > 0) {
    	        echo ', ';
    	    }
    	    echo $uh->format($u['ui']);
    	}
    ?>
    </div>
    <?php
}
?>

<div class="panel panel-default">
	<div class="panel-heading"><h3><?php echo t('Team Coordinators'); ?></h3></div>
	<div class="panel-body">
		<?php if (empty($admins)) { ?>
			<p><?php echo t('No coordinators so far'); ?></p>
		<?php } else { ?>
			<ul class="list-group">
				<?php foreach ($admins as $u ) { ?>
					<li class="list-group-item">
						<?php if ($access >= Access::GLOBAL_ADMIN) { ?>
							<div class="pull-right">
    							<form style="display: inline" method="POST" action="<?php echo $this->action('change_access', $locale->getID(), $u['ui']->getUserID(), Access::TRANSLATE); ?>">
    								<?php $token->output('change_access'.$locale->getID().'#'.$u['ui']->getUserID().':'.Access::TRANSLATE); ?>
    								<input type="submit" class="btn btn-info" value="<?php echo t('Set as translator'); ?>" />
    							</form>
    							<form style="display: inline" method="POST" action="<?php echo $this->action('change_access', $locale->getID(), $u['ui']->getUserID(), Access::NONE); ?>">
    								<?php $token->output('change_access'.$locale->getID().'#'.$u['ui']->getUserID().':'.Access::NONE); ?>
    								<input type="submit" class="btn btn-info" value="<?php echo t('Expel'); ?>" />
    							</form>
    						</div>
						<?php } ?>
						<?php echo $uh->format($u['ui']); ?>
						<div class="text-muted"><?php echo t('Coordinator since: %s', $dh->formatPrettyDateTime($u['since'], true)); ?></div>
					</li>
				<?php } ?>
			</ul>
		<?php } ?>
	</div>
</div>

<div class="panel panel-default">
	<div class="panel-heading"><h3><?php echo t('Translators'); ?></h3></div>
	<div class="panel-body">
		<?php if (empty($translators)) { ?>
			<p><?php echo t('No translators so far'); ?></p>
		<?php } else { ?>
			<ul class="list-group">
				<?php foreach ($translators as $u ) { ?>
					<li class="list-group-item">
						<?php if ($access >= Access::ADMIN) { ?>
							<div class="pull-right">
    							<form style="display: inline" method="POST" action="<?php echo $this->action('change_access', $locale->getID(), $u['ui']->getUserID(), Access::ADMIN); ?>">
    								<?php $token->output('change_access'.$locale->getID().'#'.$u['ui']->getUserID().':'.Access::ADMIN); ?>
    								<input type="submit" class="btn btn-info" value="<?php echo t('Set as coordinator'); ?>" />
    							</form>
    							<form style="display: inline" method="POST" action="<?php echo $this->action('change_access', $locale->getID(), $u['ui']->getUserID(), Access::NONE); ?>">
    								<?php $token->output('change_access'.$locale->getID().'#'.$u['ui']->getUserID().':'.Access::NONE); ?>
    								<input type="submit" class="btn btn-info" value="<?php echo t('Expel'); ?>" />
    							</form>
    						</div>
						<?php } ?>
						<?php echo $uh->format($u['ui']); ?>
						<div class="text-muted"><?php echo t('Translator since: %s', $dh->formatPrettyDateTime($u['since'], true)); ?></div>
					</li>
				<?php } ?>
			</ul>
		<?php } ?>
	</div>
</div>

<?php if (!empty($aspiring)) { ?>
    <div class="panel panel-default">
    	<div class="panel-heading"><h3><?php echo t('Join Requests'); ?></h3></div>
    	<div class="panel-body">
    		<ul class="list-group">
    			<?php foreach ($aspiring as $u ) { ?>
    				<li class="list-group-item">
						<?php if ($access >= Access::ADMIN) { ?>
							<div class="pull-right">
								<form style="display: inline" method="POST" action="<?php echo $this->action('answer', $locale->getID(), $u['ui']->getUserID(), 1); ?>">
									<?php $token->output('comtra_answer'.$locale->getID().'#'.$u['ui']->getUserID().':1'); ?>
									<input type="submit" class="btn btn-info" value="<?php echo t('Approve'); ?>" />
								</form>
								<form style="display: inline" method="POST" action="<?php echo $this->action('answer', $locale->getID(), $u['ui']->getUserID(), 0); ?>">
									<?php $token->output('comtra_answer'.$locale->getID().'#'.$u['ui']->getUserID().':0'); ?>
									<input type="submit" class="btn btn-danger" value="<?php echo t('Deny'); ?>" />
								</form>
							</div>
						<?php } ?>
						<?php echo $uh->format($u['ui']); ?>
						<div class="text-muted"><?php echo t('Request date: %s', $dh->formatPrettyDateTime($u['since'], true)); ?></div>
					</li>
				<?php } ?>
			</ul>
    	</div>
    </div>
<?php } ?>


<div class="pull-right"><?php
    switch ($access) {
        case Access::NOT_LOGGED_IN:
            ?><a href="#" class="btn btn-default" onclick="<?php echo h('window.alert('.json_encode(t('You must sign-in in order to join this translation group.')).'); return false'); ?>"><?php echo t('Join this team'); ?></a><?php
            break;
	    case Access::NONE:
	        ?>
	        <form style="display: inline" method="POST" action="<?php echo $this->action('join', $locale->getID()); ?>">
	        	<?php $token->output('comtra_join'.$locale->getID()); ?>
	        	<input type="submit" class="btn btn-info" value="<?php echo t('Join this team'); ?>" />
	        </form>
	        <?php
	        break;
        case Access::ASPRIRING:
            ?>
            <form style="display: inline" method="POST" action="<?php echo $this->action('leave', $locale->getID()); ?>">
            	<?php $token->output('comtra_leave'.$locale->getID()); ?>
            	<input type="submit" class="btn btn-danger" value="<?php echo t('Cancel join request'); ?>" />
            </form>
            <?php
            break;
        case Access::TRANSLATE:
        case Access::ADMIN:
            ?>
            <form style="display: inline" method="POST" action="<?php echo $this->action('leave', $locale->getID()); ?>">
            	<?php $token->output('comtra_leave'.$locale->getID()); ?>
                <input type="submit" class="btn btn-danger" value="<?php echo t('Leave this group'); ?>" />
            </form>
            <?php
            break;
	}
	if ($access >= Access::GLOBAL_ADMIN) {
    	?>
    	<form style="display: inline" method="POST" action="<?php echo $this->action('delete', $locale->getID()); ?>">
    		<?php $token->output('comtra_delete'.$locale->getID()); ?>
    		<input type="submit" class="btn btn-danger" value="<?php echo t('Delete'); ?>" />
    	</form>
    	<?php
	}
	?>
	<a class="btn btn-default" href="<?php echo URL::to('/teams') ?>"><?php echo t('Back to Team list'); ?></a>
</div><?php

id(new Area('Closing'))->display($c);
