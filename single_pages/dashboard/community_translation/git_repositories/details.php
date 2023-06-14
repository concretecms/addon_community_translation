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

$buildLabelWithTooltip = function (string $for, string $text, string $tooltipHtml) use ($form): string {
    return $form->label($for, $text . ' <i class="far fa-question-circle launch-tooltip small text-muted" title="' . h($tooltipHtml) . '" data-bs-html="true"></i>');
};

?>
<div class="d-none">
    <?php
    if ($gitRepository->getID() !== null) {
        ?>
        <form id="comtra-delete" method="post" action="<?= $view->action('deleteRepository', $gitRepository->getID()) ?>">
            <?php $token->output('comtra-repository-delete' . $gitRepository->getID()) ?>
            <input type="hidden" name="repositoryID" value="<?= $gitRepository->getID() ?>" />
        </form>
        <?php
    }
    ?>
    <div id="comtra-app-test-tag2ver-regex" v-cloak>
        <div class="mb-3">
            <?= $form->label('comtra-test-regex-rx', t('Regular expression')) ?>
            <?= $form->text('comtra-test-regex-rx', '', ['maxlength' => 255, 'spellcheck' => 'false', 'class' => 'form-control-sm font-monospace', 'v-model.trim' => 'rx', 'v-bind:readonly' => 'busy']) ?>
            <div class="small text-muted">
                <?= t('Default value: %s', '<a href="#" v-on:click.prevent="' . h('rx = ' . json_encode($gitRepository::DEFAULT_TAGTOVERSION_REGEXP)) . '"><code>' . h($gitRepository::DEFAULT_TAGTOVERSION_REGEXP) . '</code></a>') ?>
            </div>
        </div>
        <div class="mb-3">
            <?= $form->label('comtra-test-regex-tag', t('Sample git tag found')) ?>
            <?= $form->text('comtra-test-regex-tag', '', ['spellcheck' => 'false', 'class' => 'form-control-sm font-monospace', 'v-model.trim' => 'sampleTag', 'v-bind:readonly' => 'busy']) ?>
        </div>
        <div class="mb-3">
            <?= $form->label('comtra-test-regex-result', t('Resulting version')) ?>
            <div class="input-group input-group-sm">
                <?= $form->text('comtra-test-regex-result', '', ['readonly' => 'readonly', 'class' => 'form-control-sm', 'v-bind:value' => 'result', 'v-bind:class' => "{'font-monospace': !error, 'text-danger': error}"]) ?>
                <span class="input-group-text" v-visible="busy">
                    <i class="fas fa-sync-alt" v-bind:class="{'fa-spin': busy}"></i>
                </span>
            </div>
        </div>
        <div class="dialog-buttons">
            <button class="btn btn-secondary float-start" onclick="jQuery.fn.dialog.closeTop()"><?= t('Cancel') ?></button>
            <button class="btn float-end" v-bind:class="error ? 'btn-danger' : 'btn-success'" v-on:click.prevent="apply"><?= t('Apply') ?></button>
        </div>
    </div>
</div>

<div id="comtra-app" v-cloak>
    <form method="post" class="form-horizontal" action="<?= h($view->action('save')) ?>" onsubmit="if (this.already) return false; this.already = true" v-cloak id="comtra-app">
        <?= $token->output('comtra-repository-save') ?>
        <input type="hidden" name="repositoryID" value="<?= $gitRepository->getID() === null ? 'new' : $gitRepository->getID() ?>" />

        <div class="mb-3">
            <?= $form->label('name', t('Mnemonic name')) ?>
            <div class="input-group">
                <?= $form->text('name', $gitRepository->getName(), ['required' => 'required', 'maxlength' => 100]) ?>
                <span class="input-group-text"><i class="fa fa-asterisk"></i></span>
            </div>
        </div>

        <div class="mb-3">
            <?= $form->label('packageHandle', t('Package handle')) ?>
            <div class="input-group">
                <?= $form->text('packageHandle', $gitRepository->getPackageHandle(), ['required' => 'required', 'maxlength' => 64, 'spellcheck' => 'false', 'class' => 'font-monospace']) ?>
                <span class="input-group-text"><i class="fa fa-asterisk"></i></span>
            </div>
        </div>

        <div class="mb-3">
            <?= $form->label('url', t('Repository URL')) ?>
            <div class="input-group">
                <?= $form->url('url', $gitRepository->getURL(), ['required' => 'required', 'maxlength' => 255, 'spellcheck' => 'false', 'class' => 'font-monospace']) ?>
                <span class="input-group-text"><i class="fa fa-asterisk"></i></span>
            </div>
        </div>

        <div class="mb-3">
            <?= $buildLabelWithTooltip('directoryToParse', t('Directory to parse'), t('This is the path of the directory in the git repository that contains the translatable strings')) ?>
            <?= $form->text('directoryToParse', $gitRepository->getDirectoryToParse(), ['maxlength' => 255, 'spellcheck' => 'false', 'class' => 'font-monospace']) ?>
        </div>

        <div class="mb-3">
            <?= $buildLabelWithTooltip('directoryForPlaces', t('Base directory for places'), t('This is the base directory for the places associated to extracted comments')) ?>
            <?= $form->text('directoryForPlaces', $gitRepository->getDirectoryForPlaces(), ['maxlength' => 255, 'spellcheck' => 'false', 'class' => 'font-monospace']) ?>
        </div>
        <div class="mb-3">
            <?= $buildLabelWithTooltip('', t('Parse tags'), t('The tags that satisfy this criteria will be fetched just once.')) ?>
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
                <?= $form->radio('parsetags', '1', $parsetags === 1, ['class' => 'form-check-input', 'id' => 'parsetags1', 'v-model' => 'parsetags', 'v-bind:value' => '1']) ?>
                <?= $form->label('parsetags1', tc('Tags', 'none'), ['class' => 'form-check-label']) ?>
            </div>

            <div class="form-check">
                <?= $form->radio('parsetags', '2', $parsetags === 2, ['class' => 'form-check-input', 'id' => 'parsetags2', 'v-model' => 'parsetags', 'v-bind:value' => '2']) ?>
                <?= $form->label('parsetags2', tc('Tags', 'all'), ['class' => 'form-check-label']) ?>
            </div>

            <div class="form-check">
                <?= $form->radio('parsetags', '3', $parsetags === 3, ['class' => 'form-check-input', 'id' => 'parsetags3', 'v-model' => 'parsetags', 'v-bind:value' => '3']) ?>
                <?= $form->label('parsetags3', tc('Tags', 'filter'), ['class' => 'form-check-label']) ?>
            </div>

            <div class="input-group input-group-sm" v-visible="showParsetags1" style="max-width: 500px">
                <?= $form->select('parsetagsOperator1', ['&lt;' => '&lt;', '&lt;=' => '&le;', '=' => '=', '&gt;=' => '&ge;', '>' => '&gt;'], h(($ptx1 === null) ? '>=' : $ptx1['operator'])) ?>
                <?= $form->text('parsetagsVersion1', ($ptx1 === null) ? '1.0' : $ptx1['version'], ['v-bind:required' => 'showParsetags1', 'v-bind:pattern' => "showParsetags1 ? '[0-9]+(\.[0-9]+)*' : null", 'spellcheck' => 'false', 'class' => 'font-monospace']) ?>
                <div class="input-group-text">
                    <?= $form->checkbox('parsetagsAnd2', '1', $ptx2 !== null, ['class' => 'form-check-input', 'v-model' => 'parsetagsAnd2']) ?>
                    <span class="input-group-text"><?= t('and') ?></span>
                </div>
                <?= $form->select('parsetagsOperator2', ['&lt;' => '&lt;', '&lt;=' => '&le;', '=' => '=', '&gt;=' => '&ge;', '>' => '&gt;'], h(($ptx2 === null) ? '<' : $ptx2['operator']), ['v-visible' => 'showParsetags2']) ?>
                <?= $form->text('parsetagsVersion2', ($ptx2 === null) ? '2.0' : $ptx2['version'], ['v-visible' => 'showParsetags2', 'v-bind:required' => 'showParsetags2', 'v-bind:pattern' => "showParsetags2 ? '[0-9]+(\.[0-9]+)*' : null", 'spellcheck' => 'false', 'class' => 'font-monospace']) ?>
            </div>
        </div>

        <div class="row mb-3" id="comtra-tag2verregex" v-if="parsetags &gt; 1">
            <?= $buildLabelWithTooltip('tag2verregex', t('Tag-to-filter regular expression'), t('A regular expression whose first match against the tags should be the version')) ?>
            <div class="input-group">
                <?= $form->text('tag2verregex', $gitRepository->getTagToVersionRegexp(), ['maxlength' => 255, 'spellcheck' => 'false', 'class' => 'font-monospace', 'v-model.trim' => 'tag2verregex']) ?>
                <button class="btn btn-outline-secondary" type="button" v-on:click.prevent="testTag2VerRegex"><?= t('Test') ?></button>
            </div>
        </div>

        <div class="row mb-3">
            <?= $form->label('directoryForPlaces', t('')) ?>
            <?= $buildLabelWithTooltip('directoryForPlaces', t('Development branches'), t('These branches should be fetched periodically in order to extract new strings while the development progresses. The version should start with %s', '<code>' . h(Version::DEV_PREFIX) . '</code>')) ?>
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
<script type="text/x-template" id="comtra-devbranch-template">
    <div class="input-group">
        <input type="text" name="branch[]" class="form-control font-monospace" placeholder="<?= h(t('Branch')) ?>" v-model.trim="devBranch.branch" v-bind:required="required" spellcheck="false" />
        <span class="input-group-text">&rArr;</span>
        <input type="text" name="version[]" class="form-control font-monospace" placeholder="<?= h(t('Version')) ?>" v-model.trim="devBranch.version" v-bind:required="required" spellcheck="false" />
        <a href="#" class="btn-outline-secondary btn" v-on:click.prevent="emitDelete"><?= t('Remove') ?></a>
    </div>
</script>
<script type="text/x-template" id="comtra-test-regex-template">
</script>

<script>$(document).ready(function() {

Vue.directive('visible', function(el, binding) {
    el.style.visibility = binding.value ? 'visible' : 'hidden';
});

Vue.component('comtra-devbranch', {
    template: '#comtra-devbranch-template',
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
    methods: {
        emitChange() {
            this.$emit('change', this.index);
        },
        emitDelete() {
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
        required() {
            return this.devBranch.branch !== '' || this.devBranch.version !== '';
        },
    },
});

const tag2VerRegexTester = new Vue({
    el: '#comtra-app-test-tag2ver-regex',
    data() {
        return {
            rx: '',
            sampleTag: '1.2.3',
            error: false,
            result: '',
            busy: false,
            updateTimer: null,
            applyCallback: null,
        };
    },
    watch: {
        rx() {
            this.scheduleUpdate();
        },
        sampleTag() {
            this.scheduleUpdate();
        },
    },
    methods: {
        show(rx, applyCallback) {
            this.applyCallback = applyCallback;
            this.rx = rx;
            $.fn.dialog.open({
                element: this.$el,
                modal: true,
                width: Math.min(Math.max($(window).width() - 100, 300), 1400),
                title: <?= json_encode(t('Test regular expression')) ?>,
                height: 'auto',
            });
            this.$nextTick(() => this.update());
        },
        scheduleUpdate() {
            if (this.updateTimer !== null) {
                clearTimeout(this.updateTimer);
            }
            this.updateTimer = setTimeout(() => this.update(), 200);
        },
        update() {
            if (this.busy) {
                return;
            }
            if (this.updateTimer !== null) {
                clearTimeout(this.updateTimer);
                this.updateTimer = null;
            }
            this.busy = true;
            this.error = false;
            this.result = '...';
            $.ajax({
                data: $.extend(true, <?= json_encode([$token::DEFAULT_TOKEN_NAME => $token->generate('ct-testregex')]) ?>, {
                    rx: this.rx,
                    sampleTag: this.sampleTag,
                }),
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action('testTag2verRegex')) ?>
            })
            .always(() => {
                this.busy = false;
            })
            .done((data) => {
                if (data && typeof data.resultingVersion === 'string') {
                    this.error = false;
                    this.result = data.resultingVersion;
                } else {
                    this.error = true;
                    this.result = data && typeof data.error === 'string' && data.error !== '' ? data.error : '?';
                }
            })
            .fail((xhr, status, error) => {
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
                this.error = true;
                this.result = <?= json_encode(t('Error')) ?>;
            });
        },
        apply() {
            this.applyCallback(this.rx);
            jQuery.fn.dialog.closeTop();
        },
    },
});

new Vue({
    el: '#comtra-app',
    data() {
        return {
            parsetags: null,
            parsetagsAnd2: false,
            tag2verregex: '',
            devBranches: <?= json_encode($devBranches) ?>,
        };
    },
    beforeMount() {
        this.parsetags = parseInt(document.querySelector('input[name="parsetags"]:checked').value);
        this.parsetagsAnd2 = document.getElementById('parsetagsAnd2').checked;
        this.tag2verregex = document.querySelector('input[name="tag2verregex"]').value;
    },
    mounted() {
        this.checkNewBranch();
    },
    computed: {
        showParsetags1() {
            return this.parsetags === 3;
        },
        showParsetags2() {
            return this.showParsetags1 && this.parsetagsAnd2;
        },
    },
    methods: {
        removeDevBramch(index) {
            this.devBranches.splice(index, 1);
            this.checkNewBranch();
        },
        checkNewBranch() {
            for (const db of this.devBranches) {
                if (db.branch === '' && db.version === '') {
                    return;
                }
            }
            this.devBranches.push({branch: '', version: ''});
        },
        testTag2VerRegex() {
            tag2VerRegexTester.show(this.tag2verregex, (rx) => this.tag2verregex = rx);
        },
        <?php
        if ($gitRepository->getID() !== null) {
            ?>
            deleteGitRepository() {
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
