## Community Translation addon for Concrete CMS

This package is the current engine of [translate.concretecms.org](https://translate.concretecms.org).

Even if it requires Concrete to be executed, it was designed to be used by any other project that needs a collaborative, feature-rich and user-friendly translation system.

### Scheduled Jobs

In order to fetch remote data, as well as to send notifications, you should schedule some CLI commands provided by this package.

The easiest way is to use cron, with a configuration like this:

```sh
# People may apply to become members of translation teams
# These requests should be accepted/denied by team coordinators
# BTW team coordinators may be unresponsive, so appliers don't have any feedback
# The following command accepts automatically the requests if they aren't answered
# for 15 days (the "15" argument)
0 0 * * * ./concrete/bin/concrete ct:accept-requests 15 -vvv --no-interaction

# CommunityTranslation can automatically parse git repositories to:
# - update strings of "development" versions
# - find new version-like git tags, thus creating new versions of the translatable strings
# The following command does that
0 0 * * * ./concrete/bin/concrete ct:git-repository -vvv --no-interaction

# CommunityTranslation can send email notifications to users.
# Those notifications aren't sent immediately.
# Why?
# Because we may have temporary delivery (SMTP) issues.
# Furthermore, for example translators may add many comments to translations, and we don't
# want that the other translators receive tons of emails.
# So, many notifications can be "merged" together:
# For example, instead of sending 10 emails if a translator adds 10 comments,
# we send just 1 email with "user X posted 10 comments" messages.
# He have different kinds of notifications, each with different "priorities".
# So, we send hi-priority notifications more often:
* * * * * ./concrete/bin/concrete ct:send-notifications -vvv --no-interaction --priority=10
# Next we send lower-priority notification less often:
15 * * * * ./concrete/bin/concrete ct:send-notifications -vvv --no-interaction --priority=5
# Finally, we send every other notification even less often:
45 */6 * * * ./concrete/bin/concrete ct:send-notifications -vvv --no-interaction

# CommunityTranslation can limit the rates of the requests per IP address.
# To do that, we need to log the IP addresses used for requests.
# After some time, we don't need the older IP addresses, so we may safely remove them.
# This is done with the following command ("2" means "delete the IPs that are older than 2 days)
0 0 * * * ./concrete/bin/concrete ct:remove-logged-ips 2 -vvv --no-interaction

# CommunityTranslation can fetch translations from remote "packages"
# (for example, packages submitted to the Concrete marketplace).
# An external system (for example, the Concrete PRB) should tell CommunityTranslation when a new package
# (or a new package version) is available.
# When those packages are submitted to the remote system, they are not immediately available: for example
# they may need maintainer approval.
# With the following command, CommunityTranslation fetches the approved packages (or package versions)
# and extract the translatable strings.
35 * * * * ./concrete/bin/concrete ct:remote-packages -vvv --no-interaction
# It may happens that the external system tell CommunityTranslation about packages,
# but it may not tell CommunityTranslation about package approvals (for any reason).
# So, we also try to process the unapproved packages
5 12 * * * ./concrete/bin/concrete ct:remote-packages --try-unapproved=90 -vvv --no-interaction

# Translators may tell CommunityTranslation they want to be notified when
# the specific packages are updated (new translatable strings, new versions, ...)
# The following command generates those notifications.
55 */12 * * * ./concrete/bin/concrete ct:notify-packages -vvv --no-interaction
```
