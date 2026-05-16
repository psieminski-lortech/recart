# ReCart AI - Smart Cart Recovery with Anti-Abuse

ReCart AI to zaawansowana wtyczka do WooCommerce, która odzyskuje porzucone koszyki z wykorzystaniem technologii exit-intent oraz sztucznej inteligencji. Wtyczka jest w 100% zgodna z najlepszymi praktykami e-commerce, oferując zaawansowaną ochronę przed nadużyciami (Anti-Abuse) opartą na FingerprintJS.

Rozwiązanie to jest częścią ekosystemu [Asystent.io](https://asystent.io) - platformy AI do odzyskiwania porzuconych koszyków w e-commerce.

## Główne funkcje

1. **Exit-Intent Popup (Desktop + Mobile)**
   - Wykrywa zamiar opuszczenia strony (ruch kursora, szybkie przewijanie na mobile, przełączanie zakładek).
   - Zbiera adres email i opcjonalnie numer telefonu.
   - Wyświetla produkty aktualnie znajdujące się w koszyku.
   - W pełni konfigurowalny wygląd i teksty.

2. **Zaawansowana ochrona Anti-Abuse (FingerprintJS)**
   - Generuje unikalny identyfikator odwiedzającego na podstawie ponad 80 sygnałów przeglądarki.
   - **Zero first-discount**: Pierwszy kontakt nigdy nie generuje kodu rabatowego.
   - Kody rabatowe są jednorazowe, ważne 48h i przypisane do konkretnego odcisku palca (fingerprint).
   - Limit: maksymalnie 1 kod na użytkownika w ciągu 30 dni.
   - **Anomaly detection**: Automatyczne blokowanie użytkowników, którzy porzucili koszyk więcej niż 3 razy w ciągu 7 dni.

3. **Cart Abandonment Detection & Webhook**
   - Śledzi zawartość koszyka dla gości i zalogowanych użytkowników.
   - Wysyła pełny JSON payload na zewnętrzny endpoint (np. n8n, Langflow, ReCart AI backend) po określonym czasie od porzucenia.
   - Integracja z Formspree do prostych powiadomień email.

4. **Panel Administracyjny**
   - Pełna konfiguracja popupa, ochrony anti-abuse i webhooków.
   - Dashboard ze statystykami odzyskanych koszyków i wygenerowanych kodów.
   - Zarządzanie czarną listą (IP, Email, Fingerprint).
   - Szczegółowe logi zdarzeń.

## Wymagania

- WordPress 6.5+
- WooCommerce 9.0+
- PHP 8.1+

## Instalacja

1. Pobierz repozytorium jako plik ZIP lub sklonuj je.
2. Skopiuj folder `recart-ai` do katalogu `/wp-content/plugins/` w Twojej instalacji WordPress.
3. Zaloguj się do panelu administratora WordPress.
4. Przejdź do zakładki **Wtyczki** i aktywuj wtyczkę **ReCart AI - Smart Cart Recovery with Anti-Abuse**.
5. Po aktywacji wtyczka automatycznie utworzy niezbędne tabele w bazie danych.

## Konfiguracja

1. W panelu WordPress przejdź do **ReCart AI -> Settings**.
2. **Zakładka Popup**: Skonfiguruj teksty, opóźnienie i zachowanie popupa.
3. **Zakładka Anti-Abuse**: Ustaw minimalną wartość koszyka, rodzaj i wartość rabatu, próg darmowej dostawy oraz limity dla ochrony przed nadużyciami.
4. **Zakładka Webhook**: Wprowadź adres URL swojego endpointu (np. n8n) lub skonfiguruj integrację z Formspree.
5. **Zakładka Appearance**: Dostosuj kolory popupa do wyglądu Twojego sklepu.

## Testowanie mechanizmu Anti-Abuse

Aby przetestować działanie wtyczki i mechanizmów ochronnych:

1. **Test pierwszego kontaktu (Brak kodu)**:
   - Otwórz sklep w oknie incognito.
   - Dodaj produkt do koszyka (powyżej minimalnej kwoty).
   - Spróbuj opuścić stronę (przesuń kursor poza górną krawędź okna).
   - Wypełnij formularz w popupie.
   - **Oczekiwany rezultat**: Zobaczysz podziękowanie, ale NIE otrzymasz kodu rabatowego (zasada "zero first-discount").

2. **Test drugiego kontaktu (Otrzymanie kodu)**:
   - W tym samym oknie incognito, po upływie co najmniej 30 sekund (lub po ponownym wejściu na stronę i dodaniu czegoś do koszyka).
   - Ponownie wywołaj popup exit-intent.
   - Wypełnij formularz.
   - **Oczekiwany rezultat**: Otrzymasz jednorazowy kod rabatowy (lub darmową dostawę, w zależności od konfiguracji i wartości koszyka).

3. **Test limitu kodów**:
   - Spróbuj wywołać popup po raz trzeci i wypełnić formularz.
   - **Oczekiwany rezultat**: Nie otrzymasz kolejnego kodu (działa limit 1 kod / 30 dni).

4. **Test Anomaly Detection**:
   - Wyczyść cookies, ale użyj tej samej przeglądarki (FingerprintJS Cię rozpozna).
   - Dodaj produkty do koszyka i porzuć go 4 razy.
   - **Oczekiwany rezultat**: Twój fingerprint zostanie zablokowany, a w popupie zobaczysz tylko komunikat zachęcający do kontaktu.

Wszystkie te zdarzenia możesz śledzić w panelu administratora w zakładce **ReCart AI -> Logs**.

## Struktura bazy danych

Wtyczka tworzy następujące tabele:
- `wp_recart_fingerprints` - Przechowuje unikalne identyfikatory i statystyki użytkowników.
- `wp_recart_abandoned_carts` - Przechowuje dane o porzuconych koszykach.
- `wp_recart_logs` - Przechowuje logi zdarzeń systemowych.
- `wp_recart_blacklist` - Przechowuje zablokowane adresy IP, emaile i fingerprinty.
- `wp_recart_coupons` - Śledzi wygenerowane i użyte kody rabatowe.

## Licencja

GPL v2 or later.
