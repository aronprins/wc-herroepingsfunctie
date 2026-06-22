=== WooCommerce Herroepingsfunctie (NL) ===

Wettelijk verplichte online herroepingsfunctie (art. 6:230oa BW / Richtlijn (EU) 2023/2673).

INSTALLATIE
1. Ga in WordPress naar Plugins > Nieuwe plugin > Plugin uploaden.
2. Upload het ZIP-bestand en activeer.
3. Ga naar WooCommerce > Herroeping instellingen en configureer
   uitgesloten categorieen/producten, knopteksten en e-mail.
4. Controleer in dezelfde instellingen de sectie
   "Checkout-afstandsverklaring voor digitale producten". Deze toont een
   verplichte checkbox bij carts die uitsluitend virtuele/downloadbare
   producten bevatten voor ingestelde factuurlanden. Standaard is dit EU + EER
   (AT, BE, BG, HR, CY, CZ, DK, EE, FI, FR, DE, GR, HU, IE, IT, LV, LT, LU,
   MT, NL, PL, PT, RO, SK, SI, ES, SE, IS, LI, NO). De plugin bewaart tekst,
   versie en tijdstip op de order.
5. Maak een goed vindbare pagina (bv. "Herroepen / Annuleren") en zet daar
   de shortcode [herroepingsfunctie] op. Plaats een link in je footer.
   Het formulier staat ook automatisch in Mijn account onder "Herroepen".
6. Ga eenmalig naar Instellingen > Permalinks en klik op Opslaan, zodat het
   account-endpoint /mijn-account/herroepen/ werkt.

TALEN
De plugin laadt automatisch de WordPress-site/gebruikerslocale via de standaard
textdomain `wc-herroepingsfunctie`. Er zijn vertaalbestanden meegeleverd voor
EU-officiele talen, EER-talen en Engels: bg_BG, cs_CZ, da_DK, de_DE, el,
en_GB, en_US, es_ES, et, fi, fr_FR, ga_IE, hr, hu_HU, is_IS, it_IT, lt_LT, lv,
mt_MT, nb_NO, nl_NL, pl_PL, pt_PT, ro_RO, sk_SK, sl_SI en sv_SE. Regionale
varianten zoals nl_BE, fr_BE, de_AT of it_CH vallen automatisch terug op de
bijbehorende basistaal.

Standaardwaarden voor tekstvelden worden vertaald zolang ze nog exact gelijk
zijn aan de meegeleverde standaardtekst. Zodra een merchant een instelling
aanpast, blijft die aangepaste juridische tekst behouden en wordt deze niet
stilzwijgend machine-vertaald.

CHECKOUT-BLOCK
De afstandsverklaring werkt met classic checkout en met de WooCommerce
Checkout Block. WooCommerce 8.6-8.8 gebruikt hiervoor de oudere experimentele
Blocks API; WooCommerce 8.9+ gebruikt de stabiele Additional Checkout Fields
API. Voor conditioneel tonen bij fysieke carts gebruikt WooCommerce 9.9+ het
cart-schema; oudere block-checkouts worden aanvullend door het meegeleverde
checkout-script verborgen/aangestuurd. Server-side validatie verplicht de
checkbox alleen voor carts die uitsluitend virtuele/downloadbare producten
bevatten en waarvan het geselecteerde factuurland in de ingestelde landenlijst
staat. Zolang het factuurland leeg of onbekend is, geldt standaard fail-closed
gedrag en blijft de checkbox verplicht. Dit kan in de instellingen worden
uitgezet. De betaalknoptekst wordt alleen aangepast wanneer dezelfde
digitale-cart en factuurlandregels gelden.

LET OP
Test op staging. Laat juridische teksten, de landenlijst en de lijst met
uitzonderingen controleren door een jurist voor livegang. De meegeleverde
vertalingen zijn machine-assisted conceptvertalingen en moeten juridisch worden
gereviewd voordat ze productie-afhankelijk worden gebruikt. Niet-EU landen
kunnen eigen of vergelijkbare regels hebben.
