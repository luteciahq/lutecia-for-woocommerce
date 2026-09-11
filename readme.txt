=== Lutecia: UCP for WooCommerce ===
Contributors: lutecia
Tags: ai, chatgpt, agentic commerce, ucp, woocommerce
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let AI agents shop your WooCommerce store on UCP, the open standard behind Google AI Mode, Gemini and Microsoft Copilot.

== Description ==

AI assistants are starting to buy on people's behalf, using open standards such as UCP (Universal Commerce Protocol) to work with stores directly. Lutecia connects your WooCommerce store to them.

**What you get**

* **Discovery.** Your products, searchable by AI agents. When a shopper describes what they want, an agent can find it in your store.
* **Checkout.** The agent builds a cart and places the order in your store. It arrives in your store as a normal order.
* **Orders.** Everything shows up in your admin, and the agent can follow an order's status after the sale. You fulfill them exactly as you do today.

**How it works**

* **Install.** Install and activate the plugin, then open WooCommerce, Lutecia. It takes about a minute.
* **Approve.** Confirm the connection from the plugin screen. Lutecia registers your store and starts reading your products.
* **Live.** Your store is live for AI agents. From here on, the plugin screen shows what is on and lets you turn it off.

When an AI platform changes its requirements, the update happens on Lutecia's servers; you do not update the plugin for it.

**Channels**

UCP is on as soon as you connect, and you can turn it off under Controls. For ChatGPT, the plugin guides you through OpenAI's merchant program; once accepted, Lutecia delivers your catalog in OpenAI's format. For Google, Microsoft and Perplexity, the plugin links to each merchant program and, once they accept you, gives you a field to paste what they send you.

**Controls**

* **Discovery: on by default.** Your products, searchable by AI agents as soon as you connect. Turn it off from the dashboard at any time.
* **Checkout: opt-in.** Off until you turn it on from the dashboard.
* **Sales attribution: off by default.** A cookie on your storefront and a report of paid orders that came from an assistant.
* **Catalog file: on by default.** A daily file of your full catalog in Google Shopping format, for the programs that fetch a file rather than query live, such as Google and Microsoft Merchant Center.
* **Disconnecting.** One click on the dashboard: the API key is deleted and your store stops being served to assistants.

**For developers**

UCP version 2026-08-25. Capabilities: catalog search and lookup, cart, checkout, order, fulfillment, discounts, buyer consent, permalink. Transports: MCP (Model Context Protocol) and REST. The UCP discovery document is served on your domain. Daily catalog file in Google Shopping format. Nothing to host or update on your side.

== External Services ==

This plugin connects your store to the Lutecia service (https://lutecia.app). It sends requests in the following situations:

* **When you click Connect (or run `wp lutecia connect`)**: your shop URL, shop name, admin email (or the contact email you pass), language, currency, the URLs of your privacy policy and terms pages (shown to buyers by channels that require them), the agency code, if an agency managing your store gave you one, and a newly created read-only WooCommerce API key are sent to Lutecia to register your store.
* **After connection**: Lutecia uses the API key to read your products (names, prices, stock, images) and keeps a copy of the catalog on its servers to serve assistants.
* **When your catalog changes**: a signed notification (shop URL and timestamp, no product data) is sent so Lutecia can refresh your catalog.
* **When an AI assistant requests your UCP address (`/.well-known/ucp`) on your domain**: once your store is connected, the plugin fetches the UCP document from Lutecia and serves it (cached for one hour). Before you connect, nothing is sent.
* **When you open the Lutecia screen, or run `wp lutecia doctor`**: your shop's domain is sent to Lutecia to check that the service is reachable and knows your store. No product or order data is sent.
* **When a merchant program accepts you and gives you API credentials**: the credentials you paste are sent to Lutecia over an authenticated request and stored encrypted.
* **Only if you turn on Sales attribution, when an order that came from an AI assistant is paid**: the order number, the order total, the currency and the attribution parameter are sent to Lutecia so your dashboard can show which sales came from assistants. No buyer name, email or address is sent.

With Sales attribution turned on, the plugin also sets one first-party cookie on your storefront, named `lutecia_ref` (30 days, HttpOnly, SameSite=Lax). It stores the `?ref=lutecia_...` parameter carried by the product links Lutecia serves to AI assistants, so a resulting sale can be attributed. It is set only for visitors who arrive with that parameter, and holds no personal data. Because it is not strictly necessary to run your store, mention it in your cookie or privacy notice and collect consent where your law requires it. With Sales attribution off, the plugin sets no cookie and reports no order; turning it off also expires the cookie on a visitor's next page load.

Provider: Lutecia. Terms of service and privacy policy: https://lutecia.app/legal

== Installation ==

1. Install and activate the plugin.
2. Open WooCommerce → Lutecia.
3. Click "Connect my store". Your catalog is served over UCP within minutes. Each assistant's program is a separate application, from the same screen.

Your permalink structure must not be set to "Plain" (Settings > Permalinks).

== Frequently Asked Questions ==

= What access does Lutecia get? =
A read-only WooCommerce API key, visible and revocable at any time under WooCommerce → Settings → Advanced → REST API. It only switches to write access when Checkout is turned on. If you attached your store to an agency, the agency can turn Checkout on from its console.

= What is Checkout? =
A switch on the dashboard, off by default. When it is on, an assistant can create a standard order in your store with the customer's details. It appears in your Orders list awaiting payment, and you handle it as usual. Turning the switch on gives the API key write access; turning it off takes it back.

= Does the plugin track my visitors or my orders? =
Not unless you turn on Sales attribution, a switch on the dashboard. See External Services above for what the cookie stores and what is sent.

= Does this slow my store down? =
No. Assistants query Lutecia's servers, not yours. Your store is only contacted to read the catalog during syncs and, if you turned on Checkout, when an order is placed; the UCP document served on your domain is cached for an hour.

= AI assistants already crawl my site. Do I need this? =
No. But crawling has limits, and that is why the platforms built open standards for stores to connect directly. A crawler works from a copy of your pages; the assistant can only describe what it saw there. Connected, the assistant gets your products from your store, accurate and up to date, and it can place the order.

= What does it cost? =
Nothing for now: the service is free while Lutecia works with its first merchants. Any paid plan will be announced to connected merchants before it applies, with the option to disconnect first.

= Can I disconnect? =
Yes, one click on the dashboard. The API key is deleted and your store stops being served to assistants. Uninstalling the plugin does the same cleanup. Your account and catalog copy stay deactivated on Lutecia's servers until you ask for their deletion, done within 30 days of the request.

= Where can I get help? =
Write to contact@lutecia.app, or post in the support forum on this page.

== Screenshots ==

1. Connect: the connection screen, before connecting.
2. Connected: catalog sync, product data and legal pages.
3. Channels: Discovery, and each assistant's merchant program.
4. Controls: the four switches.

== Changelog ==

= 0.1.5 =
* Fixes: staging sites stay connected, the API key is revoked when a site is detected as moved or cloned, key permission changes are verified, the attributed-sale report retries.

= 0.1.4 =
* WP-CLI commands, agency code, duplicate-site guard, checkout from the agency console.

= 0.1.3 =
* The daily catalog file is produced in Google Shopping format.
* Clearer descriptions of each assistant's program on the plugin screen.

= 0.1.2 =
* Wording update.

= 0.1.1 =
* Fixes: clearer labels in the plugin screens, UCP address handling hardened, variation changes trigger the catalog notification, the sales attribution report runs from a scheduled event, complete uninstall cleanup.

= 0.1.0 =
* Initial release: one-click connection, UCP address served on your domain, catalog change notifications, guided channel applications (credentials stored encrypted), missing brand and GTIN report, admin dashboard.
