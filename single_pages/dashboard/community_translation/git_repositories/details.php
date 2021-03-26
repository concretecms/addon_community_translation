<?php
use CommunityTranslation\Entity\Package\Version;

defined('C5_EXECUTE') or die('Access Denied.');

/* @var Concrete\Core\Page\View\PageView $this */
/* @var Concrete\Core\Page\View\PageView $view */
/* @var Concrete\Core\Validation\CSRF\Token $token */
/* @var Concrete\Core\Form\Service\Form $form */

/* @var CommunityTranslation\Entity\GitRepository $gitRepository */

if ($gitRepository->getID() !== null) {
    ?>
    <form id="comtra-delete" method="post" class="d-none" action="<?= $view->action('deleteRepository', $gitRepository->getID()) ?>">
        <?php $token->output('comtra-repository-delete' . $gitRepository->getID()) ?>
        <input type="hidden" name="repositoryID" value="<?= $gitRepository->getID() ?>" />
    </form>
    <?php
}

?>
<form method="post" class="form-horizontal" action="<?= $view->action('save') ?>" onsubmit="if (this.already) return false; this.already = true">
    <?= $token->output('comtra-repository-save') ?>
    <input type="hidden" name="repositoryID" value="<?= ($gitRepository->getID() === null) ? 'new' : $gitRepository->getID() ?>" />

    <div class="row form-group">
        <label for="name" class="control-label col-sm-3"><?= t('Mnemonic name') ?></label>
        <div class="col-sm-7">
            <div class="input-group">
                <?= $form->text('name', $gitRepository->getName(), ['required' => 'required', 'maxlength' => 100]) ?>
                <div class="input-group-append"><span class="input-group-text"><i class="fa fa-asterisk"></i></span></div>
            </div>
        </div>
    </div>

    <div class="row form-group">
        <label for="packageHandle" class="control-label col-sm-3"><?= t('Package handle') ?></label>
        <div class="col-sm-7">
            <?= $form->text('packageHandle', $gitRepository->getPackageHandle(), ['required' => 'required', 'maxlength' => 64]) ?>
        </div>
    </div>

    <div class="row form-group">
        <label for="url" class="control-label col-sm-3"><?= t('Repository URL') ?></label>
        <div class="col-sm-7">
            <div class="input-group">
                <?= $form->url('url', $gitRepository->getURL(), ['required' => 'required', 'maxlength' => 255]) ?>
                <div class="input-group-append"><span class="input-group-text"><i class="fa fa-asterisk"></i></span></div>
            </div>
        </div>
    </div>

    <div class="row form-group">
        <div class="control-label col-sm-3"><label for="directoryToParse" class="launch-tooltip" data-html="true" title="<?= t('This is the path to the directory in the git repository that contains the translatable strings') ?>"><?= t('Directory to parse') ?></label></div>
        <div class="col-sm-7">
            <?= $form->text('directoryToParse', $gitRepository->getDirectoryToParse(), ['maxlength' => 255]) ?>
        </div>
    </div>

    <div class="row form-group">
        <div class="control-label col-sm-3"><label for="directoryForPlaces" class="launch-tooltip" data-html="true" title="<?= t('This will be the base directory in places associated to extracted comments') ?>"><?= t('Base directory for places') ?></label></div>
        <div class="col-sm-7">
            <?= $form->text('directoryForPlaces', $gitRepository->getDirectoryForPlaces(), ['maxlength' => 255]) ?>
        </div>
    </div>

    <div class="row form-group">
        <div class="col-sm-3 control-label"><label class="launch-tooltip" title="<?= t('The tags that satisfy this criteria will be fetched just once.') ?>"><?= t('Parse tags') ?></label></div>
        <div class="col-sm-7">
            <?php
            $ptx = $gitRepository->getTagFiltersExpanded();
            $ptx1 = null;
            $ptx2 = null;
            if ($ptx === null) {
                $parsetags = 1;
            } elseif (empty($ptx)) {
                $parsetags = 2;
            } else {
                $parsetags = 3;
                $ptx1 = array_shift($ptx);
                $ptx2 = array_shift($ptx);
            }
            ?>
            <div class="form-group">
                <div class="form-check">
                    <?= $form->radio('parsetags', '1', $parsetags === 1, ["class" => "form-check-input", "id" => "parsetags1"])?>
                    <?= $form->label("parsetags1", tc('Tags', 'none'), ["class" => "form-check-label"]); ?>
                </div>

                <div class="form-check">
                    <?= $form->radio('parsetags', '2', $parsetags === 2, ["class" => "form-check-input", "id" => "parsetags2"])?>
                    <?= $form->label("parsetags2", tc('Tags', 'all'), ["class" => "form-check-label"]); ?>
                </div>

                <div class="form-check">
                    <?= $form->radio('parsetags', '3', $parsetags === 3, ["class" => "form-check-input", "id" => "parsetags3"])?>
                    <?= $form->label("parsetags3", tc('Tags', 'filter'), ["class" => "form-check-label"]); ?>
                </div>
            </div>

            <span class="comtra-parsetags-filter" style="visibility: hidden">
                <?= $form->select('parsetagsOperator1', ['&lt;' => '&lt;', '&lt;=' => '&le;', '=' => '=', '&gt;=' => '&ge;', '>' => '&gt;'], h(($ptx1 === null) ? '>=' : $ptx1['operator']), ['style' => 'width: 60px; display: inline']) ?>
                <?= $form->text('parsetagsVersion1', ($ptx1 === null) ? '1.0' : $ptx1['version'], ['style' => 'width: 100px; display: inline']) ?>
            </span>
            <span class="comtra-parsetags-filter" style="visibility: hidden">
                <div class="form-check">
                    <?= $form->checkbox('parsetagsAnd2', '1', $ptx2 !== null, ["class" => "form-check-input"]) ?>
                    <?= $form->label("parsetagsAnd2", t('and'), ["class" => "form-check-label"]) ?>
                </div>
                <span class="comtra-parsetags-filter2" style="visibility: hidden">
                    <?= $form->select('parsetagsOperator2', ['&lt;' => '&lt;', '&lt;=' => '&le;', '=' => '=', '&gt;=' => '&ge;', '>' => '&gt;'], h(($ptx2 === null) ? '<' : $ptx2['operator']), ['style' => 'width: 60px; display: inline']) ?>
                    <?= $form->text('parsetagsVersion2', ($ptx2 === null) ? '2.0' : $ptx2['version'], ['style' => 'width: 100px; display: inline']) ?>
                </span>
            </span>
        </div>
    </div>


    <div class="row form-group" id="comtra-tag2verregex" style="display: none">
        <div class="col-sm-3 control-label"><label for="tag2verregex" class="launch-tooltip" data-html="true" title="<?= t('A regular expression whose first match against the tags should be the version') ?>"><?= t('Tag-to-filter regular expression') ?></label></div>
        <div class="col-sm-7">
            <?= $form->text('tag2verregex', $gitRepository->getTagToVersionRegexp(), ['maxlength' => 255]) ?>
        </div>
    </div>

    <div class="row form-group">
        <div class="col-sm-3 control-label"><label class="launch-tooltip" data-html="true" title="<?= t('These branches should be fetched periodically in order to extract new strings while the development progresses. The version should start with %s', '<code>' . h(Version::DEV_PREFIX) . '</code>') ?>"><?= t('Development branches') ?></label></div>
        <div class="col-sm-7" id="comtra-devbranches"></div>
    </div>

    <div class="ccm-dashboard-form-actions-wrapper">
        <div class="ccm-dashboard-form-actions">
            <a href="<?= URL::to('/dashboard/community_translation/git_repositories') ?>" class="btn btn-secondary float-left"><?= t('Cancel') ?></a>
            <div class="float-right">
                <?php
                if ($gitRepository->getID() !== null) {
                    ?><a href="#" id="comtra-delete-btn" class="btn btn-danger"><?= t('Delete') ?></a><?php
                }
                ?>
                <input type="submit" class="btn btn-primary ccm-input-submit" value="<?= ($gitRepository->getID() === null) ? t('Create') : t('Update') ?>">
            </div>
        </div>
    </div>
</form>
<div class="d-none" id="comtra-devbranches-template">
    <div class="comtra-devbranches-pair" style="white-space: nowrap">
        <div class="input-group">
            <input type="text" name="branch[]" class="form-control" placeholder="<?= h(t('Branch')) ?>"/>
            <div class="input-group-append">
                <span class="input-group-text">&rArr;</span>
            </div>
            <input type="text" name="version[]" class="form-control"  placeholder="<?= h(t('Version')) ?>"/>

            <div class="input-group-append">
            <a href="#" class="btn-outline-secondary btn" onclick="$(this).closest('div').parent().parent().remove();$('#comtra-devbranches').trigger('change'); return false;"><?php echo t("Remove"); ?></a>
            </div>
        </div>
    </div>
</div>
<script>
$(document).ready(function() {

<?php
if ($gitRepository->getID() !== null) {
                    ?>
    $('a#comtra-delete-btn').on('click', function(e) {
        if (window.confirm(<?= json_encode(t('Are you sure?')) ?>)) {
            $('form#comtra-delete').submit();
        }
        e.preventDefault();
        return false;
    });
    <?php
                }
?>
$('input[name="parsetags"],#parsetagsAnd2').on('change', function() {
    var v = parseInt($('input[name="parsetags"]:checked').val(), 10),
        ask1 = v === 3,
        ask2 = ask1 && $('#parsetagsAnd2').is(':checked');
    $('.comtra-parsetags-filter').css('visibility', ask1 ? 'visible' : 'hidden');
    $('.comtra-parsetags-filter2').css('visibility', ask2 ? 'visible' : 'hidden');
    $('#parsetagsVersion1,#parsetagsVersion2').removeAttr('required').removeAttr('pattern');
    if (ask1) {
        $('#parsetagsVersion1').attr('required', 'required').attr('pattern', '[0-9]+(\.[0-9]+)*');
    }
    if (ask2) {
        $('#parsetagsVersion2').attr('required', 'required').attr('pattern', '[0-9]+(\.[0-9]+)*');
    }
    $('#comtra-tag2verregex')[v === 1 ? 'd-none' : 'show']('fast');
    if (v === 1) {
        $('#tag2verregex').removeAttr('pattern required')
    } else {
        $('#tag2verregex').attr('required', 'required').attr('pattern', '\/.+\/\w*')
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
$branches = is_array($branches) ? array_values($branches) : [];
$versions = $this->post('version');
$versions = is_array($versions) ? array_values($versions) : [];
if (!empty($branches) && count($branches) === count($versions)) {
    foreach (array_keys($branches) as $i) {
        ?>addDevBranchPair(<?= json_encode($branches[$i]) ?>, <?= json_encode($versions[$i]) ?>);<?php
    }
} else {
    foreach ($gitRepository->getDevBranches() as $branch => $version) {
        ?>addDevBranchPair(<?= json_encode($branch) ?>, <?= json_encode($version) ?>);<?php
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
