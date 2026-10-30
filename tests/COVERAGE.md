# Test coverage

Every feature the addon has, and which suite covers it. Update this file in the PR that
adds or changes a test. A feature with no test is a gap, not an oversight to hide.

- **WPUnit**: `tests/wpunit`, Codeception in wp-env, with WP User Manager from its default branch.
- **E2E**: `tests/e2e`, Playwright on the wp-env site (default theme, pretty permalinks). Setup is
  `wp wpumda-e2e` in `tests/mu-plugins/wpumda-e2e.php`, which creates the members and pages the
  specs use.
- Status: ✅ covered, ❌ not covered, 🐞 `known-bug` test that fails until the bug is fixed.
  A blank cell means the other suite covers it.

## Known bugs

Tests for open bugs are tagged `known-bug` (WPUnit `@group known-bug`, Playwright
`@known-bug`), and CI skips them. The fix for a bug removes the tag from its tests. To run
them: `codecept run wpunit -g known-bug` or `npx playwright test --grep @known-bug`.

None of these lets anyone delete an account they shouldn't.

| Bug | Test |
|---|---|
| A redirect page that has since been deleted leaves the member on a blank page: `get_permalink()` returns false, `wp_safe_redirect()` does nothing and the handler exits | `DeleteAccountTest::test_a_deleted_redirect_page_falls_back_to_the_home_page` |
| `false` from `wpum_before_delete_account_allowed` still deletes the account: only a `WP_Error` is honoured | `DeleteAccountTest::test_false_from_the_allowed_filter_stops_the_deletion` |
| An apostrophe in a translated "Are you sure?" is a JavaScript syntax error, so `confirm()` never runs and the form posts without asking. `esc_html_e()` in the `onsubmit` attribute should be `esc_js()` | `FormTest::test_an_apostrophe_in_a_translation_keeps_the_confirmation_working` |
| A mistyped password is printed back into the form's HTML as the field's value | `SecurityTest::test_a_wrong_password_is_not_printed_back_into_the_form` |
| The tab and the form are added for everyone, so `[wpum_account]` outside WPUM's account page shows them to visitors. On the account page WPUM redirects visitors first | `AccountTabTest::test_visitors_never_see_the_tab_or_the_form` |
| A visitor posting the form gets a PHP error, a fatal on PHP 8: the password is checked against `$user->data->user_pass` before `is_user_logged_in()`. Nothing is deleted | `SecurityTest::test_a_visitor_gets_the_wrong_password_error` |
| The text domain never loads: the `plugins_loaded` hook at priority 0 is added while priority 0 is already running | `SmokeTest::test_the_text_domain_loads_while_wordpress_boots` |

Behaviour that's pinned by a passing test but may not be what's wanted:

- **The last administrator can delete their account**, leaving the site with none. wp-admin
  refuses to delete the current user, the form doesn't. A site can block it with
  `wpum_before_delete_account_allowed` (`RolesTest`).
- **Comments stay** with the member's name and email after the account has gone (`ContentTest`).
  The readme's tags include `gdpr`.
- **No email** goes to the member or the admin (`DeleteAccountTest::test_no_email_is_sent`).

Not tested anywhere:

- **Multisite**: `wp_delete_user()` only removes the member from the current site, so their
  account stays on the network and they can still log in. Neither suite runs on multisite.
- **The missing vendor directory notice** (`vendor_failed_notice`), which deactivates the plugin.
- **Password attempts aren't limited**, and `wp_login_failed` doesn't fire, so login limiters don't
  see them. Someone holding a stolen session could guess the password through this form. WPUM's
  change password form is the same.
- **Theme overrides** of `forms/delete-account-form.php`.

## Loading and release

| Feature | WPUnit | E2E |
|---|---|---|
| Starts on WPUM's `after_wpum_init`, defines its constants | ✅ | |
| Version is the same in the plugin header, `$version`, `readme.txt` Stable tag and `package.json`, and is in the changelog | ✅ | |
| `release.json` deploys to EDD item 25531; its version check finds the version | ✅ | |
| Requirements check passes | ✅ | |
| The `.pot` file ships with the addon | ✅ | |
| Text domain loads | 🐞 | |

## The account tab

| Feature | WPUnit | E2E |
|---|---|---|
| Delete account tab registered after Settings; core tabs kept | ✅ | |
| Logged-in members see the tab, and it opens the form | ✅ | ✅ |
| The form is only on its own tab | ✅ | |
| Visitors are sent from the account page to the login page and never see the form | ✅ | ✅ |
| Visitors never see the tab or the form, wherever `[wpum_account]` is | 🐞 | |
| WPUM loads the addon's form file, and only for this form | ✅ | |
| The addon's templates are on WPUM's template path | ✅ | |

## The form

| Feature | WPUnit | E2E |
|---|---|---|
| One step, named Delete account, in a heading | ✅ | ✅ |
| Asks for the current password: required, a password input, empty, with its description | ✅ | ✅ |
| Posts `wpum_form`, `step`, the nonce and the submit button | ✅ | ✅ |
| The nonce is valid for the current member | ✅ | |
| Posts nothing that identifies a user | ✅ | |
| Asks "Are you sure?" on submit | ✅ | ✅ |
| Saying no keeps the account | | ✅ |
| "Are you sure?" with an apostrophe in a translation | 🐞 | |
| Translated labels escaped | ✅ | |
| `wpum_before_delete_account_form` and `wpum_after_delete_account_form` fire | ✅ | |
| `delete_form_fields` adds fields, and a required one is enforced | ✅ | |
| Processes on the `wp` hook; WPUM loads the form when it's posted | ✅ | |

## Deleting an account

| Feature | WPUnit | E2E |
|---|---|---|
| The right password and nonce delete the account and its meta | ✅ | ✅ |
| Exactly one account goes | ✅ | |
| The member is logged out | ✅ | ✅ |
| The old session cookies stop working, and they can't log in again | | ✅ |
| Redirected to the home page by default, or with an empty setting | ✅ | ✅ |
| Redirected to the chosen page | ✅ | ✅ |
| Redirect page deleted since it was chosen | 🐞 | |
| No email is sent | ✅ | |
| Hooks fire in order: before, logout, `delete_user`, `deleted_user`, after | ✅ | |
| `wpum_before_delete_account` gets the user while they exist and are logged in | ✅ | |
| `wpum_after_delete_account` gets the ID once they've gone | ✅ | |
| `wpum_before_delete_account_allowed` gets the user; a `WP_Error` stops it and shows the message, with no cleanup hook | ✅ | |
| `false` from `wpum_before_delete_account_allowed` stops it | 🐞 | |
| A password with quotes and a backslash works as it does at login: WordPress never unslashes passwords | ✅ | |
| A password hashed by an older WordPress (phpass) is accepted | ✅ | |

## What must delete nothing

| Feature | WPUnit | E2E |
|---|---|---|
| A wrong password: error shown, still logged in, no hooks, content untouched | ✅ | ✅ |
| Another member's password, the stored hash, the wrong case | ✅ | |
| An empty password, none at all, one posted as an array | ✅ | |
| No nonce, and forged nonces: empty, made up, `0`, an array, too long | ✅ | |
| A nonce for another action | ✅ | |
| A nonce issued to another member (CSRF) | ✅ | |
| A valid nonce with no password | ✅ | |
| No submit button | ✅ | |
| A step that doesn't exist | ✅ | |
| A GET request | ✅ | |
| A tampered `user_id`, `ID`, `id`, `user`, `uid`, `author` or `member_id`: only ever the current user | ✅ | |
| A tampered ID with a wrong password, or with the victim's password | ✅ | |
| An administrator can't delete someone else | ✅ | |
| A visitor with a valid nonce | ✅ | |
| A visitor gets the wrong password error, not a PHP error | 🐞 | |
| A mistyped password isn't printed back into the form | 🐞 | |

## Roles

| Feature | WPUnit | E2E |
|---|---|---|
| Every role can delete their own account: no role is protected | ✅ | ✅ (administrator) |
| The last administrator isn't protected | ✅ | |
| A site can protect a role with `wpum_before_delete_account_allowed` | ✅ | |
| A member with two roles is deleted outright | ✅ | |

## Their content

`wp_delete_user()` runs with no user to reassign to, so WordPress decides by post type.

| Feature | WPUnit | E2E |
|---|---|---|
| Posts, pages and drafts go to the trash | ✅ | ✅ (a post) |
| Uploads are deleted for good | ✅ | |
| Custom post types follow `delete_with_user` | ✅ | |
| Other members' content is left alone | ✅ | |
| Comments stay, with the name and email | ✅ | |
| A refused deletion leaves the content alone | ✅ | ✅ |

## Settings

| Feature | WPUnit | E2E |
|---|---|---|
| After account cancellation is a multiselect on the Redirects tab | ✅ | ✅ |
| It offers the site's pages | ✅ | |
| Other sections' settings are kept | ✅ | |
