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
   producten bevatten en bewaart tekst, versie en tijdstip op de order.
5. Maak een goed vindbare pagina (bv. "Herroepen / Annuleren") en zet daar
   de shortcode [herroepingsfunctie] op. Plaats een link in je footer.
   Het formulier staat ook automatisch in Mijn account onder "Herroepen".
6. Ga eenmalig naar Instellingen > Permalinks en klik op Opslaan, zodat het
   account-endpoint /mijn-account/herroepen/ werkt.

CHECKOUT-BLOCK
De afstandsverklaring werkt met classic checkout en met de WooCommerce
Checkout Block via de Additional Checkout Fields API. WooCommerce 8.9+ is
nodig voor extra checkoutvelden in blocks. Voor conditioneel tonen alleen bij
digitale carts gebruikt WooCommerce 9.9+ het cart-schema; op oudere block-
checkouts kan het veld daardoor ruimer zichtbaar zijn. De betaalknoptekst kan
in classic checkout per digitale cart worden aangepast; in Checkout Block is de
Woo-filter alleen globaal beschikbaar wanneer het script is geladen.

LET OP
Test op staging. Laat juridische teksten en de lijst met uitzonderingen
controleren door een jurist voor livegang.
