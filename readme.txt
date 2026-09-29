=== Lutecia: Agentic Commerce for WooCommerce ===
Contributors: lutecia
Tags: ai, agentic commerce, ai agents, woocommerce
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI agents can find your products, build a cart and place the order in your store.

== Description ==

**Agents read your catalog.** Products, prices and stock are served through an open standard. An agent can search them and build a cart.

**The customer pays in the agent.** Connect your Stripe account, then request the AI agents you want to sell through.

**The order arrives in WooCommerce.** Paid, with the agent's name. Refunds made in Stripe are mirrored on the order.

**Requirements.** WooCommerce on HTTPS, a brand and a barcode (GTIN, UPC, EAN or ISBN) on each product, US dollar prices for US sales, an activated Stripe account you own.

**For developers.** Lutecia follows UCP (Universal Commerce Protocol) 2026-08-25, over MCP and REST. The discovery document is served on your store's domain.

== External Services ==

This plugin connects your store to the Lutecia service (https://lutecia.app). It sends requests in the following situations:

* **When you click Connect my store (or run `wp lutecia connect`)**: your shop URL, shop name, admin email (or the contact email you pass), language, currency, the URLs of your privacy policy and terms pages (shown to buyers by programs that require them), the agency code, if an agency managing your store gave you one, and a newly created WooCommerce API key are sent to Lutecia to register your store.
* **After connection**: Lutecia uses the API key to read your products (names, prices, stock, images) and keeps a copy of the catalog on its servers to serve agents.
* **When your catalog changes**: a signed notification (shop URL and timestamp, no product data) is sent so Lutecia can refresh your catalog.
* **When an AI agent requests your UCP address (`/.well-known/ucp`) on your domain**: once your store is connected, the plugin fetches the UCP document from Lutecia and serves it (cached for one hour). Before you connect, nothing is sent.
* **When you open Lutecia in your admin, or run `wp lutecia doctor`**: your shop's domain is sent to Lutecia to check that the service is reachable and knows your store. No product or order data is sent.
* **When a merchant program accepts you and gives you API credentials**: the credentials you paste are sent to Lutecia over an authenticated request and stored encrypted.
* **When you connect Stripe**: connecting opens Stripe's page in your browser (https://stripe.com, terms: https://stripe.com/legal, privacy: https://stripe.com/privacy). Lutecia then sends your catalog to your Stripe account and creates an order in your store for each sale, with the buyer's name and shipping address as Stripe provides them.

Provider: Lutecia. Terms of service and privacy policy: https://lutecia.app/legal

== Installation ==

1. Install and activate the plugin.
2. Open WooCommerce → Lutecia and click "Connect my store".
3. Follow the setup: your store, your catalog, payment, the agents.

Your permalink structure must not be set to "Plain" (Settings > Permalinks).

== Frequently Asked Questions ==

= What access does Lutecia get? =
A WooCommerce API key, visible and revocable at any time under WooCommerce → Settings → Advanced → REST API. It reads your products. While Checkout or Stripe is on, it also creates orders in your store; turning both off takes that back. If you attached your store to an agency, the agency can turn Checkout on and connect Stripe from its console.

= What is Checkout? =
A switch on the plugin's home, off by default. When it is on, an agent can create a standard order in your store with the customer's details. It appears in your Orders list awaiting payment, and you handle it as usual. Without Stripe, the order is paid on your site.

= How are sales paid? =
On your own Stripe account, at Stripe's usual card rates. Each sale arrives in WooCommerce as a paid order. Refunds and disputes are handled in your Stripe dashboard; a refund made there is mirrored on the order.

= Which Stripe account can I use? =
An account you own, activated. An account created by another platform, such as the one the WooCommerce Stripe plugin creates, cannot be connected.

= What can I turn off? =
Three switches on the plugin's home: Discovery, Checkout and Catalog file. One click disconnects.

= Can I apply to ChatGPT, Google, Microsoft or Perplexity? =
Yes. Each runs its own merchant program. The plugin links to each one and takes the credentials they send you once you are accepted.

= Does this slow my store down? =
No. Agents query Lutecia's servers, not yours. Your store is only contacted to read the catalog and, when an order is placed, to create it; the UCP document served on your domain is cached for an hour.

= AI agents already crawl my site. Do I need this? =
Yes. A crawler works from a copy of your pages, and the agent can only describe what it saw there. It cannot check the price or the stock with your store, and it cannot place an order. That is why the platforms built open standards for stores to connect directly. Connected, the agent gets your products from your store, accurate and up to date, and it can place the order.

= What does it cost? =
Nothing for now: the service is free while Lutecia works with its first merchants. Any paid plan will be announced to connected merchants before it applies, with the option to disconnect first.

= Can I disconnect? =
Yes. Disconnect Stripe stops the sales paid through Stripe; Disconnect this store deletes the API key and your store stops being served to agents. Uninstalling the plugin does the same cleanup. Your account and catalog copy stay deactivated on Lutecia's servers until you ask for their deletion, done within 30 days of the request.

= Where can I get help? =
Write to contact@lutecia.app, or post in the support forum on this page.

== Screenshots ==

1. Welcome: before connecting.
2. Setup: the payment step.
3. Home: channels, controls and settings.

== Changelog ==

= 0.2.0 =
* Sell through AI agents with your Stripe account.

= 0.1.5 =
* Fixes.

= 0.1.4 =
* Connect and check your store from the command line.

= 0.1.3 =
* Clearer screens.

= 0.1.2 =
* Wording.

= 0.1.1 =
* Fixes.

= 0.1.0 =
* First release.
