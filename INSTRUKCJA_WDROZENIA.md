# Instrukcja wdrożenia ReCart AI (dla osób nietechnicznych)

Witaj! Ta instrukcja krok po kroku przeprowadzi Cię przez proces instalacji i konfiguracji wtyczki **ReCart AI** w Twoim sklepie WooCommerce. Nie potrzebujesz żadnej wiedzy programistycznej, aby to zrobić.

Cały proces zajmie Ci około 5-10 minut.

---

## Krok 1: Pobranie wtyczki

Najpierw musisz pobrać plik instalacyjny wtyczki na swój komputer.

1. Wejdź na stronę repozytorium GitHub, gdzie znajduje się wtyczka.
2. Znajdź zielony przycisk z napisem **"<> Code"** (zazwyczaj w prawym górnym rogu nad listą plików) i kliknij go.
3. Z rozwiniętego menu wybierz opcję **"Download ZIP"**.
4. Plik o nazwie `recart-main.zip` (lub podobnej) zostanie pobrany na Twój komputer (zazwyczaj do folderu "Pobrane"). **Nie rozpakowuj tego pliku!**

---

## Krok 2: Instalacja w Twoim sklepie

Teraz wgramy pobrany plik do Twojego sklepu.

1. Zaloguj się do panelu administracyjnego swojego sklepu WordPress (zazwyczaj pod adresem `twojsklep.pl/wp-admin`).
2. W czarnym menu po lewej stronie znajdź zakładkę **"Wtyczki"** i kliknij **"Dodaj nową"** (lub "Dodaj nową wtyczkę").
3. Na samej górze strony kliknij przycisk **"Wyślij wtyczkę na serwer"**.
4. Pojawi się nowe pole. Kliknij przycisk **"Wybierz plik"** i znajdź na swoim komputerze pobrany wcześniej plik `.zip`.
5. Kliknij przycisk **"Zainstaluj teraz"**.
6. Poczekaj chwilę, aż WordPress wgra plik. Gdy proces się zakończy, kliknij niebieski przycisk **"Włącz wtyczkę"**.

Gratulacje! Wtyczka jest już zainstalowana i działa. Teraz wystarczy ją tylko skonfigurować.

---

## Krok 3: Konfiguracja wyglądu i tekstów

Wtyczka ma własną zakładkę w panelu Twojego sklepu. Skonfigurujmy, co zobaczy Twój klient.

1. W czarnym menu po lewej stronie (gdzieś pod zakładką WooCommerce) znajdź nową pozycję o nazwie **"ReCart AI"** i kliknij w nią. Zobaczysz główny panel (Dashboard) ze statystykami.
2. Kliknij w podmenu **"Settings"** (Ustawienia).
3. Jesteś teraz w zakładce **"Popup"**. Tutaj możesz zmienić teksty, które wyświetlą się klientowi, gdy będzie chciał opuścić stronę:
   - **Popup Title**: Główny nagłówek (np. "Nie odchodź z pustymi rękami!").
   - **Popup Message**: Krótki tekst zachęcający (np. "Widzimy, że masz produkty w koszyku. Zostaw nam swój email, a pomożemy Ci dokończyć zakupy.").
   - **Button Text**: Tekst na przycisku (np. "Zapisz mój koszyk").
4. Zjedź na sam dół i kliknij **"Zapisz zmiany"** (Save Changes).
5. Teraz na samej górze kliknij zakładkę **"Appearance"** (Wygląd).
6. Tutaj możesz kliknąć w pola kolorów i dopasować je do barw Twojego sklepu (np. zmienić kolor przycisku na taki sam, jakiego używasz na stronie). Pamiętaj, aby na koniec kliknąć **"Zapisz zmiany"**.

---

## Krok 4: Ustawienie rabatów (Ochrona przed nadużyciami)

To najważniejszy krok. Ustalimy tutaj, co klient dostanie w zamian za podanie maila i jak zabezpieczymy Cię przed "łowcami promocji".

1. Na górze strony ustawień kliknij zakładkę **"Anti-Abuse"**.
2. **Minimum Cart Total**: Wpisz minimalną kwotę zamówienia, od której klient może dostać rabat (np. `150`).
3. **Discount Type**: Wybierz rodzaj rabatu:
   - `Percentage` - procentowy (np. -10%)
   - `Fixed Amount` - kwotowy (np. -20 zł)
4. **Discount Value**: Wpisz wartość rabatu (np. `10` dla 10%).
5. **Free Delivery Option**: Jeśli zaznaczysz to pole, system najpierw zaoferuje klientowi darmową dostawę. Dopiero jeśli jego koszyk przekroczy kwotę z pola poniżej ("Free Delivery Threshold"), system da mu rabat procentowy/kwotowy. To świetny sposób na ratowanie marży!
6. Zjedź na dół i kliknij **"Zapisz zmiany"**.

**Jak działa ochrona (nie musisz nic tu klikać, to dzieje się automatycznie):**
- Klient, który pierwszy raz chce opuścić stronę, **nie dostanie kodu rabatowego**. Zobaczy tylko prośbę o email.
- Kod rabatowy dostanie dopiero, gdy wróci do sklepu drugi raz, lub gdy spędzi na stronie dużo czasu.
- Kody są jednorazowe i ważne tylko 48 godzin.
- Jeden klient może dostać maksymalnie 1 kod na 30 dni.

---

## Krok 5: Powiadomienia na Twój email (Opcjonalnie)

Jeśli chcesz dostawać maila za każdym razem, gdy ktoś porzuci koszyk i zostawi swój adres email:

1. Załóż darmowe konto na stronie [Formspree.io](https://formspree.io/).
2. Stwórz tam nowy formularz (New Form) i skopiuj jego adres URL (będzie wyglądał mniej więcej tak: `https://formspree.io/f/xabcdxyz`).
3. W panelu wtyczki ReCart AI przejdź do zakładki **"Webhook"**.
4. Zaznacz pole **"Enable Formspree"**.
5. Wklej skopiowany adres w pole **"Formspree Endpoint"**.
6. Kliknij **"Zainstaluj zmiany"**.

---

## Krok 6: Jak sprawdzić, czy to działa?

Chcesz zobaczyć popup na własne oczy? Zrób to w ten sposób:

1. Otwórz swoją stronę sklepu w **Trybie Incognito** (Prywatnym) w przeglądarce.
2. Dodaj jakiś produkt do koszyka.
3. Przesuń myszkę szybko w stronę paska adresu na samej górze ekranu (tak, jakbyś chciał zamknąć kartę).
4. Powinno pojawić się okienko popupa!

Pamiętaj: Ponieważ to Twój "pierwszy kontakt" ze sklepem w tej sesji, po wpisaniu maila system podziękuje Ci, ale **nie wyświetli kodu rabatowego**. To dowód na to, że ochrona przed nadużyciami działa poprawnie!

---

## Gdzie sprawdzać wyniki?

Aby zobaczyć, ile pieniędzy wtyczka dla Ciebie zarobiła:
1. Wejdź w panelu WordPress w **ReCart AI -> Dashboard**.
2. Zobaczysz tam pełne statystyki: ile koszyków porzucono, ile odzyskano, oraz dokładną kwotę uratowanego przychodu od momentu instalacji wtyczki.

To wszystko! Wtyczka działa w tle i automatycznie ratuje Twoje porzucone koszyki.
