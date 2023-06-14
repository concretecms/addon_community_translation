<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var array $locales
 */
?>
<div id="app" v-cloak>
    <div class="alert alert-info">
        <?= t('The plural rules are stored in the database, and are determined starting from the %s package.', '<code>gettext/languages</code>') ?><br />
        <?= t('The %s package uses the Unicode CLDR data to define these plural rules.', '<code>gettext/languages</code>') ?><br />
        <?= t('Since the Unicode CLDR plural rules may change, the data stored in the database may become out of sync.') ?><br />
        <?= t('In this page you can update the plural rules stored in the database, so that they match the CLDR ones.') ?><br />
    </div>
    <div>
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="comtra-fl-onlytobefixed" v-model="showOnlyToBeFixed" v-bind:disabled="busy" />
            <label class="form-check-label" for="comtra-fl-onlytobefixed"><?= t('Show only locales that needs to be fixed') ?></label>
        </div>
    </div>
    <div class="alert alert-warning" v-if="shownLocales.length === 0">
        <?= t('No locales to be displayed') ?>
    </div>
    <table class="table table-striped table-hover table-sm" v-else>
        <thead>
            <tr>
                <th><?= t('ID') ?></th>
                <th><?= t('Name') ?></th>
                <th><?= t('Status') ?></th>
                <th><?= t('# Plurals') ?></th>
                <th><?= t('Formula') ?></th>
                <th><?= t('Forms') ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="locale in shownLocales">
                <td><code>{{ locale.id }}</code></td>
                <td>{{ locale.name }}</td>
                <td>{{ describeStatus(locale) }}</td>
                <td>
                    <span v-if="locale.pluralRules.actual === locale.pluralRules.expected">{{ locale.pluralRules.expected }}</span>
                    <div v-else class="alert alert-danger m-0 p-1">
                        <?= t('Actual:') ?> {{ locale.pluralRules.actual }}<br />
                        <?= t('Expected:') ?> {{ locale.pluralRules.expected }}
                    </div>
                </td>
                <td>
                    <code v-if="locale.pluralFormula.actual === locale.pluralFormula.expected">{{ locale.pluralFormula.expected }}</code>
                    <div v-else class="alert alert-warning m-0 p-1">
                        <?= t('Actual:') ?><br />
                        <code>{{ locale.pluralFormula.actual }}</code><br />
                        <br />
                        <?= t('Expected:') ?><br />
                        <code>{{ locale.pluralFormula.expected }}</code>
                    </div>
                </td>
                <td>
                    <code v-if="locale.pluralForms.actual === locale.pluralForms.expected" style="white-space: pre-wrap">{{ locale.pluralForms.expected }}</code>
                    <div v-else class="alert alert-warning m-0 p-1">
                        <?= t('Actual:') ?><br />
                        <code style="white-space: pre-wrap">{{ locale.pluralForms.actual }}</code><br />
                        <br />
                        <?= t('Expected:') ?><br />
                        <code style="white-space: pre-wrap">{{ locale.pluralForms.expected }}</code>
                    </div>
                </td>
                <td>
                    <button type="button" v-if="localeShouldBeFixed(locale)" v-bind:disabled="busy" class="btn btn-sm btn-danger" v-on:click.prevent="fixLocale(locale)"><?= t('Fix') ?></button>
                </td>
            </tr>
        </tbody>
    </table>
</div>
<script>$(document).ready(function() {
'use strict';

new Vue({
    el: '#app',
    data: function() {
        return {
            busy: false,
            locales: <?= json_encode($locales) ?>,
            showOnlyToBeFixed: true,
            shownLocales: [],
        };
    },
    mounted: function() {
        this.applyFilters();
        if (this.shownLocales.length === 0) {
            this.showOnlyToBeFixed = false;
        }
    },
    methods: {
        describeStatus: function(locale) {
            if (locale.source === true) {
                return <?= json_encode(t('Source')) ?>;
            }
            if (locale.approved === true) {
                return <?= json_encode(t('Approved')) ?>;
            }
            return <?= json_encode(t('Unapproved')) ?>;
        },
        applyFilters: function() {
            this.shownLocales.splice(0, this.shownLocales.length);
            this.locales.forEach((locale) => {
                if (this.localeSatisfiesFilters(locale)) {
                    this.shownLocales.push(locale);
                }
            });
        },
        localeShouldBeFixed: function(locale) {
            return ['pluralRules', 'pluralFormula', 'pluralForms'].some((field) => locale[field].actual !== locale[field].expected);
        },
        localeSatisfiesFilters: function(locale) {
            if (this.showOnlyToBeFixed) {
                if (!this.localeShouldBeFixed(locale)) {
                    return false;
                }
            }
            return true;
        },
        fixLocale: function(locale) {
            if (this.busy) {
                return true;
            }
            this.busy = true;
            $.ajax({
                data: {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('comtra-plru-fix1')) ?>,
                    id: locale.id,
                },
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action('fix_locale')) ?>
            })
            .always(() => {
                this.busy = false;
            })
            .done((data) => {
                for (const key in data) {
                    locale[key] = data[key];
                }
            })
            .fail((xhr, status, error) => {
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
            });

        },
    },
    watch: {
        showOnlyToBeFixed: function() {
            this.applyFilters();
        },
    },
});

});</script>
