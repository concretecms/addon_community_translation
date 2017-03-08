<?php
namespace CommunityTranslation\Notification;

class Category
{
    /**
     * A new locale has been requested.
     *
     * @var string
     */
    const CLASS_NEW_LOCALE_REQUESTED = 'new_locale_requested';

    /**
     * The request of new locale has been approved.
     *
     * @var string
     */
    const CLASS_NEW_LOCALE_APPROVED = 'new_locale_approved';

    /**
     * The request of new locale has been rejected.
     */
    const CLASS_NEW_LOCALE_REJECTED = 'new_locale_rejected';

    /**
     * Someone wants to join a translation team.
     *
     * @var string
     */
    const CLASS_NEW_TEAM_JOIN_REQUEST = 'new_team_join_request';

    /**
     * A translation team join request has been approved.
     *
     * @var string
     */
    const CLASS_NEW_TRANSLATOR_APPROVED = 'new_translator_approved';

    /**
     * A translation team join request has been rejected.
     *
     * @var string
     */
    const CLASS_NEW_TRANSLATOR_REJECTED = 'new_translator_rejected';

    /**
     * A new comment about a translation has been submitted.
     *
     * @var string
     */
    const CLASS_TRANSLATABLE_COMMENT = 'translatable_comment';

    /**
     * Some translations need approval.
     *
     * @var string
     */
    const CLASS_TRANSLATIONS_NEED_APPROVAL = 'translations_need_approval';
}
