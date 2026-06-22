=== WooCommerce Herroepingsfunctie (NL) ===
Contributors: aronprins
Tags: woocommerce, withdrawal, refunds, compliance, checkout
Requires at least: 6.5
Tested up to: 7.0
Stable tag: 1.1.6
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Online herroepingsfunctie en digitale checkout-afstandsverklaring voor WooCommerce webshops.

== Description ==

WooCommerce Herroepingsfunctie (NL) helpt WooCommerce-webshops met een online herroepingsfunctie voor consumentenbestellingen en met een checkout-afstandsverklaring voor directe digitale levering.

De plugin is gebouwd voor B2C-compliance rondom het herroepingsrecht. Hij toont een frontendformulier via shortcode, ondersteunt gedeeltelijke herroeping per orderregel, gebruikt een aparte bevestigingsstap, mailt een ontvangstbevestiging en bewaart een beheerlog. Voor digitale-only carts kan de plugin een expliciete checkout-checkbox tonen waarmee de consument instemt met directe levering en erkent dat het herroepingsrecht onder voorwaarden vervalt.

= Belangrijkste functies =

* Shortcode `[herroepingsfunctie]` voor een publieke herroepingspagina.
* Automatisch "Mijn account"-endpoint voor ingelogde klanten.
* Orderlookup via ordernummer en e-mailadres.
* Gedeeltelijke herroeping per productregel.
* Optionele reden voor herroeping.
* Tweestapsbevestiging met configureerbare bevestigingsknop.
* Automatische ontvangstbevestiging per e-mail met inhoud en tijdstip.
* WooCommerce-beheeroverzicht met ontvangen herroepingen.
* Optionele orderstatuswijziging bij herroeping.
* Optionele IP-logging.
* HPOS-compatibele ordermeta.
* Classic checkout en WooCommerce Checkout Block ondersteuning.
* Digitale checkout-afstandsverklaring voor virtual/downloadable-only carts.
* EU/EER billing-country scope voor de digitale checkout-afstandsverklaring.
* Machine-assisted vertalingen voor EU-officiele talen, EER-talen en Engels.
* Instellingenhulp om meegeleverde vertaalde standaardteksten vooraf in te vullen, te controleren en pas daarna op te slaan.
* WordPress-updatecontrole via installable ZIP-assets uit GitHub Releases.

= Digitale checkout-afstandsverklaring =

Voor digitale inhoud die direct na betaling wordt geleverd, kan de plugin een aparte checkbox in checkout tonen. De checkbox wordt server-side verplicht wanneer:

* de functie in de instellingen is ingeschakeld;
* de cart uitsluitend virtuele/downloadbare producten bevat;
* het factuurland in de ingestelde landenlijst staat.

De standaard landenlijst is EU + EER: AT, BE, BG, HR, CY, CZ, DK, EE, FI, FR, DE, GR, HU, IE, IT, LV, LT, LU, MT, NL, PL, PT, RO, SK, SI, ES, SE, IS, LI en NO.

Zolang het factuurland leeg of onbekend is, gebruikt de plugin standaard fail-closed gedrag: de checkbox blijft verplicht totdat een niet-gescopeerd land is geselecteerd. Dit is configureerbaar.

= Talen =

De plugin laadt vertalingen via de WordPress textdomain `wc-herroepingsfunctie` en levert `.po`/`.mo` bestanden mee voor:

bg_BG, cs_CZ, da_DK, de_DE, el, en_GB, en_US, es_ES, et, fi, fr_FR, ga_IE, hr, hu_HU, is_IS, it_IT, lt_LT, lv, mt_MT, nb_NO, nl_NL, pl_PL, pt_PT, ro_RO, sk_SK, sl_SI en sv_SE.

Regionale varianten zoals nl_BE, fr_BE, de_AT of it_CH vallen automatisch terug op de bijbehorende basistaal.

Standaardwaarden voor tekstvelden worden vertaald zolang ze nog exact gelijk zijn aan de meegeleverde standaardtekst. Zodra een merchant een instelling aanpast, blijft die aangepaste juridische tekst behouden en wordt deze niet stilzwijgend machine-vertaald. Op de instellingenpagina kan een merchant een meegeleverde vertaling kiezen om de tekstvelden vooraf in te vullen, te controleren en eventueel aan te passen voordat de normale knop "Wijzigingen opslaan" de waarden bewaart.

= Juridische disclaimer =

Deze plugin wordt "as-is" geleverd als technisch hulpmiddel en vervangt geen juridisch advies. Laat de juridische teksten, uitzonderingen, landenlijst, checkout-afstandsverklaring en vertalingen controleren door een jurist voordat u de plugin gebruikt in productie. Niet-EU landen kunnen eigen of vergelijkbare consumentenregels hebben.

== Installation ==

1. Zorg dat WooCommerce actief is.
2. Upload de pluginmap of het ZIP-bestand via Plugins > Nieuwe plugin > Plugin uploaden.
3. Activeer "WooCommerce Herroepingsfunctie (NL)".
4. Ga naar WooCommerce > Herroeping instellingen.
5. Configureer de introductietekst, bevestigingsknop, e-mailonderwerp, uitgesloten categorieen/producten en optionele IP-logging.
6. Controleer de sectie "Checkout-afstandsverklaring voor digitale producten".
7. Maak een goed vindbare pagina, bijvoorbeeld "Herroepen / Annuleren".
8. Plaats de shortcode `[herroepingsfunctie]` op die pagina.
9. Plaats een duidelijke link naar deze pagina in de footer of klantenserviceomgeving.
10. Ga eenmalig naar Instellingen > Permalinks en klik op Opslaan.

== Frequently Asked Questions ==

= Vereist deze plugin WooCommerce? =

Ja. De plugin vereist WooCommerce en declareert `Requires Plugins: woocommerce` in de plugin-header.

= Werkt dit met de WooCommerce Checkout Block? =

Ja. De plugin ondersteunt classic checkout en de WooCommerce Checkout Block. WooCommerce 8.6-8.8 gebruikt de oudere experimentele Blocks API; WooCommerce 8.9+ gebruikt de stabiele Additional Checkout Fields API. Server-side validatie blijft leidend.

= Waarom zie ik de digitale checkout-checkbox niet voor alle klanten? =

De checkbox verschijnt alleen wanneer de cart uitsluitend virtuele/downloadbare producten bevat en het geselecteerde factuurland binnen de ingestelde landenlijst valt. Standaard is dat EU + EER.

= Welke landen zitten standaard in de digitale checkout-scope? =

AT, BE, BG, HR, CY, CZ, DK, EE, FI, FR, DE, GR, HU, IE, IT, LV, LT, LU, MT, NL, PL, PT, RO, SK, SI, ES, SE, IS, LI en NO.

= Worden aangepaste instellingsteksten automatisch vertaald? =

Nee. Alleen standaardwaarden worden automatisch vertaald zolang ze exact gelijk zijn aan de meegeleverde standaardtekst. Aangepaste juridische teksten blijven onaangetast.

= Zijn de meegeleverde vertalingen juridisch gevalideerd? =

Nee. De vertalingen zijn machine-assisted conceptvertalingen. Laat ze per doelmarkt juridisch reviewen voordat u ze productie-afhankelijk gebruikt.

= Slaat de plugin bewijsmateriaal op? =

Ja. Bij een herroeping bewaart de plugin de verklaring, geselecteerde producten, tijdstip en optioneel IP-adres. Bij de digitale checkout-afstandsverklaring bewaart de plugin akkoord, tekstversie, tekst, tijdstip en bron op de order.

= Ondersteunt de plugin HPOS? =

Ja. De plugin declareert compatibiliteit met WooCommerce High-Performance Order Storage en gebruikt WooCommerce order-API's voor ordermeta.

= Hoe werken automatische updates? =

De plugin gebruikt GitHub Releases als distributiekanaal. WordPress controleert de nieuwste gepubliceerde niet-prerelease GitHub Release en accepteert alleen het exacte ZIP-asset `wc-herroepingsfunctie-<versie>.zip`. Drafts, prereleases, releases zonder exact ZIP-asset en niet-GitHub download-URL's worden genegeerd. De plugin forceert automatische updates niet; bestaande WordPress/site-instellingen blijven leidend.

== Changelog ==

= Unreleased =

= 1.1.6 =
* Updated plugin author metadata and public plugin URL to Aron & Sharon.

= 1.1.5 =
* Cleared the GitHub release metadata cache when an authorized admin uses WordPress' forced update check.

= 1.1.4 =
* Added a settings-page dropdown for loading bundled translated defaults into editable text fields before saving.
* Preserved explicitly selected bundled default translations when they match the raw built-in defaults.

= 1.1.3 =
* Added a built-in WordPress updater that uses published GitHub Release ZIP assets.
* Added an offline PHP test harness for updater release parsing and safety checks.
* Added WordPress textdomain loading.
* Added bundled `.po` and `.mo` translation files for EU-official languages, EEA languages and English.
* Added regional locale fallback for common European WordPress locales.
* Localized default setting values while preserving merchant-edited legal copy.
* Added an explicit as-is legal disclaimer to the settings screen and documentation.

= 1.1.2 =
* Scoped the digital checkout waiver to configured billing countries, defaulting to EU + EER.
* Added fail-closed handling for unknown billing country.
* Updated Checkout Block behavior to hide/show the waiver and button label based on billing country.

= 1.1.1 =
* Added support for WooCommerce 8.8 Checkout Block waiver behavior.

= 1.1.0 =
* Added digital withdrawal waiver checkout flow.

= 1.0.0 =
* Initial online herroepingsfunctie with shortcode, account endpoint, email confirmation and admin log.

== Upgrade Notice ==

= 1.1.6 =
Updates the plugin author metadata to Aron & Sharon.

= 1.1.5 =
Improves GitHub Releases updater freshness when an admin clicks Dashboard > Updates > Check again.

= 1.1.4 =
Adds a review-before-save translation selector for default legal text settings.

= 1.1.3 =
Adds bundled European language files and locale-aware default settings. Review machine-assisted legal translations before relying on them in production.

= 1.1.2 =
The digital checkout waiver is now scoped by billing country. Review the configured country list after upgrading.
