=== Gestor Listo for WooCommerce ===
Contributors: facilo
Tags: woocommerce, facturas, contabilidad, gestoria, iva
Requires at least: 6.6
Tested up to: 7.2
Requires PHP: 7.4
WC requires at least: 9.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hand your gestor the quarter's WooCommerce invoices in one click: the sales invoice book in Excel, a VAT summary and a ZIP with every invoice.

== Description ==

Every three months a shop in Spain has to pass its sales to its gestor (accountant). When that means a pile of PDFs, someone types them in by hand, and you pay for that time, or you get last-minute questions.

With Gestor Listo you pick the quarter, see what's missing, and download one package with everything your gestor needs. Your WooCommerce invoices stay exactly as they are: the plugin only reads them.

= What you get for free =

* A clear summary of your quarter: invoices, tax base, VAT and total, with VAT split by rate.
* A warning about anything missing before you send it: orders without an invoice, gaps in the numbering, refunds without a credit note, invoices whose totals don't add up.
* The sales invoice book (libro registro de facturas emitidas) in Excel, in the format the Spanish Tax Agency publishes.
* A sales CSV that opens in any spreadsheet or accounting program.
* An OSS summary of your EU sales by country.
* A ZIP with every invoice PDF.

No sign-up, no order limits, and your shop isn't connected to anyone. Your data stays in your shop.

= Which shops it's for =

WooCommerce shops in Spain that create their invoices with PDF Invoices & Packing Slips for WooCommerce or with WebToffee's invoice plugin. If you use another invoice plugin, you can tell Gestor Listo where it keeps the invoice number.

= Gestor Listo Pro =

Your gestor imports the quarter and stops typing invoices. Pro adds:

* Import files for a3 (SUENLACE.DAT), ContaPlus, Sage 50 and ContaSol.
* Your own ledger accounts, the way your gestor uses them.
* Automatic delivery to your gestor every quarter.
* Only what's new, if something changes after you've sent the quarter.
* The figures for your tax forms: 303 every quarter, 349 for EU sales, 369 for OSS, and 347 and 390 for the year. They cover your sales only, and your gestor checks and files them.

From EUR 59 a year, with a 14-day trial and no card needed. If your gestor can't import the files, you don't pay.

The Pro import files follow each program's published format and are covered by automated tests. They haven't yet been confirmed by a gestor importing them into their own program.

a3, ContaPlus, Sage 50 and ContaSol are trademarks of their owners. Gestor Listo has no connection with them.

== Installation ==

1. In your WordPress admin, go to Plugins > Add New and search for "Gestor Listo".
2. Install and activate it. WooCommerce must be active.
3. Go to WooCommerce > Gestor Listo and follow the three setup steps.

== Frequently Asked Questions ==

= Does it change my invoices or orders? =

No. Gestor Listo only reads. It never creates, numbers or edits an invoice.

= Is it a Verifactu system? =

No. Verifactu applies to the program that creates your invoices, and Gestor Listo doesn't create them. Keep using your invoicing plugin and ask its makers about Verifactu.

= Does it file form 303 (modelo 303)? =

No. It prepares the data for your gestor, who checks it and files it.

= Can I import the entries into ContaPlus or Sage 50? =

Yes, with Pro. For ContaPlus it creates XDiario and XSubCta, which ContaSol can also load. For Sage 50 you need Sage's add-on for importing invoices from Excel.

= What about a3's SUENLACE.DAT? =

Pro creates that file in a3's accounting link format.

= Can I export my WooCommerce orders to Excel? =

Yes. The invoice book is an Excel file, and you also get a CSV.

= What if I sell to other countries? =

It separates sales to EU businesses, exports, the Canary Islands, Ceuta and Melilla, and your OSS sales.

= Does it work with recargo de equivalencia or módulos? =

If you sell to customers under recargo de equivalencia, yes. If your own shop is taxed under those schemes, check with your gestor first.

= My gestor uses another program. =

Try the CSV or the Excel book. Many programs can import them.

== Screenshots ==

1. Your quarter, with VAT by rate and everything ready to send
2. What to check before you send it to your gestor
3. The package your gestor receives
4. Settings in three steps
5. Pro: files for your gestor's accounting program

== Changelog ==

= 1.0.0 =
* First release.
