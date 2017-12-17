=== Authentiq ===
Contributors: authentiq, stannie, ziogaschr
Tags: passwordless, two-factor, two factor, 2 step authentication, 2 factor, 2FA, admin, ios, android, authentication, encryption, harden, iphone, log in, login, mfa, mobile, multifactor, multi factor, oauth, password, passwords, phone, secure, security, smartphone, single sign on, ssl, sso, strong authentication, tfa, two factor authentication, two step, wp-admin, wp-login, xmlrpc, xml-rpc, clef
Requires at least: 4.6
Tested up to: 4.9
Stable tag: 1.0.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Sign in (and sign up) to WordPress sites using the Authentiq ID app. Strong authentication, without the passwords.


== Description ==

The [Authentiq](https://www.authentiq.com/) plugin allows users to simply use their phone to authenticate to your WordPress site, share their identity details safely, and sign out again remotely.

This plugin is for admins that are interested in moving beyond username and password, and do not want to burden their users with typing one time codes from SMS or authenticators or other methods that harm the user experience.

The [Authentiq](https://www.authentiq.com/) service is free (for most use cases) and does not store any user data centrally, but in the Authentiq ID app on the user's phone instead.

Features:

*   Use Authentiq as a convenience sign in (and sign up) method, or as a secure sign in method.
*   In the latter case, replacing one time passwords (TOTP) or hardware tokens, option to still accept accounts with classic username & password.
*   On every sign in, the profile information is explicitly shared by the Authentiq ID app and updated in the WordPress profile, thus keeping it up to date on every sign in.
*   Visitors to your site that already have the Authentiq ID app installed can simply sign up by scanning a QR code or typing their email address and confirm on their phone.
*   Block users by (verified) email domain, or limit to specific domains.
*   Optionally request social accounts, address, and (verified) phone numbers too.
*   Remote sign out: your users can sign out with their Authentiq ID app, even when they left their session signed in on another computer.
*   Existing users can activate Authentiq in their profile page for convenience or additional security.

You can check our [demo site](https://wordpress.demos.authentiq.io/).


= Widget =

You can have an Authentiq sign in button in any widgetized area / sidebar:

1. Go to 'WordPress Dashboard > Appearance > Widgets'.
2. Drag and drop the "Authentiq" widget into any widgetized area / sidebar.
3. Configure settings on the widget and click save.

Place and configure as many Authentiq widgets as you want.


= Shortcodes =

The plugin can be placed anywhere in your site using WordPress shortcodes.

The shortcode is `[authentiq_login_button]`.

Additionally you can set some extra parameters, which are:

*   **sign_in_text**: Text shown in Authentiq button, when user **is not** signed in, in order to sign in.
*   **linking_text**: Text shown in Authentiq button, when user **is** signed in, but is not linked with Authentiq yet, in order to link the user account.
*   **sign_out_text**: Text shown in Authentiq button, when user **is** signed in and linked with Authentiq, in order to sign out.

Example use: `[authentiq_login_button sign_in_text="Login" linking_text="Link your account" sign_out_text="Logout"]`.

You can even place the [shortcode in your template files](http://docs.getshortcodes.com/article/52-using-of-shortcodes-in-template-files).


== Installation ==

= Automatic installation =

Log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type 'Authentiq' and click Search Plugins, once you find it, you can install it by simply clicking 'Install Now'.

= Manual installation =

The manual installation method involves downloading the Authentiq plugin and uploading it to your webserver via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).


== Frequently Asked Questions ==

= What if I am already using "WordPress Social Login" (WSL) plugin, for Authentiq =

Simply disable Authentiq within the WSL plugin (or the WSL plugin as a whole). Make sure you configure the redirect url correctly and add a backchannel redirect url in the Authentiq dashboard as prompted in the plugins page. Your users will be able to sign in with Authentiq right away.

= How a user can link her account with Authentiq =

There are two flows for this.

1. She signs in using the Authentiq ID App, using the same email as her current WordPress user.
2. She signs in at the site using WordPress Username & Password, and then links her account with Authentiq, either using a widget or shortcode button, or by visiting her profile.

= How can I see extra user info send by Authentiq ID =

You can simply visit the user’s profile page.

= If I disable WordPress Username & Password, how a user can get back access if lost? =

When this happens, the WordPress site admin visits the user profile from the WordPress Dashboard, and click the "unlink" button in the Authentiq section.

= Is WooCommerce supported? =

Yes, WooCommerce checkout and account pages are supported. In case "Address" and "Phone number" have been opted-in in Authentiq plugin settings page, they will be pre-filled for the user during checkout.


== Screenshots ==

1. Authentiq widget added in the sidebar.
2. Authentiq button in the WordPress login area.
3. Authentiq additional information in the user profile page.
4. Authentiq plugin admin page.
5. Authentiq widget configuration in the WordPress Dashboard.
6. Adding Authentiq Shortcode in a post.


== Changelog ==

= 1.0.3 - 2017-12-17 =

* Feature - Stop updating username and display_name on sub-sequent signins.
* Feature - Add settings for defining a specific redirect URL after signin.

= 1.0.2 - 2017-11-25 =

* Feature - Add `authentiq_pre_insert_user_data` filter.
* Feature - Add `authentiq_redirect_to_after_signin` filter.

= 1.0.1 - 2017-11-14 =

* Tweak - Support WordPress 4.9.
* Tweak - Make the Authentiq button in frontend a bit smaller.

= 1.0.0 - 2017-10-29 =

* Initial public release.