# Jak przetestować wtyczkę w 5 minut (bez własnego sklepu)

Jeśli chcesz szybko zobaczyć, jak działa wtyczka ReCart AI w praktyce, nie musisz instalować jej na swoim głównym sklepie. Możesz stworzyć darmowy, tymczasowy sklep testowy w kilka sekund za pomocą serwisu TasteWP.

Oto instrukcja krok po kroku:

## Krok 1: Stworzenie darmowego sklepu testowego

1. Wejdź na stronę: **[tastewp.com/new?pre-installed-plugin-slug=woocommerce](https://tastewp.com/new?pre-installed-plugin-slug=woocommerce)**
   *(Ten specjalny link automatycznie zainstaluje dla Ciebie WooCommerce).*
2. Zaznacz pole **"I agree with the Terms"**.
3. Kliknij duży pomarańczowy przycisk **"Set it up!"**.
4. Poczekaj kilka sekund. System wygeneruje dla Ciebie w pełni działający sklep WordPress.
5. Kliknij **"Access it now!"**, aby zalogować się do panelu administratora.

## Krok 2: Instalacja wtyczki ReCart AI

1. Pobierz plik `recart-ai.zip` z naszego repozytorium na swój komputer.
2. W panelu testowego sklepu (po lewej stronie) kliknij **Wtyczki (Plugins) > Dodaj nową (Add New)**.
3. Na górze kliknij **Wyślij wtyczkę na serwer (Upload Plugin)**.
4. Wybierz pobrany plik `recart-ai.zip` i kliknij **Zainstaluj teraz (Install Now)**.
5. Po instalacji kliknij **Włącz wtyczkę (Activate Plugin)**.

## Krok 3: Aktywacja licencji testowej

1. W menu po lewej stronie znajdź i kliknij **ReCart AI**.
2. Zobaczysz czerwony komunikat o braku licencji. Kliknij link w komunikacie lub przejdź do zakładki **Settings > License**.
3. W polu "License Key" wklej specjalny klucz testowy:
   `sub_test_woo_review_2026`
4. Kliknij **Activate License**.
5. Wtyczka połączy się z naszym serwerem na Vercel i zaświeci się na zielono (Plan: Pro).

## Krok 4: Dodanie testowego produktu

Aby przetestować odzyskiwanie koszyka, musisz mieć w sklepie jakiś produkt.

1. W menu po lewej kliknij **Produkty (Products) > Dodaj nowy (Add New)**.
2. Wpisz nazwę (np. "Testowy Produkt").
3. Zjedź niżej do sekcji "Dane produktu" (Product data) i w polu **Cena (Regular price)** wpisz `200` (musi być powyżej 150, bo taki jest domyślny próg wtyczki).
4. Kliknij niebieski przycisk **Opublikuj (Publish)** po prawej stronie.

## Krok 5: Testowanie Popupa (Ważne!)

Teraz wcielimy się w rolę klienta.

1. Skopiuj adres swojego testowego sklepu (znajdziesz go na samej górze ekranu, np. `https://niebieskikot.tastewp.com`).
2. Otwórz **nowe okno Incognito / Tryb Prywatny** w przeglądarce.
3. Wklej adres i wejdź na stronę.
4. Znajdź swój "Testowy Produkt" i dodaj go do koszyka.
5. Przejdź do koszyka (Cart).
6. **Symulacja ucieczki:** Przesuń kursor myszy szybko w stronę paska adresu na samej górze ekranu.
7. **Pojawi się popup ReCart AI!**
8. Wpisz wymyślony email (np. `test@test.pl`) i kliknij przycisk.
9. Zobaczysz podziękowanie, ale **nie dostaniesz kodu rabatowego** (zadziałała ochrona przed nadużyciami - to Twój pierwszy kontakt).

## Krok 6: Testowanie drugiego kontaktu (Otrzymanie kodu)

1. Zamknij okno Incognito.
2. Otwórz **zupełnie nowe okno Incognito**.
3. Wejdź ponownie na sklep, dodaj produkt do koszyka i wywołaj popup (ruch myszką w górę).
4. Wpisz email.
5. **Tym razem system wygeneruje dla Ciebie unikalny kod rabatowy!**

## Krok 7: Sprawdzenie analityki

1. Wróć do normalnego okna przeglądarki (gdzie jesteś zalogowany jako admin).
2. Przejdź do **ReCart AI > Dashboard**.
3. Zobaczysz, że system zarejestrował porzucone koszyki, wygenerowane kupony i unikalnych użytkowników.
4. Przejdź do **ReCart AI > Logs**, aby zobaczyć dokładną historię tego, jak system Cię śledził i blokował/przyznawał kody.

Twój testowy sklep na TasteWP będzie działał przez 2 dni, po czym zostanie automatycznie usunięty. To idealny czas na przetestowanie wszystkich funkcji!
