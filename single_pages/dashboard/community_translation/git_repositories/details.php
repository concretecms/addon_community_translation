<?php

declare(strict_types=1);

use CommunityTranslation\Entity\Package\Version;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $this
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var Concrete\Core\Url\Resolver\Manager\ResolverManagerInterface $urlResolver
 * @var Concrete\Core\Form\Service\Form $form
 * @var CommunityTranslation\Entity\GitRepository $gitRepository
 * @var array $devBranches
 */

if ($gitRepository->getID() !== null) {
    ?>
    <form id="comtra-delete" method="post" class="d-none" action="<?= $view->action('deleteRepository', $gitRepository->getID()) ?>">
        <?php $token->output('comtra-repository-delete' . $gitRepository->getID()) ?>
        <input type="hidden" name="repositoryID" value="<?= $gitRepository->getID() ?>" />
    </form>
    <?php
}
?>
<div id="comtra-app" v-cloak>
    <form method="post" class="form-horizontal" action="<?= h($view->action('save')) ?>" onsubmit="if (this.already) return false; this.already = true" v-cloak id="comtra-app">
        <?= $token->output('comtra-repository-save') ?>
        <input type="hidden" name="repositoryID" value="<?= $gitRepository->getID() === null ? 'new' : $gitRepository->getID() ?>" />

        <div class="mb-3">
            <label class="form-label" for="name"><?= t('Mnemonic name') ?></label>
            <div class="input-group">
                <?= $form->text('name', $gitRepository->getName(), ['required' => 'required', 'maxlength' => 100]) ?>
                <span class="input-group-text"><i class="fa fa-asterisk"></i></span>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="packageHandle"><?= t('Package handle') ?></label>
            <div class="input-group">
                <?= $form->text('packageHandle', $gitRepository->getPackageHandle(), ['required' => 'required', 'maxlength' => 64]) ?>
                <span class="input-group-text"><i class="fa fa-asterisk"></i></span>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="url"><?= t('Repository URL') ?></label>
            <div class="input-group">
                <?= $form->url('url', $gitRepository->getURL(), ['required' => 'required', 'maxlength' => 255]) ?>
                <span class="input-group-text"><i class="fa fa-asterisk"></i></span>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="directoryToParse"><?= t('Directory to parse') ?></label>
            <div class="small text-muted"><?= t('This is the path to the directory in the git repository that contains the translatable strings') ?></div>
            <?= $form->text('directoryToParse', $gitRepository->getDirectoryToParse(), ['maxlength' => 255]) ?>
        </div>

        <div class="mb-3">
            <label class="form-label" for="directoryForPlaces"><?= t('Base directory for places') ?></label>
            <div class="small text-muted"><?= t('This will be the base directory in places associated to extracted comments') ?></div>
            <?= $form->text('directoryForPlaces', $gitRepository->getDirectoryForPlaces(), ['maxlength' => 255]) ?>
        </div>

        <div class="mb-3">
            <label class="form-label" title="<?= t('The tags that satisfy this criteria will be fetched just once.') ?>"><?= t('Parse tags') ?></label>
            <?php
            $ptx = $gitRepository->getTagFiltersExpanded();
            $ptx1 = null;
            $ptx2 = null;
            if ($ptx === null) {
                $parsetags = 1;
            } elseif ($ptx === []) {
                $parsetags = 2;
            } else {
                $parsetags = 3;
                $ptx1 = array_shift($ptx);
                $ptx2 = array_shift($ptx);
            }
            ?>
            <div class="form-check">
                <?= $form->radio('parsetags', '1', $parsetags === 1, ['class' => 'form-check-input', 'id' => 'parsetags1', 'v-model' => 'parsetags']) ?>
                <?= $form->label('parsetags1', tc('Tags', 'none'), ['class' => 'form-check-label']) ?>
            </div>

            <div class="form-check">
                <?= $form->radio('parsetags', '2', $parsetags === 2, ['class' => 'form-check-input', 'id' => 'parsetags2', 'v-model' => 'parsetags']) ?>
                <?= $form->label('parsetags2', tc('Tags', 'all'), ['class' => 'form-check-label']) ?>
            </div>

            <div class="form-check">
                <?= $form->radio('parsetags', '3', $parsetags === 3, ['class' => 'form-check-input', 'id' => 'parsetags3', 'v-model' => 'parsetags']) ?>
                <?= $form->label('parsetags3', tc('Tags', 'filter'), ['class' => 'form-check-label']) ?>
            </div>

            <div class="input-group input-group-sm" v-visible="showParsetags1" style="max-width: 500px">
                <?= $form->select('parsetagsOperator1', ['&lt;' => '&lt;', '&lt;=' => '&le;', '=' => '=', '&gt;=' => '&ge;', '>' => '&gt;'], h(($ptx1 === null) ? '>=' : $ptx1['operator'])) ?>
                <?= $form->text('parsetagsVersion1', ($ptx1 === null) ? '1.0' : $ptx1['version'], ['v-bind:required' => 'showParsetags1', 'v-bind:pattern' => "showParsetags1 ? '[0-9]+(\.[0-9]+)*' : null"]) ?>
                <div class="input-group-text">
                    <?= $form->checkbox('parsetagsAnd2', '1', $ptx2 !== null, ['class' => 'form-check-input', 'v-model' => 'parsetagsAnd2']) ?>
                    <span class="input-group-text"><?= t('and') ?></span>
                </div>
                <?= $form->select('parsetagsOperator2', ['&lt;' => '&lt;', '&lt;=' => '&le;', '=' => '=', '&gt;=' => '&ge;', '>' => '&gt;'], h(($ptx2 === null) ? '<' : $ptx2['operator']), ['v-visible' => 'showParsetags2']) ?>
                <?= $form->text('parsetagsVersion2', ($ptx2 === null) ? '2.0' : $ptx2['version'], ['v-visible' => 'showParsetags2', 'v-bind:required' => 'showParsetags2', 'v-bind:pattern' => "showParsetags2 ? '[0-9]+(\.[0-9]+)*' : null"]) ?>
            </div>
        </div>

        <div class="row mb-3" id="comtra-tag2verregex" style="display: none">
            <label class="form-label" for="tag2verregex" title="<?= t('A regular expression whose first match against the tags should be the version') ?>"><?= t('Tag-to-filter regular expression') ?></label>
            <?= $form->text('tag2verregex', $gitRepository->getTagToVersionRegexp(), ['maxlength' => 255]) ?>
        </div>

        <div class="row mb-3">
            <label class="form-label" title="<?= t('These branches should be fetched periodically in order to extract new strings while the development progresses. The version should start with %s', '<code>' . h(Version::DEV_PREFIX) . '</code>') ?>"><?= t('Development branches') ?></label>
            <div style="max-width: 500px">
                <comtra-devbranch
                    v-for="(devBranch, index) in devBranches"
                    v-bind:index="index"
                    v-bind:dev-branch="devBranch"
                    v-on:change="checkNewBranch"
                    v-on:delete="removeDevBramch($event)"
                ></comtra-devbranch>
            </div>
        </div>

        <div class="ccm-dashboard-form-actions-wrapper">
            <div class="ccm-dashboard-form-actions">
                <a href="<?= h($urlResolver->resolve(['/dashboard/community_translation/git_repositories'])) ?>" class="btn btn-secondary"><?= t('Cancel') ?></a>
                <?php
                if ($gitRepository->getID() !== null) {
                    ?><a href="#" class="btn btn-danger" v-on:click.prevent="deleteGitRepository"><?= t('Delete') ?></a><?php
                }
                ?>
                <input type="submit" class="btn btn-primary ccm-input-submit" value="<?= ($gitRepository->getID() === null) ? t('Create') : t('Update') ?>">
            </div>
        </div>
    </form>
</div>
<script>$(document).ready(function() {

Vue.directive('visible', function(el, binding) {
    el.style.visibility = binding.value ? 'visible' : 'hidden';
});

Vue.component('comtra-devbranch', {
    props: {
        index: {
            type: Number,
            required: true,
        },
        devBranch: {
            type: Object,
            required: true,
        },
    },
    template: `<div class="input-group">
        <input type="text" name="branch[]" class="form-control" placeholder="<?= h(t('Branch')) ?>" v-model.trim="devBranch.branch" v-bind:required="required" />
        <span class="input-group-text">&rArr;</span>
        <input type="text" name="version[]" class="form-control" placeholder="<?= h(t('Version')) ?>" v-model.trim="devBranch.version" v-bind:required="required" />
        <a href="#" class="btn-outline-secondary btn" v-on:click.prevent="emitDelete"><?= t('Remove') ?></a>
    </div>`,
    methods: {
        emitChange: function() {
            this.$emit('change', this.index);
        },
        emitDelete: function() {
            this.$emit('delete', this.index);
        },
    },
    watch: {
        'devBranch.branch': function() {
            this.emitChange();
        },
        'devBranch.version': function() {
            this.emitChange();
        },
    },
    computed: {
        required: function() {
            return this.devBranch.branch !== '' || this.devBranch.version !== '';
        },
    },
});

new Vue({
    el: '#comtra-app',
    data: function() {
        return {
            parsetags: '',
            parsetagsAnd2: false,
            devBranches: <?= json_encode($devBranches) ?>,
        };
    },
    beforeMount: function() {
        this.parsetags = document.querySelector('input[name="parsetags"]:checked').value;
        this.parsetagsAnd2 = document.getElementById('parsetagsAnd2').checked;
    },
    mounted: function() {
        this.checkNewBranch();
    },
    computed: {
        showParsetags1: function() {
            return this.parsetags === '3';
        },
        showParsetags2: function() {
            return this.showParsetags1 && this.parsetagsAnd2;
        },
    },
    methods: {
        removeDevBramch: function(index) {
            this.devBranches.splice(index, 1);
            this.checkNewBranch();
        },
        checkNewBranch: function() {
            for (const db of this.devBranches) {
                if (db.branch === '' && db.version === '') {
                    return;
                }
            }
            this.devBranches.push({branch: '', version: ''});
        },
        <?php
        if ($gitRepository->getID() !== null) {
            ?>
            deleteGitRepository: function() {
                if (window.confirm(<?= json_encode(t('Are you sure?')) ?>)) {
                    $('form#comtra-delete').submit();
                }
            },
            <?php
        }
        ?>
    },
});

});</script>
