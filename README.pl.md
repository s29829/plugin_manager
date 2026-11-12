# Plugin Manager – dokumentacja

[English](README.md) | **Polski**

Dokumentacja dla wersji wtyczki **1.12** (ResourceSpace 11).
Znacznik *(od X.YY)* oznacza wersję wtyczki, od której funkcja jest dostępna. Funkcje bez
znacznika są dostępne od wersji 1.00. Historia zmian: [CHANGELOG.md](CHANGELOG.md).

Wtyczka pozwala zarządzać wtyczkami ResourceSpace z przeglądarki, bez dostępu do serwera.
Można nią wgrać wtyczkę z archiwum `.zip` (także nadpisać istniejącą), włączyć ją lub wyłączyć,
otworzyć jej opcje albo usunąć jej katalog.

**Gdzie:** System > Plugins > Plugin Manager > **Options**. Strona jest dostępna tylko dla
administratorów (uprawnienie `a`).


**Nazwa** *(od 1.12)*: do wersji 1.11 wtyczka nazywała się **Plugin Admin** (katalog
`pluginadmin`). Dla ResourceSpace to inna wtyczka: zainstaluj `plugin_manager`, aktywuj ją, a potem
wyłącz i usuń `pluginadmin` z jej listy (strona przypomina o tym, dopóki stara wtyczka jest
zainstalowana). Stary katalog `<filestore>/pluginadmin` zawiera tylko pliki czekające na decyzję
i można go usunąć. Nie myl jej ze stroną ResourceSpace System > Plugins.

---

## Upload a plugin – wgrywanie wtyczki

1. Wybierz plik `.zip` i kliknij **Upload**.
2. Wtyczka jest sprawdzana i rozpakowywana do `<RS_ROOT>/plugins/<nazwa>`.
3. Następnie aktywuj ją na liście **Installed plugins**.

Akceptowane układy archiwum:

```
nazwa/nazwa.yaml, nazwa/hooks/…      (jeden katalog główny; może się nazywać inaczej, np. nazwa-main)
nazwa.yaml, hooks/…                  (pliki wtyczki bezpośrednio w archiwum)
```

O nazwie wtyczki decyduje nazwa pliku `.yaml`. Może zawierać tylko **małe litery, cyfry i `_`**,
bo jest częścią nazw funkcji hooków *(od 1.03)*.

**Jeśli wtyczka już istnieje**, strona pokazuje porównanie tytułu i wersji obecnej i wgrywanej
oraz dwa przyciski. Od wersji 1.03 sprawdzane są także wtyczki w `filestore/plugins`.

- **Overwrite** – zastępuje **cały** katalog wtyczki zawartością archiwum. Pliki, których nie ma
  w archiwum, zostają usunięte. Wtyczka zostaje w swoim dotychczasowym katalogu (`plugins/` lub
  `filestore/plugins`) *(od 1.03)*. Jeśli jest aktywna, nowe pliki działają od razu.
- **Cancel** – nic nie zmienia.

Wgrany plik czeka na tę decyzję maksymalnie godzinę.

Zabezpieczenia przy wgrywaniu:

- archiwum może mieć najwyżej 20 000 plików i 500 MB po rozpakowaniu,
- ścieżki wychodzące poza katalog wtyczki (`..`, ścieżki bezwzględne) i dowiązania symboliczne
  są odrzucane,
- śmieci systemowe (`__MACOSX`, `.DS_Store`, `.git`, `Thumbs.db` …) są pomijane,
- wtyczka jest najpierw rozpakowywana do katalogu tymczasowego i dopiero potem przenoszona.
  Przy nadpisywaniu stara wersja jest przywracana, jeśli coś się nie uda.

Limit rozmiaru pliku zależy od serwera: `upload_max_filesize` i `post_max_size` w PHP oraz
`client_max_body_size` w nginx. Aktualne wartości widać pod formularzem.

## Installed plugins – lista wtyczek

Tabela wszystkich wtyczek z `plugins/` oraz `filestore/plugins` *(drugi katalog: od 1.03)*:

| Kolumna | Znaczenie |
|---|---|
| **Folder** | Nazwa katalogu. Dopiski: „(link)” to dowiązanie, „(filestore)” to wtyczka w `filestore/plugins` *(od 1.03)*, „Duplicate name” oznacza, że ta sama nazwa jest w obu katalogach (ResourceSpace używa pierwszej, z `plugins/`) *(od 1.03)*. |
| **Title**, **Version** | Z pliku YAML wtyczki. |
| **Info** *(od 1.01)* | Ikona „i” – dymek z opisem, autorem i kategorią. „Doc” to link do dokumentacji (`info_url`). |
| **Status** | `active` / `inactive`. „(config.php)” oznacza wtyczkę włączoną na sztywno w `config.php`. |
| **Options** *(od 1.02)* | Trybik prowadzi do strony opcji wtyczki (aktywny tylko dla włączonych wtyczek, które mają opcje). |
| ostatnia *(od 1.01)* | **Activate** / **Deactivate** – te same funkcje co na stronie Plugins w ResourceSpace. Wyłączenie trzeba potwierdzić. |

Statusu nie można zmienić tutaj, gdy:

- chodzi o samą wtyczkę Plugin Manager (zmienia się ją w System > Plugins),
- wtyczka jest włączona przez `$plugins` w `config.php` (wyłącza się ją tylko tam),
- wtyczka jest zablokowana przez `$disabled_plugins`,
- wtyczka nie ma pliku `<nazwa>.yaml`.

Najechanie na „-” pokazuje powód.

## Delete a plugin – usuwanie wtyczki

1. Wybierz wtyczkę z listy. Na liście są tylko wtyczki nieaktywne, bez samej Plugin Manager.
2. Wpisz jej nazwę albo słowo `delete`. Przycisk **Delete plugin** włączy się dopiero wtedy.
3. Potwierdź w okienku.

Katalog wtyczki jest najpierw przenoszony, a dopiero potem kasowany, więc nieudane usunięcie
nie zostawia w serwisie połowy wtyczki. **Usunięcia nie można cofnąć.** Dowiązanie symboliczne
jest usuwane bez ruszania katalogu, na który wskazuje. Od wersji 1.03 można usuwać wtyczki
także z `filestore/plugins`.

## Zasady korzystania

1. **Przed nadpisaniem aktywnej wtyczki** pamiętaj, że nowe pliki działają natychmiast.
   Wgrywaj sprawdzone wersje, a w razie wątpliwości najpierw wyłącz wtyczkę.
2. **Archiwum musi zawierać kompletną wtyczkę.** Overwrite usuwa pliki, których nie ma w `.zip`.
3. **Wtyczki z tego repozytorium** pakuj z katalogu `src/plugins/<nazwa>` (sam katalog wtyczki
   w archiwum). Numer wersji z `<nazwa>.yaml` pojawi się w porównaniu przy nadpisywaniu.
4. **Przed usunięciem wyłącz wtyczkę** przyciskiem Deactivate. Usunięcie katalogu nie usuwa
   tabel ani ustawień, które wtyczka mogła zapisać w bazie.

## Instalacja i wymagania

### Pobranie i instalacja

Wtyczka jest dystrybuowana jako archiwum `plugin_manager-<wersja>.zip` na stronie wydań:
<https://github.com/s29829/plugin_manager/releases>. Archiwum zawiera katalog `plugin_manager/` z kodem, licencją
i dokumentacją. Poniżej `<RS_ROOT>` to katalog instalacji ResourceSpace (np.
`/var/www/resourcespace`), a `www-data` to użytkownik serwera WWW (w innych systemach np. `apache`).

Plugin Manager nie może zainstalować samej siebie – pierwszą instalację wykonaj z wiersza poleceń.
Potrzebne są rozszerzenie PHP zip i prawo zapisu serwera WWW do `<RS_ROOT>/plugins` (zob.
„Wymagania”).

**Przez przeglądarkę:** Plugin Manager > Upload a plugin > wybierz plik `.zip` > Upload (przy
aktualizacji: **Overwrite**).

**Z wiersza poleceń (CLI)** – pobranie, rozpakowanie i uprawnienia:

```bash
VER=<wersja>                     # numer wersji ze strony wydań, np. 1.00
cd /tmp
curl -fLO https://github.com/s29829/plugin_manager/releases/download/v$VER/plugin_manager-$VER.zip
sudo rm -rf <RS_ROOT>/plugins/plugin_manager        # tylko przy aktualizacji (dane wtyczki są w filestore)
sudo unzip -q plugin_manager-$VER.zip -d <RS_ROOT>/plugins/
sudo chown -R www-data:www-data <RS_ROOT>/plugins/plugin_manager
sudo find <RS_ROOT>/plugins/plugin_manager -type d -exec chmod 755 {} \;
sudo find <RS_ROOT>/plugins/plugin_manager -type f -exec chmod 644 {} \;
```

**Przez git:** repozytorium <https://github.com/s29829/plugin_manager> zawiera pełny katalog wtyczki:

```bash
sudo git clone https://github.com/s29829/plugin_manager <RS_ROOT>/plugins/plugin_manager
sudo chown -R www-data:www-data <RS_ROOT>/plugins/plugin_manager
```

Aktualizacja: `sudo git -C <RS_ROOT>/plugins/plugin_manager pull`.

Na koniec: System > Plugins, aktywuj „Plugin Manager” i kliknij **Options**. Wtyczka sama tworzy
swój katalog danych `<filestore>/plugin_manager` – potrzebuje tylko prawa zapisu do filestore, które
ResourceSpace i tak wymaga.

### Wymagania

- Potrzebne jest rozszerzenie PHP `zip` (np. `sudo apt install php8.3-zip`, potem restart PHP-FPM).
- Serwer WWW (`www-data`) musi mieć prawo zapisu do `<RS_ROOT>/plugins`, inaczej wgrywanie
  i usuwanie wtyczek nie działa: `sudo chown -R www-data:www-data <RS_ROOT>/plugins`.
- Wgrane pliki czekające na decyzję („Overwrite / Cancel”) są w
  `<filestore>/plugin_manager/data_<klucz>/pending/` *(od 1.09; wcześniej w `<temp>/plugin_manager/`)*.
  Wtyczka nie ma innych trwałych ustawień. Katalogi robocze `.plugin_manager_*` w katalogu wtyczek
  są sprzątane automatycznie po godzinie.

**Katalogi wtyczka tworzy sama** przy pierwszym użyciu (aktywacja, otwarcie strony opcji).
Potrzebuje tylko prawa zapisu do filestore, które ResourceSpace i tak wymaga.

## Reset wtyczki *(od 1.09)*

Panel **Reset plugin** (po wpisaniu `RESET`) usuwa wszystko, co zapisała Plugin Manager: wgrane
pliki czekające na decyzję (`<filestore>/plugin_manager`, także stary katalog `<temp>/plugin_manager`)
oraz katalogi robocze pozostałe po przerwanej instalacji lub usuwaniu. **Zainstalowane wtyczki
nie są zmieniane.**

## Licencja *(od 1.05)*

- Wtyczka jest udostępniona na licencji **PolyForm Noncommercial License 1.0.0** (plik
  [LICENSE](LICENSE)).
- **Bezpłatnie** można jej używać prywatnie i w celach niekomercyjnych, a także w organizacjach
  niekomercyjnych (edukacja, instytucje publiczne, organizacje charytatywne). Można ją zmieniać
  i udostępniać w tych celach, z zachowaniem tekstu licencji.
- **Użycie komercyjne** (firma, usługi dla klientów, płatny produkt) wymaga pisemnej zgody
  autora: <https://github.com/s29829>. Szczegóły: `LICENSE-COMMERCIAL.md` w repozytorium.
