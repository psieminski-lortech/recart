# Jak samodzielnie przetestować wtyczkę ReCart AI

Zanim udostępnisz wtyczkę klientom lub wyślesz ją do WooCommerce, warto przetestować ją na własnym sklepie. Ponieważ wtyczka ma wbudowany firewall licencyjny, musimy wygenerować prawdziwą licencję testową.

## Krok 1: Wygenerowanie licencji testowej w Stripe

Ponieważ serwer licencyjny weryfikuje klucze bezpośrednio w Stripe, musisz stworzyć darmową subskrypcję dla siebie:

1. Zaloguj się do swojego panelu Stripe (https://dashboard.stripe.com).
2. Przejdź do zakładki **Customers** (Klienci) i kliknij **Add customer**.
3. Wpisz swoje dane (np. `test@asystent.io`) i zapisz.
4. Na profilu nowo utworzonego klienta, w sekcji **Subscriptions**, kliknij **Create subscription**.
5. Wybierz produkt **ReCart AI - Pro** (lub Enterprise).
6. W sekcji **Pricing**, zmień cenę na **0 PLN** (lub dodaj 100% kupon rabatowy), aby Stripe nie pobrał od Ciebie pieniędzy.
7. Rozwiń **Advanced options** i znajdź sekcję **Metadata**.
8. Dodaj nowy klucz (Key): `domain`, a jako wartość (Value) wpisz domenę swojego sklepu testowego (np. `robotywogrodzie.pl`).
9. Kliknij **Start subscription**.
10. Skopiuj ID utworzonej subskrypcji (zaczyna się od `sub_...`). **To jest Twój klucz licencyjny.**

## Krok 2: Instalacja i aktywacja w sklepie

1. Zainstaluj wtyczkę `recart-ai.zip` w swoim sklepie WooCommerce.
2. Przejdź do **ReCart AI > Settings > License**.
3. Wklej skopiowany klucz `sub_...` i kliknij **Activate License**.
4. Wtyczka powinna połączyć się z serwerem na Vercel i zaświecić się na zielono jako "Active".

## Krok 3: Testowanie Popupa i Anti-Abuse

Wtyczka ma wbudowane zabezpieczenia przed nadużyciami, więc testowanie wymaga odpowiedniego podejścia:

### Test 1: Pierwszy kontakt (Zero First-Discount)
1. Otwórz swój sklep w **nowym oknie Incognito** (to ważne, żeby FingerprintJS uznał Cię za nowego użytkownika).
2. Dodaj do koszyka produkt za minimum 150 zł (domyślny próg).
3. Przesuń kursor myszy szybko w górę ekranu (poza obszar strony).
4. Pojawi się popup. Wpisz dowolny email i kliknij przycisk.
5. **Wynik:** Zobaczysz podziękowanie, ale **NIE dostaniesz kodu rabatowego**. System zapisał Twój "odcisk palca" i wie, że to Twój pierwszy kontakt.

### Test 2: Drugi kontakt (Otrzymanie kodu)
1. Zamknij okno Incognito i otwórz **zupełnie nowe okno Incognito**.
2. Ponownie wejdź na sklep i dodaj produkt do koszyka.
3. Wywołaj popup (ruch myszką w górę).
4. Wpisz email i kliknij przycisk.
5. **Wynik:** Tym razem system rozpozna, że już tu byłeś (lub spędziłeś na stronie wystarczająco dużo czasu) i **wygeneruje dla Ciebie unikalny kod rabatowy**.

### Test 3: Limit kodów
1. Skopiuj otrzymany kod.
2. Spróbuj wywołać popup po raz trzeci w tym samym oknie.
3. **Wynik:** Nawet jeśli wpiszesz email, nie dostaniesz kolejnego kodu. Działa limit "1 kod na 30 dni".

## Krok 4: Sprawdzenie analityki

1. Wróć do panelu admina WordPressa (w normalnym oknie przeglądarki).
2. Przejdź do **ReCart AI > Dashboard**.
3. Zobaczysz, że statystyki "Unique Visitors Tracked", "Abandoned Carts" i "Coupons Generated" wzrosły.
4. Przejdź do **ReCart AI > Logs**.
5. Zobaczysz pełną historię swoich testów (kiedy system uznał Cię za "first_contact", a kiedy za "eligible").

## Krok 5: Testowanie odzyskania koszyka (Sales Analytics)

1. Wróć do okna Incognito, gdzie masz wygenerowany kod rabatowy.
2. Przejdź do kasy (Checkout).
3. Wpisz kod rabatowy wygenerowany przez popup.
4. Złóż zamówienie (możesz użyć metody płatności "Za pobraniem" lub "Przelew bankowy" do testów).
5. Zmień status tego zamówienia w WooCommerce na "Zrealizowane" (Completed).
6. Wróć do **ReCart AI > Dashboard**.
7. **Wynik:** W sekcji "Sales Analytics" zobaczysz, że zamówienie zostało zaliczone jako "Recovered Cart", a jego wartość powiększyła "Revenue from Recovered Carts".
