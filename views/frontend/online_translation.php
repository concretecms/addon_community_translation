<?php

declare(strict_types=1);

use CommunityTranslation\Glossary\EntryType as GlossaryEntryType;
use CommunityTranslation\Translatable\StringFormat;
use Concrete\Core\Localization\Localization;
use Concrete\Core\View\View;

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\View\View $this
 * @var Concrete\Core\View\View $view
 * @var Concrete\Package\CommunityTranslation\Controller\Frontend\OnlineTranslation $controllers
 * @var CommunityTranslation\Entity\Package\Version|string $packageVersion
 * @var int|string $packageVersionID
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var bool $canApprove
 * @var CommunityTranslation\Entity\Locale $locale
 * @var bool $canEditGlossary
 * @var array $pluralCases
 * @var array $translations
 * @var string $pageTitle
 * @var string $pageTitleShort
 * @var array|null $allVersions
 * @var array|null $allLocales
 * @var string $onlineTranslationPath
 * @var CommunityTranslation\TranslationsConverter\ConverterInterface[] $translationFormats
 * @var string|null $showDialogAtStartup
 * @var Concrete\Core\Url\UrlImmutable $exitURL
 * @var string $viewUnreviewedUrl
 * @var CommunityTranslation\Entity\PackageSubscription|null $packageSubscription
 * @var CommunityTranslation\Entity\PackageVersionSubscription[]|null $packageVersionSubscriptions
 * @var string $textDirection
 * @var array $allTranslators
 */

$packageVersionIsObject = is_object($packageVersion);

$enableManagingNotifications = $packageVersionIsObject;
$enableComments = $packageVersionIsObject;

$ajaxKeys = [
    'loadTranslation' => [
        'url' => (string) $this->action('load_translation', $locale->getID()),
        'token' => $token->generate('comtra-load-translation' . $locale->getID()),
    ],
    'processTranslation' => [
        'url' => (string) $this->action('process_translation', $locale->getID()),
        'token' => $token->generate('comtra-process-translation' . $locale->getID()),
    ],
    'loadAllPlaces' => [
        'url' => (string) $this->action('load_all_places', $locale->getID()),
        'token' => $token->generate('comtra-load-all-places' . $locale->getID()),
    ],
];
if ($enableManagingNotifications) {
    $ajaxKeys += [
        'saveNotifications' => [
            'url' => (string) $this->action('save_notifications', $packageVersion->getPackage()->getID()),
            'token' => $token->generate('comtra-save-notifications' . $packageVersion->getPackage()->getID()),
        ],
    ];
}
if ($canEditGlossary) {
    $ajaxKeys += [
        'saveGlossaryEntry' => [
            'url' => (string) $this->action('save_glossary_entry', $locale->getID()),
            'token' => $token->generate('comtra-save-glossary-entry' . $locale->getID()),
        ],
        'deleteGlossaryEntry' => [
            'url' => (string) $this->action('delete_glossary_entry', $locale->getID()),
            'token' => $token->generate('comtra-delete-glossary-entry' . $locale->getID()),
        ],
    ];
}
if ($enableComments) {
    $ajaxKeys += [
        'saveComment' => [
            'url' => (string) $this->action('save_comment', $locale->getID()),
            'token' => $token->generate('comtra-save-comment' . $locale->getID()),
        ],
        'deleteComment' => [
            'url' => (string) $this->action('delete_comment', $locale->getID()),
            'token' => $token->generate('comtra-delete-comment' . $locale->getID()),
        ],
    ];
}
?><!DOCTYPE html>
<html lang="<?= Localization::activeLanguage() ?>"><head>

<meta http-equiv="X-UA-Compatible" content="IE=edge">
<?php View::element('header_required', ['pageTitle' => $pageTitle]) ?>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
</head><body>

<div id="app" v-cloak>

    <header class="bd-header bg-dark py-1 d-flex align-items-stretch border-bottom border-dark">
        <div class="container-fluid d-flex align-items-center">
            <h1 class="d-flex align-items-center fs-5 mb-0">
                <a class="text-white text-decoration-none" href="<?= $exitURL ?>">
                    <span class="d-none d-lg-block"><?= h($pageTitle) ?></span>
                    <span class="d-lg-none"><?= h($pageTitleShort) ?></span>
                </a>
            </h1>
            <div class="input-group input-group-sm ms-auto w-50" style="max-width: 500px">
                <?php
                if ($allVersions !== null) {
                    ?>
                    <select class="form-control" v-on:change="browseTo($event.target.value)" v-bind:disabled="busy">
                        <?php
                        foreach ($allVersions as $u => $n) {
                            ?><option value="<?= h($u) ?>"<?= $u === '' ? ' selected="selected"' : '' ?>><?= h($n) ?></option><?php
                        }
                        ?>
                    </select>
                    <?php
                }
                if ($allLocales !== null) {
                    ?>
                    <select class="form-control" v-on:change="browseTo($event.target.value)" v-bind:disabled="busy">
                        <?php
                        foreach ($allLocales as $u => $n) {
                            ?><option value="<?= h($u) ?>"<?= $u === '' ? ' selected="selected"' : '' ?>><?= h($n) ?></option><?php
                        }
                        ?>
                    </select>
                    <?php
                }
                if ($viewUnreviewedUrl !== '') {
                    ?>
                    <a class="btn btn-outline-secondary" v-bind:href="busy ? '#' : <?= h(json_encode($viewUnreviewedUrl)) ?>" title="<?= t('View all strings to be reviewed') ?>" v-bind:disabled="busy"><i class="fas fa-handshake"></i></a>
                    <?php
                }
                ?>
                <a class="btn btn-outline-secondary" href="#" title="<?= t('Download translations') ?>" v-bind:disabled="busy" data-bs-toggle="modal" data-bs-target="#comtra_translation-download"><i class="fas fa-download"></i></a>
                <a class="btn btn-outline-secondary" href="#" title="<?= t('Upload translations') ?>" v-bind:disabled="busy" data-bs-toggle="modal" data-bs-target="#comtra_translation-upload"><i class="fas fa-upload"></i></a>
                <?php
                if ($enableManagingNotifications) {
                    ?>
                    <a class="btn btn-outline-secondary" href="#" title="<?= t('Notifications') ?>" v-bind:disabled="busy" data-bs-toggle="modal" data-bs-target="#comtra_translation-notifications"><i class="fas fa-bell"></i></a>
                    <?php
                }
                ?>
            </div>
        </div>
    </header>
    <section id="search-box">
        <div class="card">
            <div class="card-body py-1">
                <div class="input-group input-group-sm">
                    <input type="search" class="form-control" placeholder="<?= t('Search') ?>" v-model="search.text" v-on:keyup.enter="applySearchFilters" />
                    <button type="button" class="btn"
                        v-bind:class="this.search.translated === null ? 'btn-outline-secondary' : this.search.translated ? 'btn-success' : 'btn-danger'"
                        v-bind:title="search.translated === true ? <?= h(json_encode('Show only translated strings')) ?> : search.translated === false ? <?= h(json_encode('Show only untranslated strings')) ?> : <?= h(json_encode('Show translated and untranslated strings')) ?>"
                        v-on:click.prevent="toggleSearchTranslated"
                        v-bind:disabled="busy"
                    ><i class="fas fa-check"></i></button>
                    <button type="button" class="btn" v-bind:class="isAdvancedSearch ? 'btn-success' : 'btn-outline-secondary'" title="<?= t('Advanced Search') ?>" v-bind:disabled="busy" data-bs-toggle="modal" data-bs-target="#comtra_translation-filter"><i class="fas fa-ellipsis-h"></i></button>
                    <button type="button" class="btn btn-primary" title="<?= t('Search') ?>" v-on:click.prevent="applySearchFilters" v-bind:disabled="busy"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </div>
    </section>
    <main>
        <div id="translations-list">
            <div>
                <table class="table table-sm table-hover m-0">
                    <colgroup>
                        <col width="50%" />
                    </colgroup>
                    <thead>
                        <tr class="table-primary">
                            <th><?= t('Original string') ?></th>
                            <th><?= t('Translation') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="translation in page" v-bind:class="translation.rowClass" v-on:click.prevent="setCurrentTranslation(translation)">
                            <td><div>{{ translation.original }}</div></td>
                            <td><div v-if="translation.isTranslated">{{ translation.translations[0] }}</div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="btn-group btn-group-sm" role="group" v-if="totalPages !== 1">
                <button type="button" class="btn btn-primary" v-bind:disabled="busy || totalPages === 0 || pageIndex === 0" v-on:click.prevent="gotoPage(0)"><i class="fas fa-fast-backward"></i></button>
                <button type="button" class="btn btn-primary" v-bind:disabled="busy || totalPages === 0 || pageIndex === 0" v-on:click.prevent="gotoPage(-1, true)"><i class="fas fa-backward"></i></button>
                <button type="button" class="btn btn-outline-secondary" v-bind:disabled="busy || totalPages &lt;= 1" v-on:click.prevent="askPage"><span v-bind:class="totalPages === 0 ? 'invisible' : ''">Page {{ pageIndex + 1 }} / {{ totalPages }}</span></button>
                <button type="button" class="btn btn-primary" v-bind:disabled="busy || totalPages === 0 || pageIndex === totalPages - 1" v-on:click.prevent="gotoPage(1, true)"><i class="fas fa-forward"></i></button>
                <button type="button" class="btn btn-primary" v-bind:disabled="busy || totalPages === 0 || pageIndex === totalPages - 1" v-on:click.prevent="gotoPage(totalPages - 1)"><i class="fas fa-fast-forward"></i></button>
            </div>
        </div>

        <div id="translation-container" class="p-2" v-if="currentTranslation !== null">
            <div class="card">
                <div class="card-header bg-primary text-white"><?= t('Original string') ?></div>
                <div class="card-body">
                    <div class="container-fluid">
                        <div class="row">
                            <label class="form-label" v-if="currentTranslation.isPlural"><b><?= t('Singular') ?></b></label>
                            <comtra-sourcetext
                                v-bind:format="currentTranslation.format"
                                v-bind:text="currentTranslation.original"
                                v-on:copy-whole="setTranslatingString($event, null, true)"
                                v-on:copy-chunk="setTranslatingString($event, null, false)"
                            ></comtra-sourcetext>
                        </div>
                        <div class="row mt-3" v-if="currentTranslation.isPlural">
                            <label class="form-label"><b><?= t('Plural') ?></b></label>
                            <comtra-sourcetext
                                v-bind:format="currentTranslation.format"
                                v-bind:text="currentTranslation.originalPlural"
                                v-on:copy-whole="setTranslatingString($event, null, true)"
                                v-on:copy-chunk="setTranslatingString($event, null, false)"
                            ></comtra-sourcetext>
                        </div>
                    </div>
                    <div class="small text-muted" v-if="currentTranslation.context !== ''"><?= t('Context: %s', '<code>{{ currentTranslation.context }}</code>') ?></div>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-header bg-primary text-white"><?= t('Translation') ?></div>
                <div class="card-body">
                    <ul class="nav nav-tabs" role="tablist" v-if="translating.length !== 1">
                        <li v-for="(ruleName, ruleIndex) in PLURAL_RULE_BYINDEX" class="nav-item">
                            <a class="nav-link" v-bind:class="ruleIndex === translatingIndex ? 'active' : ''" href="#" v-on:click.prevent="translatingIndex = ruleIndex">{{ PLURAL_RULE_NAME[ruleName] }}</a>
                        </li>
                    </ul>
                    <div v-bind:class="translating.length !== 1 ? 'tab-content' : ''">
                        <div v-bind:class="translating.length !== 1 ? 'tab-pane active pt-2' : ''">
                            <div class="small alert alert-success p-1" v-if="currentTranslation.extractedComments.length !== 0">
                                <div v-for="extractedComment in currentTranslation.extractedComments">{{ extractedComment }}</div>
                            </div>
                            <div v-if="translating.length !== 1" class="small text-muted">
                                <?= t('Example: %s', '<code>{{ PLURAL_EXAMPLES[PLURAL_RULE_BYINDEX[translatingIndex]] }}</code>') ?>
                            </div>
                            <textarea
                                class="form-control"
                                v-model="translating[translatingIndex]"
                                rows="5"
                                lang="<?= h(str_replace('_', '-', $locale->getID())) ?>"
                                v-bind:readonly="busy"
                                ref="translating"
                                v-on:keypress.ctrl.10.13.prevent="<?= $canApprove ? 'saveTranslation(true, true)' : 'saveTranslation(null, true)' ?>"
                            ></textarea>
                            <div class="small text-muted text-end" v-if="currentTranslation.currentInfo"
                                v-html="<?= h('`' . t('Translated by %1$s on %2$s', '${ currentTranslation.currentInfo.createdBy }', '${ currentTranslation.currentInfo.createdOn }') . '`') ?>"
                            ></div>
                        </div>
                    </div>
                    <?php
                    if ($canApprove) {
                        ?>
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" role="switch" id="translating-approved" v-model="translatingApproved" v-bind:disabled="busy" />
                            <label class="form-check-label" for="translating-approved"><?= t('This translation is approved') ?></label>
                        </div>
                        <?php
                    } else {
                        ?>
                        <div>
                            {{ translatingApproved ? <?= json_encode('This translation is approved: your changes will need approval.') ?> : <?= json_encode('This translation is not approved.') ?> }}
                        </div>
                        <?php
                    }
                    ?>
                </div>
                <div class="card-footer text-end">
                    <?php
                    if ($canApprove) {
                        ?>
                        <button type="button" class="btn btn-info" v-on:click.prevent="saveTranslation(null, true)" v-bind:disabled="busy"><?= h(t('Save & Continue')) ?></button>
                        <button type="button" class="btn btn-primary" v-on:click.prevent="saveTranslation(true, true)" v-bind:disabled="busy" title="<?= h(t('Ctrl + Enter')) ?>"><?= h(t('Approve & Continue')) ?></button>
                        <?php
                    } else {
                        ?>
                        <button type="button" class="btn btn-primary" v-on:click.prevent="saveTranslation(null, true)" v-bind:disabled="busy" title="<?= h(t('Ctrl + Enter')) ?>"><?= h(t('Save & Continue')) ?></button>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-header bg-secondary text-white"><?= t('References') ?></div>
                <div class="card-body">
                    <div class="alert alert-info" v-if="currentTranslation.references.length === 0">
                        <?= t('No references found for this string.') ?>
                    </div>
                    <div class="list-group" v-else>
                        <div v-for="reference in currentTranslation.references">
                            <a v-if="Array.isArray(reference)" class="list-group-item list-group-item-action" v-bind:href="reference[0]" v-bind:title="reference[0]" target="_blank">{{ reference[1] }}</a>
                            <span v-else>{{ reference }}</span>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="button" class="btn btn-sm btn-secondary" v-on:click.prevent="showAllPlaces(currentTranslation)" v-bind:disabled="busy"><?= t('Show all the places where this string is used') ?></button>
                </div>
            </div>
        </div>

        <div id="translation-extra" v-if="currentTranslation !== null">
            <div id="translation-extra-tabs" class="nav nav-tabs small" role="tablist">
                <button type="button" class="nav-link position-relative active" data-bs-toggle="tab" data-bs-target="#translation-extra-othertranslations" role="tab" title="<?= t('Other translations') ?>">
                    <i class="d-xl-none fas fa-history"></i>
                    <span class="d-none d-xl-inline"><?= t('Other translations') ?></span>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-secondary" v-if="currentTranslation.otherTranslations.length">
                        {{ currentTranslation.otherTranslations.length }}
                    </span>
                </button>
                <?php
                if ($enableComments) {
                    ?>
                    <button type="button" class="nav-link position-relative" data-bs-toggle="tab" data-bs-target="#translation-extra-comments" role="tab" title="<?= t('Comments') ?>">
                        <i class="d-xl-none fas fa-comment"></i>
                        <span class="d-none d-xl-inline"><?= t('Comments') ?></span>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-secondary" v-if="currentTranslation.totalComments !== 0">
                            {{ currentTranslation.totalComments }}
                        </span>
                    </button>
                    <?php
                }
                ?>
                <button type="button" class="nav-link position-relative" data-bs-toggle="tab" data-bs-target="#translation-extra-suggestions" role="tab" title="<?= t('Suggestions') ?>">
                    <i class="d-xl-none fas fa-tasks"></i>
                    <span class="d-none d-xl-inline"><?= t('Suggestions') ?></span>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-secondary" v-if="currentTranslation.suggestions.length">
                        {{ currentTranslation.suggestions.length }}
                    </span>
                </button>
                <button type="button" class="nav-link position-relative" data-bs-toggle="tab" data-bs-target="#translation-extra-glossary" role="tab" title="<?= t('Glossary') ?>">
                    <i class="d-xl-none fas fa-spell-check"></i>
                    <span class="d-none d-xl-inline"><?= t('Glossary') ?></span>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-secondary" v-if="currentTranslation.glossary.length">
                        {{ currentTranslation.glossary.length }}
                    </span>
                </button>
            </div>
            <div class="tab-content pt-2">
                <div class="tab-pane show active" id="translation-extra-othertranslations" role="tabpanel">
                    <div class="alert alert-info" v-if="currentTranslation.otherTranslations.length === 0">
                        <?= t('No other translations found.') ?>
                    </div>
                    <table class="table table-striped" v-else>
                        <colgroup>
                            <col width="1" />
                            <col />
                            <col width="1" />
                        </colgroup>
                        <thead>
                            <tr>
                                <th><?= t('Date') ?></th>
                                <th><?= t('Translation') ?></th>
                                <th><?= t('Action') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="otherTranslation in currentTranslation.otherTranslations">
                                <td class="text-nowrap small">{{ otherTranslation.createdOn }}<br /><?= tc('Prefix of an author name', 'by') ?> <span v-html="otherTranslation.createdBy"></span></td>
                                <td>
                                    <a href="#" v-bind:disabled="busy" v-if="otherTranslation.translations.length === 1" v-on:click.prevent="setTranslatingString(otherTranslation.translations[0], null, true)">{{ otherTranslation.translations[0] }}</a>
                                    <div v-else>
                                        <div v-for="(str, index) in otherTranslation.translations">
                                            <a href="#" v-bind:disabled="busy" v-on:click.prevent="setTranslatingString(str, index, true)">{{ str }}</a>
                                            <span class="badge bg-secondary">{{ PLURAL_RULE_NAME[PLURAL_RULE_BYINDEX[index]] }}</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span v-if="otherTranslation.approved === null">
                                        <?php
                                        if ($canApprove) {
                                            ?>
                                            <button type="button" class="btn btn-sm btn-success" v-bind:disabled="busy" v-on:click.prevent="processOtherTranslation(otherTranslation, 'approve')"><?= tc('Translation', 'Use this & Approve') ?></button>
                                            <button type="button" class="btn btn-sm btn-small" v-bind:disabled="busy" v-on:click.prevent="processOtherTranslation(otherTranslation, 'deny')"><?= t('Ignore') ?></button>
                                            <?php
                                        } else {
                                            ?>
                                            <span class="badge bg-danger"><?= t('Awaiting approval') ?></span>
                                            <?php
                                        }
                                        ?>
                                    </span>
                                    <span v-else>
                                        <button type="button" class="btn btn-sm btn-info" v-bind:disabled="busy" v-on:click.prevent="processOtherTranslation(otherTranslation, 'reuse')"><?= tc('Translation', 'Use this') ?></button>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php
                if ($enableComments) {
                    ?>
                    <div class="tab-pane show" id="translation-extra-comments" role="tabpanel">
                        <div class="container-fluid">
                            <div class="alert alert-info" v-if="currentTranslation.comments.length === 0">
                                <?= t('No comments found for this string.') ?>
                            </div>
                            <div class="list-group" v-else>
                                <comtra-comment v-for="comment in currentTranslation.comments" v-bind:key="comment.id" v-bind:translation="currentTranslation" v-bind:parent-comment="null" v-bind:comment="comment"></comtra-comment>
                            </div>
                            <div class="row justify-content-center mt-2">
                                <button type="button" class="btn btn-primary btn-sm w-50" v-on:click.prevent="editComment(currentTranslation, null, null)" v-bind:disabled="busy"><?= t('New comment') ?></button>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                ?>
                <div class="tab-pane show" id="translation-extra-suggestions" role="tabpanel">
                    <div class="alert alert-info" v-if="currentTranslation.suggestions.length === 0">
                        <?= t('No similar translations found for this string.') ?>
                    </div>
                    <div class="list-group" v-else>
                        <a class="list-group-item list-group-item-action" v-for="suggestion in currentTranslation.suggestions" href="#" v-on:click.prevent="setTranslatingString(suggestion.translation, null, true)">
                            <span class="badge bg-secondary" v-bind:title="suggestion.source">{{ suggestion.source }}</span>
                            <span class="suggestion-text">{{ suggestion.translation }}</span>
                        </a>
                    </div>
                </div>
                <div class="tab-pane show" id="translation-extra-glossary" role="tabpanel">
                    <div class="container-fluid">
                        <div class="alert alert-info comtra_none" v-if="currentTranslation.glossary.length === 0">
                            <?= t('No glossary terms found for this string.') ?>
                        </div>
                        <div class="row" v-for="glossaryEntry in currentTranslation.glossary">
                            <div class="col-6 text-end" v-bind:title="glossaryEntry.termComments">
                                {{ glossaryEntry.term }}
                                <span class="badge bg-primary rounded-pill" v-if="glossaryEntry.type && GLOSSARY_TYPES[glossaryEntry.type]" v-bind:title="`${GLOSSARY_TYPES[glossaryEntry.type].name}\n${GLOSSARY_TYPES[glossaryEntry.type].description}`">
                                    {{ GLOSSARY_TYPES[glossaryEntry.type].short }}
                                </span>
                                <?php
                                if ($canEditGlossary) {
                                    ?>
                                    <a href="#" title="<?= t('Click to edit') ?>" v-on:click.prevent="editGlossaryEntry(glossaryEntry)" v-bind:disabled="busy"><i class="fas fa-edit"></i></a>
                                    <?php
                                }
                                ?>
                            </div>
                            <div class="col-6" v-bind:title="glossaryEntry.translationComments">
                                <i v-if="glossaryEntry.translation === ''"><?= t('n/a') ?></i>
                                <a v-else href="#" v-bind:disabled="busy" v-on:click.prevent="setTranslatingString(glossaryEntry.translation, null, false)">{{ glossaryEntry.translation }}</a>
                            </div>
                        </div>
                        <?php
                        if ($canEditGlossary) {
                            ?>
                            <div class="row justify-content-center mt-2">
                                <button type="button" class="btn btn-primary btn-sm w-50" v-on:click.prevent="editGlossaryEntry(null)" v-bind:disabled="busy"><?= t('New term') ?></button>
                            </div>
                            <?php
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div id="comtra-dialogs">
        <div class="modal" id="comtra_translation-download">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="<?= h($this->action('download', $locale->getID())) ?>" target="_blank" v-bind:disabled="busy">
                        <?php $token->output('comtra-download-translations' . $locale->getID()) ?>
                        <input type="hidden" name="packageVersion" value="<?= h($packageVersionID) ?>" />
                        <div class="modal-header">
                            <h5 class="modal-title"><?= t('Download translations') ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <fieldset>
                            <legend><?= t('File format') ?></legend>
                            <?php
                            foreach ($translationFormats as $tf) {
                                ?>
                                <div class="form-check">
                                    <input type="radio" class="form-check-input" name="download-format" id="downloadformat-<?= h($tf->getHandle()) ?>" value="<?= h($tf->getHandle()) ?>" required="required" v-bind:disabled="busy" />
                                    <label class="form-check-label" for="downloadformat-<?= h($tf->getHandle()) ?>"><?= h($tf->getName()) ?></label>
                                </div>
                                <?php
                                }
                            ?>
                            </fieldset>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" v-bind:disabled="busy"><?= t('Cancel') ?></button>
                            <input type="submit" class="btn btn-primary" value="<?= t('Download') ?>" v-bind:disabled="busy" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal" id="comtra_translation-upload">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" enctype="multipart/form-data" action="<?= $this->action('upload', $locale->getID()) ?>" onsubmit="if (this.already) return; this.already = true">
                        <?php $token->output('comtra-upload-translations' . $locale->getID()) ?>
                        <input type="hidden" name="packageVersion" value="<?= h($packageVersionID) ?>" />
                        <div class="modal-header">
                            <h5 class="modal-title"><?= t('Upload translations') ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="comtra_upload-translations-file"><?= t('File to be imported') ?></label>
                                <input type="file" class="form-control" name="file" id="comtra_upload-translations-file" required="required" v-bind:disabled="busy" />
                            </div>
                            <?php
                            if ($canApprove) {
                                ?>
                                    <div class="form-check mt-3">
                                        <input type="checkbox" class="form-check-input" name="all-fuzzy" value="1" checked="checked" id="allFuzzy" v-bind:disabled="busy" />
                                        <label class="form-check-label" for="allFuzzy"><?= t('Consider all the translations as fuzzy') ?></label>
                                        <div class="small text-muted"><?= t('If checked, all the translations will be considered as fuzzy (that is: not approved).') ?></div>
                                    </div>
                                    <div class="form-check mt-3">
                                        <input type="checkbox" class="form-check-input" name="fuzzy-unapprove" value="1" id="fuzzyUnapprove" v-bind:disabled="busy" />
                                        <label class="form-check-label" for="fuzzyUnapprove"><?= t('Unapprove fuzzy translations') ?></label>
                                        <div class="small text-muted"><?= t('If checked, fuzzy translations will mark currently approved strings as not approved.') ?></div>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" v-bind:disabled="busy"><?= t('Cancel') ?></button>
                            <input type="submit" class="btn btn-primary" v-bind:disabled="busy" value="<?= t('Upload') ?>" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="modal" id="comtra_translation-filter">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= t('Advanced Search') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="form-label"><?= t('Search text:') ?></div>
                            <div class="input-group">
                                <input type="text" class="form-control" v-model="search.text" />
                                <div class="input-group-text">
                                    <input class="form-check-input mt-0" type="checkbox" v-model="search.caseSensitive" id="ct-search-casesensitive" v-bind:disabled="busy" />
                                    &nbsp;
                                    <label for="ct-search-casesensitive"><?= t('Case sensitive') ?></label>
                                </div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="form-label"><?= t('Search in:') ?></div>
                            <div class="btn-group" role="group">
                                <input type="checkbox" class="btn-check" id="ct-search-in-source" v-model="search.source" v-bind:disabled="busy" />
                                <label class="btn btn-outline-primary" for="ct-search-in-source"><?= t('Source strings') ?></label>
                                <input type="checkbox" class="btn-check" id="ct-search-in-translation" v-model="search.translation" v-bind:disabled="busy" />
                                <label class="btn btn-outline-primary" for="ct-search-in-translation"><?= t('Translations') ?></label>
                                <input type="checkbox" class="btn-check" id="ct-search-in-context" v-model="search.context" v-bind:disabled="busy" />
                                <label class="btn btn-outline-primary" for="ct-search-in-context"><?= t('Context') ?></label>
                            </div>
                        </div>
                        <!--
                        <div class="row mb-3">
                            <label class="form-label"><?= t('Translator') ?></label>
                            <select class="form-control" v-model="search.translator">
                                <option v-bind:value="false"><?= h(t('<any>')) ?></option>
                                <option v-for="t in ALL_TRANSLATORS" v-bind:value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        -->
                        <div class="row mb-3">
                            <div class="form-label"><?= t('Translated state') ?></div>
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="ct-search-translatedstate" id="ct-search-translatedstate-all" v-model="search.translated" v-bind:value="null" v-bind:disabled="busy" />
                                <label class="btn btn-outline-primary" for="ct-search-translatedstate-all"><?= t('Show all strings') ?></label>
                                <input type="radio" class="btn-check" name="ct-search-translatedstate" id="ct-search-translatedstate-translated" v-model="search.translated" v-bind:value="true" v-bind:disabled="busy" />
                                <label class="btn btn-outline-primary" for="ct-search-translatedstate-translated"><?= t('Show only translated strings') ?></label>
                                <input type="radio" class="btn-check" name="ct-search-translatedstate" id="ct-search-translatedstate-untranslated" v-model="search.translated" v-bind:value="false" v-bind:disabled="busy" />
                                <label class="btn btn-outline-primary" for="ct-search-translatedstate-untranslated"><?= t('Show only untranslated strings') ?></label>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="form-label"><?= t('Approval state') ?></div>
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="ct-search-approvalstate" id="ct-search-approvalstate-all" v-model="search.approved" v-bind:value="null" v-bind:disabled="busy" />
                                <label class="btn btn-outline-primary" for="ct-search-approvalstate-all"><?= t('Show all strings') ?></label>
                                <input type="radio" class="btn-check" name="ct-search-approvalstate" id="ct-search-approvalstate-approved" v-model="search.approved" v-bind:value="true" v-bind:disabled="busy" />
                                <label class="btn btn-outline-primary" for="ct-search-approvalstate-approved"><?= t('Show only approved strings') ?></label>
                                <input type="radio" class="btn-check" name="ct-search-approvalstate" id="ct-search-approvalstate-unapproved" v-model="search.approved" v-bind:value="false" v-bind:disabled="busy" />
                                <label class="btn btn-outline-primary" for="ct-search-approvalstate-unapproved"><?= t('Show only unapproved strings') ?></label>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="form-label"><?= t('Plural') ?></div>
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="ct-search-plural" id="ct-search-plural-all" v-model="search.plural" v-bind:value="null" v-bind:disabled="busy" />
                                <label class="btn btn-outline-primary" for="ct-search-plural-all"><?= t('Show all strings') ?></label>
                                <input type="radio" class="btn-check" name="ct-search-plural" id="ct-search-plural-yes" v-model="search.plural" v-bind:value="true" v-bind:disabled="busy" />
                                <label class="btn btn-outline-primary" for="ct-search-plural-yes"><?= t('Show only plural strings') ?></label>
                                <input type="radio" class="btn-check" name="ct-search-plural" id="ct-search-plural-no" v-model="search.plural" v-bind:value="false" v-bind:disabled="busy" />
                                <label class="btn btn-outline-primary" for="ct-search-plural-no"><?= t('Show singular strings') ?></label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-danger" v-bind:disabled="busy" v-on:click.prevent="resetSearchFilters"><?= t('Reset') ?></button>
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal" v-bind:disabled="busy" v-on:click="applySearchFilters"><?= t('Apply') ?></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal" id="comtra_translation-allplaces">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= t('String usage') ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="list-group">
                            <div v-for="place in allPlaces" class="list-group-item active">
                                {{ place.packageVersionDisplayName }}
                                <div class="list-group-item" v-if="place.comments.length !== 0">
                                    <span class="badge bg-info"><?= t('Comments') ?></span>
                                    <div v-for="placeComment in place.comments">{{ placeComment }}</div>
                                </div>
                                <div class="list-group-item" v-if="place.references.length !== 0">
                                    <span class="badge bg-success"><?= t('References') ?></span>
                                    <div v-for="placeReference in place.references">
                                        <a v-if="Array.isArray(placeReference)" class="list-group-item list-group-item-action" v-bind:title="placeReference[0]" v-bind:href="placeReference[0]" target="_blank">{{ placeReference[1] }}</a>
                                        <span v-else>{{ placeReference }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" data-bs-dismiss="modal"><?= t('Close') ?></button>
                    </div>
                </div>
            </div>
        </div>
        <?php
        if ($enableComments) {
            ?>
            <div class="modal" id="comtra_comment-edit">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <span v-if="editingComment.comment !== null"><?= t('Edit comment') ?></span>
                                <span v-else-if="editingComment.parentComment === null"><?= t('New comment') ?></span>
                                <span v-else><?= t('Add reply') ?></span>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="container-fluid">
                                <div class="row mb-3" v-if="editingComment.parentComment === null">
                                    <label class="form-label" for="comtra_comment-edit-isglobal"><b><?= t('Comment visibility') ?></b></label>
                                    <select class="form-control" id="comtra_comment-edit-isglobal" v-bind:disabled="busy" v-model="editingComment.isGlobal">
                                        <option v-if="editingComment.isGlobal === null" v-bind:value="null"><?= h(t('<please select>')) ?></option>
                                        <option v-bind:value="false"><?= h(t('This is a comment only for %s', $locale->getDisplayName())) ?></option>
                                        <option v-bind:value="true"><?= h(t('This is a comment for all languages')) ?></option>
                                    </select>
                                </div>
                                <div class="row mb-3">
                                    <label class="form-label" for="comtra_comment-edit-newcommenttext"><b><?= t('Comment') ?></b></label>
                                    <textarea class="form-control" id="comtra_comment-edit-newcommenttext" rows="5" v-model.trim="editingComment.newCommentText" v-bind:disabled="busy"></textarea>
                                    <a href="https://commonmark.org/help" target="_blank" class="small text-end"><?= t('Markdown syntax') ?></a>
                                </div>
                                <div class="row mb-3">
                                    <label class="form-label"><b><?= t('Preview') ?></b></label>
                                    <div class="form-control" v-html="newCommentHtml" style="min-height: 70px"></div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" v-bind:disabled="busy"><?= t('Cancel') ?></button>
                            <button type="button" class="btn btn-danger" v-bind:disabled="busy" v-if="editingComment.comment !== null" v-on:click.prevent="deleteComment"><?= t('Delete') ?></button>
                            <button type="button" class="btn btn-primary" v-on:click.prevent="saveComment" v-bind:disabled="busy"><?= t('Save') ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
        if ($enableManagingNotifications) {
            $notifyData = [
                'newVersions' => $packageSubscription->isNotifyNewVersions(),
                'allExistingVersions' => null,
                'packageVersionSubscriptions' => [],
            ];
            foreach ($packageVersionSubscriptions as $pvs) {
                if ($notifyData['allExistingVersions'] === null) {
                    $notifyData['allExistingVersions'] = $pvs->isNotifyUpdates() ? 'yes' : 'no';
                } elseif ($pvs->isNotifyUpdates()) {
                    if ($notifyData['allExistingVersions'] !== 'yes') {
                        $notifyData['allExistingVersions'] = 'custom';
                        break;
                    }
                } else {
                    if ($notifyData['allExistingVersions'] !== 'no') {
                        $notifyData['allExistingVersions'] = 'custom';
                        break;
                    }
                }
            }
            foreach ($packageVersionSubscriptions as $pvs) {
                $pv = $pvs->getPackageVersion();
                $notifyData['packageVersionSubscriptions'][] = [
                    'id' => $pv->getID(),
                    'name' => $pv->getDisplayVersion(),
                    'checked' => $pvs->isNotifyUpdates(),
                ];
            }
            ?>
            <div class="modal" id="comtra_translation-notifications">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><?= t('Package notifications') ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label"><?= t('New versions') ?></label>
                                <div class="col-sm-9">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" role="switch" id="comtra-notify-newversions" v-model="notify.newVersions" v-bind:disabled="busy" />
                                        <label class="form-check-label" for="comtra-notify-newversions"><?= t('Notify me when new versions of this package are available') ?></label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <label class="col-sm-3 col-form-label" for="comtra-notify-existingversions-all"><?= t('Existing versions') ?></label>
                                <div class="col-sm-9">
                                    <select class="form-control" id="comtra-notify-existingversions-all" v-model="notify.allExistingVersions" v-bind:disabled="busy">
                                        <option value="yes"><?= t('Alert me when any version changes') ?></option>
                                        <option value="no"><?= t('Never alert me') ?></option>
                                        <option value="custom"><?= t('Alert me for specific version changes') ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="row mb-3" v-if="notify.allExistingVersions === 'custom'">
                                <div class="col-sm-3 col-form-label">
                                    <?= t('Version-specific alerts') ?>
                                    <div class="row">
                                        <div class="col p-2">
                                            <a class="btn btn-secondary btn-sm w-100" v-on:click.prevent="checkAllPackageVersionSubscriptions(true)" v-bind:disabled="busy"><?= t('all') ?></a>
                                        </div>
                                        <div class="col p-2">
                                            <a class="btn btn-secondary btn-sm w-100" v-on:click.prevent="checkAllPackageVersionSubscriptions(false)" v-bind:disabled="busy"><?= t('none') ?></a>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-9">
                                    <div class="form-check form-switch" v-for="pvs in notify.packageVersionSubscriptions">
                                        <input type="checkbox" class="form-check-input" role="switch" v-bind:id="`pvs${pvs.id}`" v-model="pvs.checked" v-bind:disabled="busy" />
                                        <label class="form-check-label" v-bind:for="`pvs${pvs.id}`">{{ pvs.name }}</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" v-bind:disabled="busy"><?= t('Cancel') ?></button>
                            <button type="button" class="btn btn-primary" v-on:click.prevent="saveNotifications" v-bind:disabled="busy"><?= t('Save') ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        } else {
            $notifyData = null;
        }
        if ($canEditGlossary) {
            ?>
            <div class="modal" id="comtra_glossaryentry-edit">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <span v-if="currentGlossaryEntry === null"><?= t('Add Glossary Entry') ?></span>
                                <span v-else><?= t('Edit Glossary Entry') ?></span>
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <fieldset>
                                <legend><?= t('Info shared with all languages') ?></legend>
                                <div class="row mb-3">
                                    <label for="comtra_glossaryentry-edit-term" class="col-sm-2 col-form-label"><?= t('Term') ?></label>
                                    <div class="col-sm-10">
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="comtra_glossaryentry-edit-term" v-model.trim="editingGlossaryEntry.term" maxlength="255" v-bind:disabled="busy" />
                                            <div class="input-group-text">*</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="comtra_glossaryentry-edit-type" class="col-sm-2 col-form-label"><?= t('Type') ?></label>
                                    <div class="col-sm-10">
                                        <select class="form-control" v-bind:disabled="busy" v-model="editingGlossaryEntry.type">
                                            <option v-bind:value="''"><?= tc('Type', 'none') ?></option>
                                            <?php
                                            foreach (GlossaryEntryType::getTypesInfo() as $typeHandle => $typeInfo) {
                                                ?><option v-bind:value="<?= h(json_encode($typeHandle)) ?>"><?= h($typeInfo['name']) ?></option><?php
                                            }
                                            ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="comtra_glossaryentry-edit-termcomments" class="col-sm-2 col-form-label"><?= t('Comments') ?></label>
                                    <div class="col-sm-10">
                                        <textarea class="form-control" v-model.trim="editingGlossaryEntry.termComments" v-bind:disabled="busy" cols="3"></textarea>
                                    </div>
                                </div>
                            </fieldset>
                            <fieldset>
                                <legend><?= h(t('Info only for %s', $locale->getDisplayName())) ?></legend>
                                <div class="row mb-3">
                                    <label for="comtra_glossaryentry-edit-translation" class="col-sm-2 col-form-label"><?= t('Translation') ?></label>
                                    <div class="col-sm-10">
                                        <input type="text" class="form-control" id="comtra_glossaryentry-edit-translation" v-model.trim="editingGlossaryEntry.translation" v-bind:disabled="busy" />
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <label for="comtra_glossaryentry-edit-translationcomments" class="col-sm-2 col-form-label"><?= t('Comments') ?></label>
                                    <div class="col-sm-10">
                                        <textarea class="form-control" v-model.trim="editingGlossaryEntry.translationComments" v-bind:disabled="busy" cols="3"></textarea>
                                    </div>
                                </div>
                            </fieldset>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" v-bind:disabled="busy"><?= t('Cancel') ?></button>
                            <button type="button" class="btn btn-danger" v-bind:disabled="busy" v-if="currentGlossaryEntry !== null" v-on:click.prevent="deleteGlossaryEntry"><?= t('Delete') ?></button>
                            <button type="button" class="btn btn-primary" v-bind:disabled="busy" v-on:click.prevent="saveGlossaryEntry"><?= t('Save') ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
        if (isset($showDialogAtStartup)) {
            ?>
            <div class="modal" id="comtra_startup-dialog">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title"><?= t('Message') ?></h4>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <?= $showDialogAtStartup ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" data-bs-dismiss="modal"><?= t('Close') ?></button>
                        </div>
                    </div>
                </div>
            </div>
            <script>$(document).ready(function() {
                setTimeout(function() {
                    let modal;
                    var el = document.getElementById('comtra_startup-dialog');
                    el.addEventListener('hidden.bs.modal', function() {
                        modal.dispose();
                        $('#comtra_startup-dialog').remove();
                    });
                    modal = new bootstrap.Modal(el);
                    modal.show();
                }, 100);
            });</script>
            <?php
        }
        ?>
    </div>

</div>

<div class="d-none" id="comtra-sourcetext-template">
    <div class="source-translation form-control">
        <div><span v-for="chunk in getChunks()"><code v-if="chunk.copyable" v-on:click.prevent="copyChunk(chunk.text)" title="<?= t('Click to copy') ?>">{{ chunk.text }}</code><span v-else>{{ chunk.text}}</span></span></div>
        <button type="button" class="btn btn-sm btn-outline-secondary" title="<?= t('Copy to translation') ?>" v-on:click.prevent="copyWholeText"><i class="fas fa-copy"></i></button>
    </div>
</div>
</div>
<?php
if ($enableComments) {
    ?>
    <div class="d-none" id="comtra-comment-template">
        <div class="list-group-item">
            <div class="container-fuid small">
                <span class="text-muted">{{ comment.date }} by <span v-html="comment.by"></span></span>
                <div class="float-end">
                    <a v-if="comment.mine" href="#" title="Edit" v-on:click.prevent="edit"><i class="fas fa-pencil-alt"></i></a>
                    <a href="#" style="margin-left: 10px" title="Reply" v-on:click.prevent="reply"><i class="fa fa-reply"></i></a>
                </div>
            </div>
            <div v-html="comment.textFormatted"></div>
            <div v-if="comment.comments.length !== 0">
                <comtra-comment v-for="childComment in comment.comments" v-bind:key="childComment.id" v-bind:translation="translation" v-bind:parent-comment="comment" v-bind:comment="childComment"></comtra-comment>
            </div>
        </div>
    </div>
    <?php
}
?>

<script>$(document).ready(function() {
'use strict';

function TranslationState(translation, translating, translatingApproved)
{
    this.wasTranslated = translation.translations !== null;
    this.isTranslated = translating.some((str) => str.replace(/^\s+|\s+$/g, '') !== '');
    if (this.wasTranslated !== this.isTranslated) {
        this.isDirty = true;
    } else if (!this.wasTranslated) {
        this.isDirty = false;
    } else if (translation.isApproved !== translatingApproved) {
        this.isDirty = true;
    } else {
        this.isDirty = translation.translations.some((str, index) => translating[index] !== str);
    }
    this.errorMessage = '';
    this.errorTranslatingIndex = null;
    if (this.isDirty) {
        if (this.isTranslated) {
            for (const index in translating) {
                if (translating[index].replace(/^\s+|\s+$/g, '') === '') {
                    this.errorMessage = <?= json_encode(t('Please fill-in the translation')) ?>;
                    this.errorTranslatingIndex = index;
                    break;
                }
            }
        }
    }
}

const convertMarkdownToHtml = (function() {
    /**
     * @see https://markdown-it.github.io/markdown-it/
     */
    const MarkdownIt = window.markdownit({
        // HTML tags in source?
        html: false,
        // Use '/' to close single tags (<br />)?
        xhtmlOut: true,
        // Convert '\n' in paragraphs into <br />?
        breaks: true,
        // Autoconvert URL-like text to links?
        linkify: true,
        // Enable some language-neutral replacement + quotes beautification?
        typographer: true,
    });
    return function (markdown) {
        return $('<div />')
            .html(MarkdownIt.render(markdown))
            .find('a').attr('target', '_blank').end()
            .html()
        ;
    };
})();

const convertTextToHtml = (function() {
    const div = document.createElement('div');
    return function (text) {
        text = text ? text.toString() : '';
        const lines = [];
        text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n').forEach((line) => {
            div.innerText = line;
            lines.push(div.innerHTML);
        });
        return lines.join('<br />');
    };
})();

const UrlHash = (function() {
    const AVAILABLE_EXTRA_TABS = (function() {
        const result = [];
        $('#translation-extra-tabs>button[data-bs-target]').each((_, el) => {
            const prop = $(el).attr('data-bs-target');
            const match = typeof prop === 'string' ? /^#translation-extra-(.+)$/.exec(prop) : null;
            if (match) {
                result.push(match[1]);
            }
        });
        return result;
    })();
    function getDictionary() {
        const hash = window.location.hash || '';
        const tidMatch = /(?:^#|,)tid:([1-9]\d*)(?:$|,)/.exec(hash);
        const xtabMatch = /(?:^#|,)xtab:([\w\-]+)(?:$|,)/.exec(hash);
        return {
            tid: tidMatch ? parseInt(tidMatch[1], 10) : null,
            xtab: xtabMatch && AVAILABLE_EXTRA_TABS.indexOf(xtabMatch[1]) >= 0 ? xtabMatch[1] : null,
        };
    };
    function setDictionaryKey(key, value) {
        const dictionary = getDictionary();
        if (value === undefined || value === '' || value === false) {
            value = null;
        }
        if (dictionary[key] === value) {
            return;
        }
        dictionary[key] = value;
        const chunks = [];
        for (const key in dictionary) {
            if (dictionary[key] !== null) {
                chunks.push(`${key}:${dictionary[key]}`);
            }
        }
        if (chunks.length === 0) {
            window.history.replaceState(null, '', window.location.pathname);
        } else {
            window.history.replaceState(null, '', '#' + chunks.join(','));
        }
    }
    return Object.defineProperties({}, {
        translationID: {
            get: () => getDictionary().tid,
            set: (newValue) => setDictionaryKey('tid', newValue),
        },
        extraTabKey: {
            get: () => getDictionary().xtab,
            set: (newValue) => setDictionaryKey('xtab', newValue),
        },
    });
})();

const ajax = (function() {
    const AJAX_KEYS = <?= json_encode($ajaxKeys) ?>;

    function resolveErrorMessage(xhr, status, error) {
        const json = xhr ? xhr.responseJSON : null;
        if (json) {
            if (typeof json.error === 'string') {
                return json.error;
            }
            if (json.error && typeof json.error.message === 'string') {
                return json.error.message;
            }
            if (Array.isArray(json.errors) && json.errors.length > 0) {
                if (typeof json.errors[0] === 'string') {
                    return json.errors[0];
                }
                if (json.errors[0] && typeof json.errors[0].message === 'string') {
                    return json.errors[0].message;
                }
            }
        }
        if (typeof error === 'string' && error !== '') {
            return error.responseText;
        }
        if (status && status.toString) {
            return status.toString();
        }
        if (error && error.toString) {
            return error.toString();
        }
        return '?Unrecognized server error';
    };
    return function(key, data, cb) {
        $.ajax({
            data: $.extend(true,
                {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: AJAX_KEYS[key].token,
                },
                data
            ),
            dataType: 'json',
            method: 'POST',
            url: AJAX_KEYS[key].url,
        })
        .done(function(data) {
            cb(true, data);
        })
        .fail(function(xhr, status, error) {
            cb(false, resolveErrorMessage(xhr, status, error));
        });
    };
})();

Vue.component('ComtraSourcetext', {
    template: document.getElementById('comtra-sourcetext-template').innerHTML,
    props: {
        format: {
            type: String,
            required: true,
        },
        text: {
            type: String,
            required: true,
        },
    },
    methods: {
        copyWholeText: function() {
            this.$emit('copy-whole', this.text);
        },
        copyChunk: function(s) {
            this.$emit('copy-chunk', s);
        },
        getChunks() {
            if (typeof this.text !== 'string' || this.text === '') {
                return [];
            }
            switch (this.format) {
                case <?= json_encode(StringFormat::PHP) ?>:
                    return this.getPHPChunks();
                default:
                    return [{copyable: false, text: this.text}];
            }
        },
        getPHPChunks() {
            let s = this.text;
            if (typeof s !== 'string' || s === '') {
                return [];
            }
            const chunks = [];
            let prevChunk = null;
            function addTextChunk(s) {
                if (s === '') {
                    return;
                }
                if (prevChunk === null || prevChunk.copyable) {
                    prevChunk = {copyable: false, text: s};
                    chunks.push(prevChunk);
                } else {
                    prevChunk.text += s;
                }
            }
            function addCopyiableChunk(s) {
                if (s === '') {
                    return;
                }
                prevChunk = {copyable: true, text: s};
                chunks.push(prevChunk);
            }
            while (s !== '') {
                const match = s.match(/^(.*?)(<\/?[A-Za-z]+|%(?:\d+\$)?[\-+ 0']*\d*(?:\.\d*)*[%bcdeEfFgGhHosuxX])/ms);
                if (match === null) {
                    addTextChunk(s);
                    break;
                }
                s = s.substr(match[0].length);
                addTextChunk(match[1]);
                const special = match[2];
                if (special.charAt(0) === '<') {
                    let endTagIndex = s.indexOf('>');
                    if (endTagIndex < 0) {
                        addTextChunk(special);
                    } else {
                        addCopyiableChunk(special + s.substr(0, endTagIndex + 1));
                        s = s.substr(endTagIndex + 1);
                    }
                } else {
                    addCopyiableChunk(special);
                }
            }
            return chunks;
        },
    },
});
<?php
if ($enableComments) {
    ?>
    Vue.component('ComtraComment', {
        template: document.getElementById('comtra-comment-template').innerHTML,
        props: {
            translation: {
                type: Object,
                required: true,
            },
            parentComment: {
                type: Object,
            },
            comment: {
                type: Object,
                required: true,
            },
        },
        methods: {
            convertMarkdownToHtml: function(markdown) {
                return convertMarkdownToHtml(markdown);
            },
            edit: function() {
                this.$root.editComment(this.translation, this.parentComment, this.comment);
            },
            reply: function() {
                this.$root.editComment(this.translation, this.comment, null);
            },
        },
    });
    <?php
}
?>

new Vue({
    el: '#app',
    data: function() {
        const result = <?= json_encode([
            'ALL_TRANSLATORS' => $allTranslators,
            'ITEMS_PER_PAGE' => 100,
            'PLURAL_RULE_NAME' => [
                'zero' => tc('PluralCase', 'Zero'),
                'one' => tc('PluralCase', 'One'),
                'two' => tc('PluralCase', 'Two'),
                'few' => tc('PluralCase', 'Few'),
                'many' => tc('PluralCase', 'Many'),
                'other' => tc('PluralCase', 'Other'),
            ],
            'PLURAL_RULE_BYINDEX' => array_keys($pluralCases),
            'PLURAL_EXAMPLES' => $pluralCases,
            'GLOSSARY_TYPES' => GlossaryEntryType::getTypesInfo(),
            'busy' => false,
            'notify' => $notifyData,
            'search' => [
                'text' => '',
                'caseSensitive' => false,
                'source' => true,
                'translation' => true,
                'context' => true,
                'translated' => null,
                'approved' => null,
                'plural' => null,
            ],
            'translations' => $translations,
            'filteredTranslations' => [],
            'pageIndex' => 0,
            'currentTranslation' => null,
            'translating' => [],
            'translatingApproved' => null,
            'translatingIndex' => 0,
            'allPlaces' => [],
            'currentGlossaryEntry' => null,
            'editingGlossaryEntry' => [
                'term' => '',
                'type' => '',
                'termComments' => '',
                'translation' => '',
                'translationComments' => '',
            ],
            'editingComment' => [
                'translation' => null,
                'parentComment' => null,
                'comment' => null,
                'isGlobal' => null,
                'newCommentText' => '',
            ],
            'watchUrlHash' => true,
        ]) ?>;
        result.translations.forEach((translation) => {
            translation.isPlural = translation.hasOwnProperty('originalPlural');
            if (typeof translation.context !== 'string') {
                translation.context = '';
            }
            if (translation.translations) {
                translation.isTranslated = true;
            } else {
                translation.translations = null;
                translation.isTranslated = false;
            }
            this.updateTranslationRowClass(translation);
        })
        return result;
    },
    mounted: function() {
        this.filteredTranslations = this.translations;
        this.inspectUrlHash();
        window.addEventListener('hashchange', () => this.inspectUrlHash(), false);
        $("#app").on('show.bs.tab', '#translation-extra-tabs>[data-bs-toggle="tab"]', (e) => {
            const prop = $(e.target).attr('data-bs-target');
            const match = typeof prop === 'string' ? /^#translation-extra-(.+)$/.exec(prop) : null;
            if (match) {
                this.watchUrlHash = false;
                UrlHash.extraTabKey = match[1];
                this.watchUrlHash = true;
            }
        });
        window.addEventListener('beforeunload', (e) => {
            if (this.unsavedChanges) {
                e.preventDefault();
                e.returnValue = 'Confirm';
            }
        }, false);
    },
    methods: {
        browseTo: function(url) {
            if (this.busy || !url) {
                return;
            }
            window.location.href = url;
            this.busy = true;
        },
        updateTranslationRowClass: function(translation) {
            translation.rowClass = (translation.isTranslated ? 'table-success' : 'table-danger') + (translation === this.currentTranslation ? ' table-active' : '');
        },
        resetSearchFilters: function() {
            this.search.text = '';
            this.search.caseSensitive = false;
            this.search.source = true;
            this.search.translation = true;
            this.search.context = true;
            this.search.translated = null;
            this.search.approved = null;
            this.search.plural = null;
        },
        toggleSearchTranslated: function() {
            const CASES = [null, false, true];
            this.search.translated = CASES[(CASES.indexOf(this.search.translated) + 1) % CASES.length];
            this.applySearchFilters();
        },
        applySearchFilters: function() {
            if (this.busy) {
                return;
            }
            let filteredTranslations = this.translations;
            if (this.search.translated !== null) {
                filteredTranslations = filteredTranslations.filter((translation) => translation.isTranslated === this.search.translated);
            }
            if (this.search.approved !== null) {
                filteredTranslations = filteredTranslations.filter((translation) => translation.isTranslated && translation.isApproved === this.search.approved);
            }
            if (this.search.plural !== null) {
                filteredTranslations = filteredTranslations.filter((translation) => translation.isPlural === this.search.plural);
            }
            if (this.search.text !== '' && (this.search.source || this.search.translation || this.search.context)) {
                const textFixer = this.search.caseSensitive ? (v) => v : (v) => v.toLowerCase();
                const searchText = textFixer(this.search.text);
                filteredTranslations = filteredTranslations.filter((translation) => {
                    if (this.search.source) {
                        if (textFixer(translation.original).includes(searchText)) {
                            return true;
                        }
                        if (translation.isPlural && textFixer(translation.originalPlural).includes(searchText)) {
                            return true;
                        }
                    }
                    if (this.search.translation && translation.isTranslated && textFixer(translation.translations.join('\x01')).includes(searchText)) {
                        return true;
                    }
                    if (this.search.context && textFixer(translation.context).includes(searchText)) {
                        return true;
                    }
                    return false;
                });
            }
            this.filteredTranslations = filteredTranslations;
            this.pageIndex = 0;
        },
        gotoPage: function(num, isDelta) {
            if (this.busy) {
                return;
            }
            const newPageIndex = isDelta ? (this.pageIndex + num) : num;
            if (!isNaN(newPageIndex) && newPageIndex >= 0 && newPageIndex < this.totalPages) {
                this.pageIndex = newPageIndex;
            }
        },
        setCurrentTranslation: function(translation, setPage) {
            if (this.busy || translation === this.currentTranslation) {
                return;
            }
            if (this.unsavedChanges && !window.confirm(<?= json_encode(implode("\n", [t('The current item has changed.'), t('If you proceed you will lose your changes.'), '', t('Do you want to proceed anyway?')])) ?>)) {
                return;
            }
            if (!translation) {
                this.currentTranslation = null;
                return;
            }
            this.busy = true;
            ajax(
                'loadTranslation',
                {
                    translatableID: translation.id,
                    packageVersionID: <?= json_encode($packageVersionID) ?>,
                },
                (ok, data) => {
                    this.busy = false;
                    if (!ok) {
                        window.alert(data);
                        return;
                    }
                    this.currentTranslation = null;
                    translation.isMultiline = (translation.original + (translation.isPlural ? translation.originalPlural : '')).indexOf('\n') >= 0;
                    this.updateTranslationTranslations(translation, data.translations.current);
                    if (data.translations.current) {
                        delete data.translations.current.translations;
                        delete data.translations.current.isTranslated;
                        delete data.translations.current.isApproved;
                        translation.currentInfo = data.translations.current;
                    } else {
                        translation.currentInfo = null;
                    }
                    translation.format = data.format;
                    translation.otherTranslations = data.translations.others;
                    translation.extractedComments = data.extractedComments;
                    translation.references = data.references;
                    <?php
                    if ($enableComments) {
                        ?>
                        data.comments.forEach((comment) => {
                            this.finalizeComment(comment, true);
                        });
                        translation.comments = data.comments;
                        translation.totalComments = this.countTotalComments(translation);
                        <?php
                    }
                    ?>
                    translation.suggestions = data.suggestions;
                    translation.glossary = data.glossary;
                    this.currentTranslation = translation;
                    if (setPage) {
                        const translationIndex = this.filteredTranslations.indexOf(translation);
                        if (translationIndex >= 0) {
                            this.pageIndex = Math.floor(translationIndex / this.ITEMS_PER_PAGE);
                        }
                    }
                },
            );
        },
        setTranslatingString: function(text, translatingIndex, replace) {
            if (this.busy) {
                return;
            }
            if (typeof translatingIndex !== 'number') {
                translatingIndex = this.translatingIndex;
            }
            if (this.translatingIndex !== translatingIndex) {
                this.translatingIndex = translatingIndex;
                this.$nextTick(() => {
                    this.setTranslatingString(text, translatingIndex, replace);
                });
                return;
            }
            const input = this.$refs.translating;
            if(replace) {
                if (this.translating[translatingIndex] !== text || input.value !== text) {
                    this.translating[translatingIndex] = text;
                    input.value = text;
                }
            } else {
                if (text !== '') {
                    const currentText = this.translating[translatingIndex];
                    if ('selectionStart' in input && 'selectionEnd' in input) {
                        const before = currentText.substring(0, input.selectionStart);
                        var after = currentText.substring(input.selectionEnd);
                        input.value = this.translating[translatingIndex] = before + text + after;
                        input.selectionStart = before.length;
                        input.selectionEnd = before.length + text.length;
                    } else if (window.document.selection && window.document.selection.createRange) {
                        input.focus();
                        document.selection.createRange().text = text;
                        this.translating[translatingIndex] = input.value;
                    } else {
                        input.value = this.translating[translatingIndex] = text;
                    }
                }
            }
            this.$nextTick(() => {
                this.$refs.translating.focus();
            });
        },
        processOtherTranslation: function (otherTranslation, operation) {
            this.processTranslation(this.currentTranslation, operation, {translationID: otherTranslation.id});
        },
        saveTranslation: function(markAsApproved, gotoNext) {
            if (this.busy || this.currentTranslation === null) {
                return;
            }
            if (this.translationState.isTranslated && typeof markAsApproved === 'boolean') {
                this.translatingApproved = markAsApproved;
            }
            const completed = () => {
                if (gotoNext) {
                    let nextTranslation = null;
                    const translationIndex = this.filteredTranslations.indexOf(this.currentTranslation);
                    if (translationIndex >= 0) {
                        const nextTranslationIndex = translationIndex + 1;
                        if (nextTranslationIndex < this.filteredTranslations.length) {
                            nextTranslation = this.filteredTranslations[nextTranslationIndex];
                        }
                    }
                    this.setCurrentTranslation(nextTranslation, true);
                }
            };
            if (!this.unsavedChanges) {
                completed();
                return;
            }
            if (this.translationState.errorMessage !== '') {
                window.alert(this.translationState.errorMessage);
                if (this.translationState.errorTranslatingIndex !== null) {
                    this.translatingIndex = this.translationState.errorTranslatingIndex;
                    this.$nextTick(() => {
                        this.$refs.translating.focus();
                    });
                }
                return;
            }
            if (this.translationState.isTranslated) {
                const translatedB64 = this.translating.map((s) => window.btoa(window.encodeURIComponent(s)));
                this.processTranslation(this.currentTranslation, 'save-current', {translatedB64, approved: this.translatingApproved ? 1 : 0}, completed);
            } else {
                this.processTranslation(this.currentTranslation, 'clear-current', {}, completed);
            }
        },
        processTranslation: function (translation, operation, send, cbOk, cbKo) {
            let wasBusy = this.busy;
            if (!wasBusy) {
                this.busy = true;
            }
            ajax(
                'processTranslation',
                $.extend(true, {
                    packageVersionID: <?= json_encode($packageVersionID) ?>,
                    id: translation.id,
                    operation,
                }, send),
                (ok, response) => {
                    if (!wasBusy) {
                        this.busy = false;
                    }
                    if (!ok) {
                        if (cbKo) {
                            cbKo(response);
                        } else {
                            window.alert(response);
                        }
                        return;
                    }
                    if (response.hasOwnProperty('current')) {
                        this.updateTranslationTranslations(translation, response.current);
                    }
                    if (response.hasOwnProperty('others')) {
                        translation.otherTranslations = response.others;
                    }
                    if (response.hasOwnProperty('message')) {
                        window.alert(response.message);
                    }
                    if (cbOk) {
                        cbOk(response);
                    }
                }
            );
        },
        showAllPlaces: function(translation) {
            if (this.busy) {
                return;
            }
            this.busy = true;
            ajax(
                'loadAllPlaces',
                {
                    id: translation.id,
                },
                (ok, response) => {
                    this.busy = false;
                    if (!ok) {
                        window.alert(response);
                        return;
                    }
                    this.allPlaces.splice(0, this.allPlaces.length);
                    for (const p of response) {
                        this.allPlaces.push(p);
                    }
                    if (this.allPlaces.length === 0) {
                        window.alert(<?= json_encode(t('This string is not used in any package.')) ?>);
                        return;
                    }
                    const modal = new bootstrap.Modal('#comtra_translation-allplaces');
                    modal.show();
                }
            );
        },
        inspectUrlHash: function() {
            if (this.busy || !this.watchUrlHash) {
                return;
            }
            const translationID = UrlHash.translationID;
            if (translationID !== null) {
                for (const translation of this.filteredTranslations) {
                    if (translation.id === translationID) {
                        this.setCurrentTranslation(translation, true);
                        break;
                    }
                }
            }
            const extraTabKey = UrlHash.extraTabKey;
            if (extraTabKey !== null) {
                const $tab = $(`#translation-extra-tabs>[data-bs-target="#translation-extra-${extraTabKey}"]`);
                if ($tab.length === 1) {
                    $tab.trigger('click');
                }
            }
        },
        updateTranslationTranslations: function(translation, translatedData) {
            if (translatedData) {
                translation.translations = translatedData.translations;
                translation.isTranslated = true;
                translation.isApproved = translatedData.approved ? true : false;
            } else {
                translation.translations = null;
                translation.isTranslated = false;
                translation.isApproved = false;
            }
            if (translation === this.currentTranslation) {
                this.currentTranslation = null;
                this.currentTranslation = translation;
            }
            this.updateTranslationRowClass(translation);
        },
        askPage: function() {
            if (this.busy) {
                return;
            }
            let ask = (this.pageIndex + 1).toString(), newPage;
            for (;;) {
                ask = window.prompt(`Enter the new page number (between 1 and ${this.totalPages}):`, ask);
                ask = typeof ask === 'string' ? ask.replace(/^\s+|\s+$/, '') : '';
                if (ask === '') {
                    return;
                }
                newPage = parseInt(ask, 10);
                if (!isNaN(newPage) && newPage >= 1 && newPage <= this.totalPages) {
                    break;
                }
            }
            newPage--;
            if (this.pageIndex !== newPage) {
                this.pageIndex = newPage;
            }
        },
        checkAllPackageVersionSubscriptions: function(checked) {
            if (this.busy) {
                return;
            }
            checked = checked ? true : false;
            for (const pvs of this.notify.packageVersionSubscriptions) {
                pvs.checked = checked;
            }
        },
        <?php
        if ($enableManagingNotifications) {
            ?>
            saveNotifications: function() {
                if (this.busy) {
                    return;
                }
                const data = {
                    newVersions: this.notify.newVersions ? 1 : 0,
                    allVersions: this.notify.allExistingVersions,
                };
                if (data.allVersions === 'custom') {
                    let versions = [];
                    for (const pvs of this.notify.packageVersionSubscriptions) {
                        if (pvs.checked) {
                            versions.push(pvs.id);
                        }
                    }
                    data.versions = versions.join(',');
                }
                this.busy = true;
                ajax('saveNotifications', data, (ok, response) => {
                    this.busy = false;
                    if (!ok) {
                        window.alert(response);
                        return;
                    }
                    bootstrap.Modal.getInstance('#comtra_translation-notifications').hide();
                });
            },
            <?php
        }
        if ($canEditGlossary) {
            ?>
            editGlossaryEntry: function(glossaryEntry) {
                if (this.busy) {
                    return;
                }
                this.currentGlossaryEntry = glossaryEntry;
                for (const key of Object.keys(this.editingGlossaryEntry)) {
                    this.editingGlossaryEntry[key] = glossaryEntry ? glossaryEntry[key] : '';
                }
                this.$nextTick(() => {
                    const el = document.getElementById('comtra_glossaryentry-edit');
                    let modal = bootstrap.Modal.getInstance(el);
                    if (!modal) {
                        modal = new bootstrap.Modal(el, {backdrop: 'static'});
                        el.addEventListener('shown.bs.modal', () => {
                            const i = document.getElementById(this.currentGlossaryEntry === null ? 'comtra_glossaryentry-edit-term' : 'comtra_glossaryentry-edit-translation');
                            i.focus();
                            i.select();
                        });
                        el.addEventListener('hide.bs.modal', (e) => {
                            if (this.busy) {
                                e.preventDefault();
                            }
                        });
                    }
                    modal.show();
                });
            },
            saveGlossaryEntry: function() {
                if (this.busy) {
                    return;
                }
                if (this.editingGlossaryEntry.term === '') {
                    document.getElementById('comtra_glossaryentry-edit-term').focus();
                    return;
                }
                this.busy = true;
                ajax(
                    'saveGlossaryEntry',
                    $.extend(true, {
                        id: this.currentGlossaryEntry === null ? 'new' : this.currentGlossaryEntry.id,
                    }, this.editingGlossaryEntry),
                    (ok, data) => {
                        this.busy = false;
                        if (!ok) {
                            window.alert(data);
                            return;
                        }
                        if (this.currentGlossaryEntry === null) {
                            this.currentTranslation.glossary.push(data);
                        } else {
                            for (const key of Object.keys(data)) {
                                this.currentGlossaryEntry[key] = data[key];
                            }
                        }
                        bootstrap.Modal.getInstance('#comtra_glossaryentry-edit').hide();
                    }
                );
            },
            deleteGlossaryEntry: function() {
                if (this.busy || this.currentGlossaryEntry === null) {
                    return;
                }
                if (!confirm(<?= json_encode(t('Are you sure?')) ?>)) {
                    return;
                }
                this.busy = true;
                ajax(
                    'deleteGlossaryEntry',
                    {
                        id: this.currentGlossaryEntry.id,
                    },
                    (ok, data) => {
                        this.busy = false;
                        if (!ok) {
                            window.alert(data);
                            return;
                        }
                        const index = this.currentTranslation.glossary.indexOf(this.currentGlossaryEntry);
                        if (index >= 0) {
                            this.currentTranslation.glossary.splice(index, 1);
                        }
                        bootstrap.Modal.getInstance('#comtra_glossaryentry-edit').hide();
                    }
                );
            },
            <?php
        }
        if ($enableComments) {
            ?>
            finalizeComment: function(comment, childrenToo) {
                comment.textFormatted = convertMarkdownToHtml(comment.text);
                if (childrenToo) {
                    comment.comments.forEach((childComment) => {
                        this.finalizeComment(childComment, true);
                    });
                }
            },
            editComment: function(translation, parentComment, comment) {
                if (this.busy) {
                    return;
                }
                this.editingComment.translation = translation;
                this.editingComment.parentComment = parentComment;
                this.editingComment.comment = comment;
                this.editingComment.isGlobal = (parentComment === null && comment !== null) ? comment.isGlobal : null;
                this.editingComment.newCommentText = comment === null ? '' : comment.text;
                const el = document.getElementById('comtra_comment-edit');
                let modal = bootstrap.Modal.getInstance(el);
                if (!modal) {
                    modal = new bootstrap.Modal(el, {backdrop: 'static'});
                    el.addEventListener('shown.bs.modal', () => {
                        $(el).find('select,textarea').filter(':first').focus();
                    });
                    el.addEventListener('hide.bs.modal', (e) => {
                        if (this.busy) {
                            e.preventDefault();
                        }
                    });
                }
                this.$nextTick(() => modal.show());
            },
            saveComment: function() {
                if (this.busy) {
                    return;
                }
                const translation = this.editingComment.translation;
                const parentComment = this.editingComment.parentComment;
                const comment = this.editingComment.comment;
                const data = {
                    packageVersionID: <?= json_encode($packageVersionID) ?>,
                    id: comment === null ? 'new' : comment.id,
                    parent: parentComment === null ? 'root' : parentComment.id,
                    text: this.editingComment.newCommentText,
                };
                if (parentComment === null) {
                    if (typeof this.editingComment.isGlobal !== 'boolean') {
                        $('#comtra_comment-edit-isglobal').focus();
                        return;
                    }
                    data.translatable = translation.id;
                    data.visibility = this.editingComment.isGlobal ? 'global' : 'locale';
                }
                if (data.text === '') {
                    $('#comtra_comment-edit-newcommenttext').focus();
                    return;
                }
                this.busy = true;
                ajax(
                    'saveComment',
                    data,
                    (ok, response) => {
                        this.busy = false;
                        if (!ok) {
                            window.alert(response);
                            return;
                        }
                        this.finalizeComment(response, false);
                        this.editingComment.translation = null;
                        this.editingComment.parentComment = null;
                        this.editingComment.comment = null;
                        if (comment !== null) {
                            for (const key in response) {
                                comment[key] = response[key];
                            }
                        } else {
                            if (parentComment !== null) {
                                parentComment.comments.push(response);
                            } else {
                                translation.comments.push(response);
                            }
                            translation.totalComments = this.countTotalComments(translation);
                        }
                        if (translation === this.currentTranslation) {
                            this.fixCommentsDisplay();
                        }
                        bootstrap.Modal.getInstance('#comtra_comment-edit').hide();
                    }
                );
            },
            deleteComment: function() {
                const translation = this.editingComment.translation;
                const parentComment = this.editingComment.parentComment;
                const comment = this.editingComment.comment;
                if (this.busy || comment === null) {
                    return;
                }
                if (comment.comments.length !== 0) {
                    window.alert(<?= json_encode(t("This comment has some replies, so it can't be deleted.")) ?>);
                    return;
                }
                this.busy = true;
                ajax(
                    'deleteComment',
                    {
                        id: comment.id,
                    },
                    (ok, response) => {
                        this.busy = false;
                        if (!ok) {
                            window.alert(response);
                            return;
                        }
                        this.editingComment.translation = null;
                        this.editingComment.parentComment = null;
                        this.editingComment.comment = null;
                        const parent = parentComment === null ? translation : parentComment;
                        const index = parent.comments.indexOf(comment);
                        if (index >= 0) {
                            parent.comments.splice(index, 1);
                        }
                        translation.totalComments = this.countTotalComments(translation);
                        if (translation === this.currentTranslation) {
                            this.fixCommentsDisplay();
                        }
                        bootstrap.Modal.getInstance('#comtra_comment-edit').hide();
                    }
                );
            },
            fixCommentsDisplay: function() {
                const translation = this.currentTranslation;
                if (translation === null) {
                    return;
                }
                // I don't know why I have to do this
                const actualComments = [].concat(translation.comments);
                translation.comments.splice(0, translation.comments.length);
                this.$forceUpdate();
                this.$nextTick(() => {
                    for (const actualComment of actualComments) {
                        translation.comments.push(actualComment);
                    }
                    this.$forceUpdate();
                });
            },
            countTotalComments: function(parent) {
                let result = 0;
                result += parent.comments.length;
                parent.comments.forEach((childComment) => {
                    result += this.countTotalComments(childComment);
                });
                return result;
            },
            <?php
        }
        ?>
    },
    computed: {
        isAdvancedSearch: function() {
            return this.search.caseSensitive || this.search.source !== true || this.search.translation !== true || this.search.context !== true || this.search.approved !== null || this.search.plural !== null;
        },
        totalPages: function() {
            return Math.ceil(this.filteredTranslations.length / this.ITEMS_PER_PAGE);
        },
        page: function() {
            const start = this.pageIndex * this.ITEMS_PER_PAGE;
            return this.filteredTranslations.slice(start, start + this.ITEMS_PER_PAGE);
        },
        translationState: function() {
            if (this.currentTranslation === null) {
                return false;
            }
            return new TranslationState(this.currentTranslation, this.translating, this.translatingApproved);
        },
        unsavedChanges: function() {
            const state = this.translationState;
            return state !== null && state.isDirty;
        },
        <?php
        if ($enableComments) {
            ?>
            newCommentHtml: function() {
                try {
                    return convertMarkdownToHtml(this.editingComment.newCommentText);
                } catch(e) {
                    return '<div class="alert alert-danger"'> + convertTextToHtml(e.message || e) + '</div>';
                }
            },
            <?php
        }
        ?>
    },
    watch: {
        currentTranslation: function (newCurrentTranslation, oldCurrentTranslation) {
            this.watchUrlHash = false;
            this.translating.splice(0, this.translating.length);
            this.translatingApproved = null;
            if (newCurrentTranslation) {
                this.updateTranslationRowClass(newCurrentTranslation);
                UrlHash.translationID = newCurrentTranslation.id;
                const numTranslating = newCurrentTranslation.isPlural ? this.PLURAL_RULE_BYINDEX.length : 1;
                for (let i = 0; i < numTranslating; i++) {
                    this.translating.push(newCurrentTranslation.translations ? newCurrentTranslation.translations[i] : '');
                }
                this.translatingApproved = newCurrentTranslation.isApproved;
                this.$nextTick(() => {
                    setTimeout(() => {
                        this.inspectUrlHash();
                    }, 100);
                });
            } else {
                UrlHash.translationID = null;
                this.translatingApproved = null;
            }
            this.watchUrlHash = true;
            if (oldCurrentTranslation) {
                this.updateTranslationRowClass(oldCurrentTranslation);
            }
            this.translatingIndex = 0;
        },
    },
});

});</script>
<?php
View::element('footer_required', ['disableTrackingCode' => true, 'display_account_menu' => false]);
?>
</body></html>
