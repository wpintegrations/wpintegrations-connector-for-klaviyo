=== WPIntegrations Form Connector for Klaviyo ===
Contributors: wpintegrations
Tags: klaviyo, contact form 7, elementor, wpforms, newsletter
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Send Contact Form 7, Elementor Pro, WPForms, Fluent Forms and Gravity Forms submissions to Klaviyo as profiles, subscribed to the lists you choose.

== Description ==

**WPIntegrations Form Connector for Klaviyo** turns every form submission into a Klaviyo profile — from whichever form plugin your site uses — and subscribes the person to email marketing on the lists you choose.

= Supported form plugins =

* Contact Form 7
* Elementor Pro Forms
* WPForms (Lite and paid editions)
* Fluent Forms
* Gravity Forms

One plugin covers them all: each integration picks a form plugin, then a form, then the Klaviyo fields it fills.

= Why this plugin =

* **Profile fields and custom properties** — map your form's fields to the profile's email, first and last name, phone number, organization, job title, address, external ID and locale, or to the custom properties your account already uses — or type any new name to save an answer as a custom profile property. No renaming form fields to match Klaviyo.
* **Name parts** — a WPForms, Fluent Forms or Gravity Forms name field offers first and last name separately, so they land in the right Klaviyo fields.
* **Lists and consent** — subscribe each person to email marketing on any number of your Klaviyo lists, picked from your account. Klaviyo records the consent, and a list set to double opt-in emails the confirmation link first. Or add them to the lists without marketing consent.
* **Consent checkbox** — map your form's consent box, and only people who tick it are sent.
* **Doesn't hold up your form** — on hosts running PHP-FPM or LiteSpeed (most of them), delivery happens after the visitor already has their confirmation, so a slow API doesn't slow your form down.
* **Full delivery log** — see every request, response and failure. Nothing is silent.
* **Secure by design** — your private API key is stored encrypted, every admin endpoint requires administrator capability, and nothing is sent anywhere except Klaviyo's API.

= What this free version includes =

* Unlimited integrations, across all five form plugins
* Email, first and last name, phone number (international format), organization, job title, address, external ID, locale, and custom profile properties
* A consent checkbox that decides whether the person is sent
* Email marketing subscription on any number of lists, each following its own single or double opt-in setting — or list membership without consent
* Create profiles, or update the ones that already exist, identified by email
* A delivery log with the full request and response for every send

= Pro version =

A separate Pro plugin, sold on [wpintegrations.net](https://wpintegrations.net/plugins/), adds:

* SMS marketing consent for the phone number, with a default country code for locally typed numbers
* A Klaviyo event for every submission, with all the answers, to start your flows
* Smart values resolved at submission time — page URL, submission time, the logged-in user's name and email, site title and URL — and fixed values
* The visitor's IP address saved on the profile
* Lists chosen by the visitor's answers
* Removing people from lists, or unsubscribing them, from a form
* Conditions — send only when the answers match
* A delivery queue that retries failed sends automatically, and Resend for failed deliveries in the log
* An email alert when a submission could not be delivered

= Source code and third-party libraries =

The complete, human-readable source of this plugin is public at [github.com/wpintegrations/wpintegrations-connector-for-klaviyo](https://github.com/wpintegrations/wpintegrations-connector-for-klaviyo).

The admin screens are a React app. The compiled bundle in `build/assets/` is generated with Vite from the source in `resources/js/`, which ships with the plugin together with `package.json` and `vite.config.mjs`. To rebuild it, install Node.js 18 or later and pnpm, then run `pnpm install` and `pnpm run build` in the plugin folder.

The bundle includes these open-source libraries:

* React and React DOM 18.3.1 (MIT) — [github.com/facebook/react](https://github.com/facebook/react)
* Ant Design 5.29.3 (MIT) — [github.com/ant-design/ant-design](https://github.com/ant-design/ant-design)
* Ant Design Icons 5.6.1 (MIT) — [github.com/ant-design/ant-design-icons](https://github.com/ant-design/ant-design-icons)

This plugin is built by WPIntegrations and is not affiliated with, endorsed by, or sponsored by Klaviyo, Contact Form 7, Elementor, WPForms, Fluent Forms or Gravity Forms. All names are the trademarks of their respective owners, used here only to describe what the plugin connects.

= Requirements =

* At least one of the supported form plugins (Elementor forms need Elementor Pro)
* A Klaviyo account and a private API key with Full access to Lists, Profiles and Subscriptions (Klaviyo → Settings → API keys)

== External services ==

This plugin connects to the **Klaviyo API** (operated by Klaviyo, Inc., https://www.klaviyo.com/). Nothing is sent until you save a private API key, and form data is sent only when a form that has an integration is submitted.

* **What is sent:** the email and the form fields you explicitly map (as profile attributes and custom properties), the lists you chose for the integration, and — when the integration subscribes people — an email marketing consent record labelled with the integration's name. With "Update profiles that already exist" off, the email is first looked up in your profiles. Nothing is sent when a mapped consent box is left empty.
* **What is read:** your lists (so you can pick them, and to confirm the connection), and the custom property names on your most recently updated profiles (so the mapping can offer them).
* **Where:** `https://a.klaviyo.com/api/profile-import`, `https://a.klaviyo.com/api/profile-subscription-bulk-create-jobs`, `https://a.klaviyo.com/api/lists/{id}/relationships/profiles`, `https://a.klaviyo.com/api/profiles` and `https://a.klaviyo.com/api/lists`, authenticated with your own private API key.

No data is sent to WPIntegrations or any other third party. Klaviyo terms of service: https://www.klaviyo.com/legal/terms-of-service — privacy notice: https://www.klaviyo.com/legal/privacy/privacy-notice

== Installation ==

1. Install and activate the plugin.
2. In Klaviyo, open **Settings → API keys** and create a private API key with Full access to **Lists**, **Profiles** and **Subscriptions**. Paste it on the plugin's **Settings** tab, save, and use **Test connection**.
3. Open the **Integrations** tab and create an integration: pick the form plugin and the form, map the fields, choose lists, and save.

== Frequently Asked Questions ==

= Which fields do I have to map? =

**Email** — the integration identifies the Klaviyo profile by it. Every other field is optional. A submission with an empty or invalid email is not sent, and the log says why.

= How do I save an answer as a custom property? =

In the field mapping, type the property name in the Klaviyo field box instead of picking a field, and press Enter. Klaviyo creates the property the first time it receives a value.

= What happens when the same person submits twice? =

With "Update profiles that already exist in Klaviyo" on (the default), the profile's mapped fields are updated; fields the form leaves empty are kept. With it off, a returning email is left as it is in Klaviyo, and the log says so.

= Does it support double opt-in? =

Yes — it follows the list. In Klaviyo, set the list's opt-in process to double opt-in (list → Settings). The list picker marks those lists. Klaviyo then emails new subscribers a confirmation link and adds them once they click it.

= What is the difference between "Subscribe" and "Only add them to the list"? =

Subscribing records email marketing consent in Klaviyo, so the person can receive your campaigns. Only adding them to the list makes them a list member without changing their consent — use it for lists you do not send marketing email to.

= Why was the phone number not saved? =

Klaviyo accepts phone numbers only in international format: a + followed by the country code and number, such as +14155550123. A number in another format is left out, the rest of the profile is still saved, and the log says why.

= How do I only add people who ticked the consent box? =

Map your consent checkbox to "Consent — only add the person when this is ticked". A submission where it is left empty is not sent, and the log says why.

= Does it work with the free version of Elementor? =

The Forms widget is an Elementor Pro feature, so Elementor Pro is required for Elementor forms. The other form plugins work in their free editions, except Gravity Forms, which is paid only.

= What happens if Klaviyo is temporarily unavailable? =

The submission is not delivered, and the failure is recorded in the Logs tab with the full response.

= Where is the source code of the admin screens? =

In `resources/js/` inside the plugin, and publicly at [github.com/wpintegrations/wpintegrations-connector-for-klaviyo](https://github.com/wpintegrations/wpintegrations-connector-for-klaviyo). The compiled files in `build/` are generated from it with Vite: run `pnpm install` and then `pnpm run build` in the plugin folder. See "Source code and third-party libraries" above for the bundled libraries.

= Where do I see errors? =

The **Logs** tab shows every delivery with the full request and response.

== Changelog ==

= 1.0.0 =
* Initial release: Contact Form 7, Elementor Pro, WPForms, Fluent Forms and Gravity Forms submissions to Klaviyo profiles, with built-in fields and custom properties, subscription to any number of lists with email marketing consent, a consent checkbox, a delivery log and encrypted API key storage.
