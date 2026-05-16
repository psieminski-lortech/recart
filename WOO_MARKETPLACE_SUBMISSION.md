# Przewodnik: Jak dodać wtyczkę ReCart AI do WooCommerce Marketplace

Ten przewodnik krok po kroku przeprowadzi Cię przez proces publikacji wtyczki ReCart AI w oficjalnym sklepie WooCommerce Marketplace (woo.com).

## Zrozumienie modelu biznesowego (SaaS)

Ponieważ ReCart AI jest rozwiązaniem typu SaaS (Software as a Service) z własnym systemem subskrypcyjnym i serwerem licencyjnym, proces publikacji różni się od zwykłych wtyczek:

1. Zgodnie z wytycznymi WooCommerce [1], wtyczki integrujące się z zewnętrznymi systemami SaaS **muszą korzystać z WooCommerce SaaS Billing API** lub podpisać specjalną umowę partnerską (Partnership Agreement).
2. Oznacza to, że subskrypcje (plany Basic, Pro, Enterprise) będą sprzedawane bezpośrednio przez WooCommerce.com, a WooCommerce będzie pobierać 30% prowizji od sprzedaży [2].
3. Po zatwierdzeniu biznesowym otrzymasz dostęp do środowiska Sandbox, gdzie będziesz musiał zintegrować nasz serwer licencyjny z ich Billing API.

---

## Krok 1: Przygotowanie materiałów marketingowych

Zanim rozpoczniesz proces zgłoszenia, musisz przygotować następujące zasoby graficzne i tekstowe [3]:

### Wymagane grafiki
- **Product Icon (Ikona produktu)**: Plik JPG lub PNG o wymiarach dokładnie 160x160 px. Musi to być logo produktu (nie marki), bez zaokrąglonych rogów (system sam je zaokrągli). Cała treść powinna mieścić się w obszarze 112x112 px.
- **Highlight Card Color**: Kod koloru HEX (np. `#4F46E5`), który będzie tłem dla ikony w wyróżnionych miejscach sklepu.
- **Gallery Images (Zrzuty ekranu)**: Minimum 896x550 px. Powinny pokazywać zarówno widok od strony klienta (popup), jak i panel administracyjny (dashboard, ustawienia).
- **Wideo (Opcjonalnie, ale zalecane)**: Link do YouTube lub Vimeo prezentujący działanie wtyczki.

### Wymagane teksty
- **Short Description**: Krótki, chwytliwy opis (wyświetlany na kartach produktów i w Google).
- **Product Description**: Długi opis skupiony na korzyściach sprzedażowych, a nie specyfikacji technicznej. Używaj list wypunktowanych, prostego języka i unikaj emoji.
- **FAQs**: Sekcja najczęściej zadawanych pytań (np. "Czy wtyczka spowalnia stronę?", "Jak działa ochrona przed nadużyciami?").

---

## Krok 2: Zgłoszenie produktu (Business Review)

Pierwszym etapem jest weryfikacja biznesowa przez zespół WooCommerce.

1. Zaloguj się do swojego panelu dostawcy pod adresem: `woocommerce.com/wp-admin/`
2. W menu po lewej stronie przejdź do **Submissions > Submit Product**.
3. Jako typ produktu wybierz **SaaS** (nie Extension!).
4. Wypełnij formularz zgłoszeniowy:
   - Podaj nazwę produktu: **ReCart AI - Smart Cart Recovery**
   - Opisz model biznesowy (plany subskrypcyjne 99 zł, 249 zł, 499 zł).
   - Wyjaśnij, w jaki sposób produkt rozwiązuje problemy sprzedawców (odzyskiwanie koszyków przed wyjściem ze strony).
5. Prześlij plik `.zip` z wtyczką (wygenerowany z naszego repozytorium).
6. Podaj instrukcje testowania dla zespołu WooCommerce (możesz skopiować kroki z pliku `INSTRUKCJA_WDROZENIA.md`).

---

## Krok 3: Testy automatyczne (QIT)

Po przesłaniu pliku, wtyczka zostanie poddana automatycznym testom Quality Insights Toolkit (QIT) [4]. Nasza wtyczka jest już przygotowana, aby je przejść:
- Jest kompatybilna z PHP 8.1+
- Obsługuje HPOS (High-Performance Order Storage)
- Nie zawiera złośliwego kodu (Malware scan)
- Spełnia standardy bezpieczeństwa WordPressa

Jeśli jakikolwiek test wykaże błąd (status "Failed"), będziesz musiał pobrać raport, poprawić kod i wgrać plik `.zip` ponownie za pomocą przycisku **Replace**.

---

## Krok 4: Integracja z SaaS Billing API (Po akceptacji)

Gdy Twoje zgłoszenie przejdzie "Business Review" (może to potrwać do 30 dni), otrzymasz dostęp do środowiska Sandbox WooCommerce [5].

W tym momencie będziemy musieli zaktualizować nasz serwer licencyjny (ten na Vercel), aby zamiast bezpośrednio ze Stripe, komunikował się z API WooCommerce:

1. Otrzymasz klucze API (Key i Secret) dla środowiska Sandbox.
2. Będziemy musieli dodać endpointy w naszym serwerze na Vercel, które będą odbierać webhooki od WooCommerce (np. gdy klient kupi plan, anuluje subskrypcję lub zmieni plan).
3. Po zintegrowaniu i przetestowaniu w środowisku Sandbox, wyślesz link do testów zespołowi technicznemu WooCommerce.

---

## Krok 5: Publikacja

Po przejściu testów technicznych (Code review) oraz testów użyteczności (UX review), wtyczka zostanie zatwierdzona do publikacji.

1. Otrzymasz klucze API dla środowiska produkcyjnego.
2. Podmienimy klucze na serwerze Vercel.
3. Wtyczka pojawi się w oficjalnym sklepie WooCommerce Marketplace.

---

## Co musisz zrobić TERAZ?

Aby ruszyć z procesem, potrzebujesz przygotować paczkę ZIP z wtyczką.

1. Pobierz najnowszą wersję kodu z repozytorium GitHub.
2. Zapakuj folder `recart-ai` do pliku `recart-ai.zip`.
3. Zaloguj się na swoje konto Vendor na WooCommerce.com i rozpocznij proces zgłoszenia (Krok 2).

## References
[1] WooCommerce Developer Docs: Monetization expectations. https://developer.woocommerce.com/docs/woo-marketplace/monetization-expectations/
[2] WooCommerce Developer Docs: Getting started. https://developer.woocommerce.com/docs/woo-marketplace/getting-started/
[3] WooCommerce Developer Docs: Product page content and assets. https://developer.woocommerce.com/docs/woo-marketplace/product-page-content-and-assets/
[4] WooCommerce Developer Docs: Submitting your product. https://developer.woocommerce.com/docs/woo-marketplace/submitting-your-product/
[5] WooCommerce Developer Docs: Billing API for SaaS products. https://developer.woocommerce.com/docs/woo-marketplace/billing-api-saas/
