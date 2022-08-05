<?php

declare(strict_types=1);

defined('C5_EXECUTE') or die('Access Denied.');

/**
 * @var Concrete\Core\Page\View\PageView $view
 * @var Concrete\Core\Validation\CSRF\Token $token
 * @var array $notifications
 */
?>
<div id="app" v-cloak>
    <div v-if="notifications.length === 0" class="alert alert-info">
        <?= t('No notification found in the database') ?>
    </div>
    <div v-else>
        <table class="table table-striped table-hover table-small">
            <colgroup>
                <col width="1" />
            </colgroup>
            <thead>
                <tr>
                    <th></th>
                    <th class="text-center"><?= t('Priority') ?></th>
                    <th class="text-center"><?= t('Date') ?></th>
                    <th class="text-center"><?= t('Category') ?></th>
                    <th class="text-center"><?= t('Delivery attempts') ?></th>
                    <th class="text-center"><?= t('Sent') ?></th>
                    <th class="text-center"><?= t('Errors') ?></th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="notification in notifications">
                    <td>
                        <button type="button" class="btn btn-sm btn-primary" v-bind:disabled="busy" v-on:click.prevent="refreshNotification(notification)" title="<?= t('Refresh') ?>"><i class="fas fa-sync" v-bind:class="busy && refreshingNotification === notification ? 'fa-spin' : ''"></i></button>
                        <button type="button" class="btn btn-sm btn-primary" v-bind:disabled="busy || notification.sentOn !== null" v-on:click.prevent="sendNotification(notification)" title="<?= t('Send') ?>"><i class="fas fa-paper-plane" v-bind:class="busy && sendingNotification === notification ? 'fa-spin' : ''"></i></button>
                    </td>
                    <td class="text-center">
                        <span class="badge rounded-pill" v-bind:class="getPriorityPillClass(notification)">
                            {{ notification.priority }}
                        </span>
                    </td>
                    <td>
                        {{ notification.createdOn }}
                        <div v-if="notification.updatedOn !== notification.createdOn" class="small text-muted">
                            <span class="badge bg-secondary p-1"><?= t('Updated on') ?></span> {{ notification.updatedOn }}
                        </div>
                    </td>
                    <td>
                        {{ notification.category }}
                    </td>
                    <td class="text-center">
                        <span class="badge rounded-pill" v-bind:class="getDeliveryAttemptsPillClass(notification)">
                            {{ notification.deliveryAttempts }}
                        </span>
                    </td>
                    <td>
                        <div v-if="notification.sentOn !== null">
                            {{ notification.sentOn }}<br />
                            <span class="badge p-1" v-bind:class="notification.sentCountActual === notification.sentCountPotential ? 'bg-success' : 'bg-danger'">
                                <?= t('Recipients:') ?>
                                <span title="<?= t('Actual recipients notified') ?>">{{ notification.sentCountActual }}</span>
                                /
                                <span title="<?= t('Potential recipients to be notified') ?>">{{ notification.sentCountPotential }}</span>
                            </span>
                        </div>
                    </td>
                    <td>
                        <div v-if="notification.deliveryErrors.length === 0">
                            <i v-if="notification.sentOn === null"><?= tc('Notification', 'Not yet sent') ?></i>
                            <i v-else><?= tc('Errors', 'None') ?></i>
                        </div>
                        <div v-else class="alert alert-danger m-0 p-1">
                            <span v-if="notification.deliveryErrors.length === 1" style="white-space: pre-wrap">{{ notification.deliveryErrors[0] }}</span>
                            <ol v-else class="m-0">
                                <li v-for="deliveryError in notification.deliveryErrors" style="white-space: pre-wrap">{{ deliveryError }}</li>
                            </ol>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <div v-if="noMoreNotifications" class="alert alert-info mt-3">
            <?= t('No more notifications found in the database') ?>
        </div>
        <div class="text-center">
            <button type="button" class="btn btn-primary" v-on:click.prevent="loadNextPage" v-bind:disabled="busy"><?= t('Load more notifications') ?></button>
        </div>
    </div>
</div>
<script>$(document).ready(function() {
'use strict';

new Vue({
    el: '#app',
    data: function() {
        return {
            busy: false,
            notifications: <?= json_encode($notifications) ?>,
            refreshingNotification: null,
            sendingNotification: null,
            noMoreNotifications: <?= json_encode($notifications === []) ?>,
        };
    },
    methods: {
        getPriorityPillClass: function(notification) {
            if (notification.priority >= 8) {
                return 'bg-danger';
            }
            if (notification.priority >= 4) {
                return 'bg-warning';
            }
            return 'bg-success';
        },
        getDeliveryAttemptsPillClass: function(notification) {
            if (notification.deliveryAttempts === 0) {
                return 'bg-light text-dark';
            }
            if (notification.deliveryAttempts === 1) {
                return 'bg-success';
            }
            return 'bg-warning';
        },
        refreshNotification: function(notification) {
            if (this.busy) {
                return;
            }
            this.refreshingNotification = notification;
            this.busy = true;
            $.ajax({
                data: {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('comtra-notifications-refresh1'))?>,
                    id: notification.id,
                },
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action('refresh_notification'))?>
            })
            .always(() => {
                this.busy = false;
                this.refreshingNotification = null;
            })
            .done((data) => {
                for (const key in data) {
                    notification[key] = data[key];
                }
            })
            .fail((xhr, status, error) => {
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
            });
        },
        sendNotification: function(notification) {
            if (this.busy) {
                return;
            }
            this.sendingNotification = notification;
            this.busy = true;
            $.ajax({
                data: {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('comtra-notifications-send'))?>,
                    id: notification.id,
                },
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action('send_notification'))?>
            })
            .always(() => {
                this.busy = false;
                this.sendingNotification = null;
            })
            .done((data) => {
                for (const key in data) {
                    notification[key] = data[key];
                }
            })
            .fail((xhr, status, error) => {
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
            });
        },
        loadNextPage: function() {
            if (this.busy || this.noMoreNotifications) {
                return;
            }
            const lastNotification = this.notifications[this.notifications.length - 1];
            this.busy = true;
            $.ajax({
                data: {
                    <?= json_encode($token::DEFAULT_TOKEN_NAME) ?>: <?= json_encode($token->generate('comtra-notifications-nextpage'))?>,
                    id: lastNotification.id,
                    createdOnDB: lastNotification.createdOnDB,
                },
                dataType: 'json',
                method: 'POST',
                url: <?= json_encode((string) $view->action('get_next_page'))?>
            })
            .always(() => {
                this.busy = false;
            })
            .done((data) => {
                if (data.length === 0) {
                    this.noMoreNotifications = true;
                } else {
                    data.forEach((notification) => {
                        this.notifications.push(notification);
                    });
                }
            })
            .fail((xhr, status, error) => {
                ConcreteAlert.dialog(ccmi18n.error, ConcreteAjaxRequest.renderErrorResponse(xhr, true));
            });
        },
    }
});

});</script>
