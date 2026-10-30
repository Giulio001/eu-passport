=== Giuliomax Product Passport ===
Contributors: giulio001
Tags: product passport, qr code, manuals, product information, woocommerce
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Voluntary product information sheets with versioned manuals, local QR codes and after-sales support, with optional WooCommerce integration.

== Description ==
Create product information sheets that customers can open in a browser by scanning a QR code. Share manuals across products, manage variants and serial numbers, preserve published revisions and collect proposed information from suppliers. A standalone catalogue works without WooCommerce. No paid service or subscription is required.

This release supports voluntary information sheets. It does not certify EU legal compliance, validate regulated Digital Product Passport profiles or register passports in the EU registry. The generic, furniture and textile templates are editable information models. Registry, external resolver and external preservation adapters are not operational.

Features include:

* Separate drafts and published revisions, with editorial history and optimistic concurrency checks.
* Model, variant, batch and individual item inheritance with reversible overrides.
* Shared documents with language, version and public/private visibility.
* Local PNG/SVG QR generation, labels and a dated text PDF of the published revision.
* UTF-8 CSV preview, column mapping, isolated row errors and resumable import jobs.
* Administrative ZIP export with integrity manifest and conservative import into another catalogue.
* Supplier proposal links with expiration and revocation, followed by editorial review.
* Author, reviewer and publisher capabilities checked on the server.
* Optional WooCommerce product acquisition and explicit review of suggested updates.
* Four ready-made visual templates and a blank starting point, reusable custom designs, a simple section editor and live desktop/tablet/phone preview. Existing sheets keep their classic layout until explicitly changed.
* A shortcode and a Gutenberg block for a public sheet, QR code or link.

The admin panel starts in Italian and offers an English catalogue for the main workflow. The visual designer, some server errors and specialist guidance remain in Italian. Product content and document translations are supplied by the operator; changing the panel language does not translate product data.

== Installation ==

1. In WordPress, open Plugins > Add New > Upload Plugin, select the installable ZIP and activate it.
2. Open Product Passport > Impostazioni and enter your company name.
3. Upload a manual in Documenti, then create a product sheet in Passaporti.
4. Save a draft, preview it and publish a revision with an editorial reason.
5. Download the SVG/PNG QR or print a label. Test a real scan at the intended print size.

Requires PHP 8.2+, mbstring, fileinfo, iconv, zlib, zip, WordPress 6.8+ and a single-site installation. Use a transactional InnoDB database. Multisite activation is blocked. Deactivation and uninstall preserve data.

== Frequently Asked Questions ==

= Is WooCommerce required? =
No. The standalone catalogue works without WooCommerce. Optional acquisition reads product APIs and does not modify prices, stock, orders or checkout.

= Does this certify a European DPP? =
No. The plugin manages voluntary information sheets. Sector-specific obligations, validated data, identifiers, registry registration and preservation arrangements require separate assessment and implementation.

= Does a draft edit immediately change public information? =
No. Public information changes only when an authorized publisher publishes a revision. Published document references retain their selected version.

= Do QR codes use a third-party service? =
No. PNG and SVG QR codes are generated locally using the bundled MIT-licensed encoder.

= Are private documents in the public media library? =
No. Documents are stored in the database and served after access checks. Private evidence is excluded from public sheets and public exports. Hosting and database administrators can access database contents.

= Does ZIP export guarantee regulatory continuity? =
No. Maintain the domain or redirects, independent backups and any preservation agreements required by the applicable product regime. The administrative archive contains confidential data and must be protected. Existing identities are preserved during import; it is not a destructive database rollback.

= How do background jobs continue? =
Jobs process up to 50 records per batch through Action Scheduler when available or WP-Cron, with an hourly recovery check. You can also continue manually or run wp gmpp jobs. Configure system cron for reliable processing on low-traffic sites.

== Privacy and external services ==

The plugin sends no telemetry or AI requests. Product data, documents and QR generation remain on the WordPress site. No EU registry or preservation service is connected. Supplier emails require explicit administrative enablement and use the site's WordPress mail transport. Supplier links are stored as hashes; requests have expiry and revocation controls. Editorial records retain user IDs, timestamps and reasons. Internal data-responsibility fields and private evidence are excluded from public sheets. Public company contacts are selected by the operator. Avoid publishing unnecessary personal information.

Image URLs and external links entered by an operator may point to external sites. Loading an external image can disclose a visitor's IP address to that host; use local media when appropriate. Deactivation and uninstall preserve the data. The operator must define retention, privacy information and hosting/backup access arrangements.

== Source code ==

Readable source and test tools: https://github.com/Giulio001/eu-passport

All runtime assets and the local QR encoder are included. No JavaScript/CSS CDN or build step is required on the customer's site. Node dependencies in the repository are development-only test tools. The complete QR license is included in vendor/LICENSE-qrcode.txt. Italian usage and regulatory guidance is available in the repository documentation.

== Changelog ==

= 1.1.0 =
Visual templates: four ready-made styles, a blank starting point, reusable custom designs, section ordering, accessible color contrast and responsive live preview. Designs are saved into drafts and frozen on publication. Existing sheets retain their current appearance.

= 1.0.1 =
Functional and security review: automatic resumable queue leases, robust CSV preview, owned draft document access, named product selection, translation capture on draft save, preservation of safety notices, parameterized SQL identifiers and WordPress.org packaging corrections.

= 1.0.0 =
Initial standalone catalogue, versioned documents, local QR, import/export, editorial roles, supplier proposals and optional WooCommerce acquisition.
