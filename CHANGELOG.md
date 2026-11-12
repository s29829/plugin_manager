# Changelog - Plugin Manager

Numeracja wtyczki: 1.00, 1.01, 1.02 … (+0.01 przy każdej zmianie, `tools/release.py bump`).
Wersja wtyczki w `plugin_manager.yaml`; wersja całego repozytorium w pliku `VERSION` (zob. `CHANGELOG.md` w katalogu głównym).

## [1.12] - 2026-11-12
- Nowa nazwa: Plugin Manager (katalog plugin_manager, wcześniej Plugin Admin / pluginadmin); strona przypomina o wyłączeniu i usunięciu starej wtyczki pluginadmin; nowe repozytorium s29829/plugin_manager.

## [1.11] - 2026-11-11
- Porządki w historii zmian (bez zmian w działaniu wtyczki).

## [1.10] - 2026-11-11
- Dokumentacja: instalacja przez Plugin Manager albo CLI (curl, unzip, chown) bez skryptów bash; wydania zawierają tylko archiwum zip.

## [1.09] - 2026-11-11
- Pliki czekające na „Overwrite / Cancel” w <filestore>/plugin_manager/data_<klucz>/pending/ (wcześniej katalog tymczasowy); opcja „Reset plugin” (usuwa te pliki i pozostałe katalogi robocze, zainstalowanych wtyczek nie zmienia); usunięte wszystkie wywołania z @ (sprawdzanie plików i uprawnień przed użyciem).

## [1.08] - 2026-11-11
- Zmiany w dokumentacji i narzędziach wydawniczych (bez zmian w działaniu wtyczki).

## [1.07] - 2026-11-11
- Zmiany w dokumentacji i narzędziach wydawniczych (bez zmian w działaniu wtyczki).

## [1.06] - 2026-11-11
- Samodzielny pakiet zip (release.py package): plik LICENSE-COMMERCIAL.md w katalogu wtyczki, licencja i dokumentacja w paczce; instrukcja instalacji z archiwum zip.

## [1.05] - 2026-11-11
- Licencja PolyForm Noncommercial 1.0.0 (plik LICENSE; użycie komercyjne tylko za pisemną zgodą autora); sekcja „Licencja” w dokumentacji.

## [1.04] - 2026-11-11
- Dokumentacja w katalogu wtyczki: README.md (EN) i README.pl.md (PL), ze znacznikami wersji, od której dostępna jest każda funkcja.

## [1.03] - 2026-11-09
- Zgodność z wytycznymi ResourceSpace: obsługa obu lokalizacji wtyczek – `plugins/` i `filestore/plugins` (lista, usuwanie, nadpisywanie w bieżącym katalogu wtyczki; unikalność nazw sprawdzana w obu; ostrzeżenie o zduplikowanej nazwie).
- Nazwy nowo wgrywanych wtyczek tylko z małych liter, cyfr i `_` (nazwa jest częścią nazw funkcji hooków).

## [1.02] - 2026-11-09
- Kolumna „Options” z ikoną trybika (wbudowany SVG, link do `config_url` wtyczki) – aktywna tylko dla włączonych wtyczek.
- Przyciski „Activate” / „Deactivate” o jednakowej szerokości.

## [1.01] - 2026-11-08
- Kolumna „Info”: dymek z opisem `desc`, autorem i kategorią z pliku YAML oraz link „Doc” z `info_url`.
- Przyciski „Delete” na liście zastąpione przyciskami „Activate” / „Deactivate” (funkcje `activate_plugin` / `deactivate_plugin` ResourceSpace).

## [1.00] - 2026-11-08
- Pierwsza wersja: wgrywanie wtyczek jako archiwum `.zip` (rozpakowywane do `/plugins/<nazwa>`; jeśli wtyczka już istnieje – wybór „Overwrite” / „Cancel”) oraz usuwanie katalogu wtyczki po potwierdzeniu nazwą wtyczki lub słowem `delete`. Aktywnych wtyczek i samej Plugin Manager nie można usunąć.
