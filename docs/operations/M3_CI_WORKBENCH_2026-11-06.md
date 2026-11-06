# M3-Abnahme mit dem unveränderten CI-Paket 4fb50cb

Alternative zur bestehenden Website für O09/M3-08/10. PR #62 bleibt Draft, kein Update auf orgavela.com, keine formale Abnahme. Das Plugin wird aus dem tatsächlichen CI-ZIP installiert, nicht aus einem neu gebauten Checkout.

## Herkunft und Identität

- PR-HEAD: `4fb50cbf495e482441eb35b5b41507866380cda2`.
- GitHub Run `42221730284`, Artifact `13503402652`, nicht abgelaufen bei Prüfung am 06.11.2026.
- GitHub-Artefaktcontainer SHA-256: `fc59b37260f720e0a963e269ee5a1cd81a658175d2764d57fa04b4fb18eafee4`.
- Enthaltenes unverändertes `uop-core.zip`: `4e92063a3983725577856b63f87a32da6d826c61e89dc95f9ecce9915061a670`.
- Editor-JavaScript: `868d2ad9eda83b2bd99851bbfe3e8087f95dc075beae4a241003b231ab0969fe`.
- WordPress 6.9, PHP 8.3.36, MariaDB 10.11; Corearchiv und Dockerimages im Launcher per SHA-256/Digest gebunden. Das Corearchiv stammt von `https://wordpress.org/wordpress-6.9.tar.gz`.

## Zugriff im aktuellen Arbeitsbereich

Die aufgebaute Instanz läuft unter `http://127.0.0.1:8762/wp-admin/`. Diese URL gilt **nur auf dem Rechner/Arbeitsbereich, der die Container ausführt**. Es wurde keine öffentlich erreichbare Freigabe und kein Remote-Zugang eingerichtet. Ein externer Browser erreicht diese Instanz nicht über sein eigenes localhost. Für Screenreader/Touch im eigenen Browser das Startpaket lokal ausführen; keine uneingeschränkte Erreichbarkeit für Work behaupten.

Lokale Dateien: `/workspace/shared/orgavela-m3-test/`. Private Passwörter stehen ausschließlich in `private-state/credentials.json` (Modus 0600, Elternverzeichnis 0700), niemals im Repository oder Startpaket. Synthetische Administratoren: `orgavela_m3_test` (`admin_password`) und `orgavela_m3_editor` (`editor_password`). Keine echten Teilnehmerdaten verwenden.

## Reproduzierbarer lokaler Start für Work

Voraussetzung: Linux mit Python >=3.12, lokalem Docker-Engine-Socket `/var/run/docker.sock`, Berechtigung für diesen Socket und ausreichend freiem Plattenplatz (empfohlen mindestens 6 GB). Die drei Dateien neben dem Launcher ablegen: das unveränderte `uop-core.zip`, `wordpress-6.9.tar.gz` und `m3-ci-workbench.py`. Das an den Betreiber übergebene Startarchiv enthält diese Dateien und diese Anleitung, keine Passwörter, Datenbank oder Testdaten. Docker lädt die beiden fest gebundenen öffentlichen Images beim ersten Start.

```sh
python3 m3-ci-workbench.py start
python3 m3-ci-workbench.py verify
```

Dann auf demselben Rechner `http://127.0.0.1:8762/wp-admin/` öffnen und mit den frisch generierten lokalen Passwörtern aus `.orgavela-m3-workbench/credentials.json` anmelden. Die beiden Accounts in getrennten Browserprofilen verwenden. Der Launcher ist ausschließlich für synthetische lokale Tests gedacht. Er adressiert immer den lokalen Unix-Socket und überschreibt keine vorhandene Website. Bei teilweise angelegter Instanz bricht er ab statt Daten still zu löschen. Den Fehler prüfen; vorhandene Abnahmedaten nicht ungeprüft löschen.

```sh
python3 m3-ci-workbench.py stop
python3 m3-ci-workbench.py start
python3 m3-ci-workbench.py verify
```

Stop erhält Docker-Volumes und Eingaben; Start nimmt eine vorhandene Instanz wieder auf und prüft den Editorhash. `verify` vergleicht alle 192 Paketdateien. Nach einer Unterbrechung vor HTTP-Zugriff kurz die Datenbankbereitschaft abwarten. Kein automatisches Löschen oder Reset vorgesehen. Das Ende dieses Chats garantiert keine unbegrenzte Laufzeit des aktuellen Arbeitsbereichs; das Startpaket ermöglicht den unabhängigen Neuaufbau.

## Begrenzung und verifizierte Prüfungen

Nur Webport 8762 an Host-Loopback; Datenbank ohne veröffentlichten Port in internem Docker-Netz. Der Webcontainer hat zusätzlich ein Bridge-Netz für die lokale Portweiterleitung. WordPress blockiert externe HTTP-Anfragen (`WP_HTTP_BLOCK_EXTERNAL=true`), WP-Cron ist deaktiviert; ein separates Test-MU-Plugin blockiert `wp_mail`. Keine Behauptung einer vollständigen System-Firewall für beliebigen PHP-Code. Suchmaschinenabschreckung ist eingeschaltet.

Tatsächlich geprüft: SHA-256 des GitHub-Artefaktcontainers, Paket und HTTP-Editor, alle 192 installierten Paketdateien, Installation/Aktivierung, Login und authentifizierter Builder-Host mit REST-Nonce-Konfiguration, Stop/Resume mit erhaltenen Volumes. Falsches Paket wird vor Containeränderungen verweigert. Dies ist Bereitstellungsnachweis, keine Screenreader-, Touch- oder M3-Abnahme.

## Nächster Auftrag: Work

Am bereitgestellten oder lokal reproduzierten identischen Paket beide Accounts verwenden: Tastatur und Folgefokus für Auf/Ab/Positionswahl, Screenreader-Ansagen, Touch/320px/Reflow, leere/fehlerhafte Zustände, Denied und fehlende/falsche/abgelaufene Nonce sowie konkurrierende Bearbeitung mit Übernehmen, Verwerfen, erneutem Konflikt und Publikation prüfen. Lokale Passwörter nicht in Logs/öffentliche Kommentare kopieren. Ergebnisse mit Paket-/Assethash dokumentieren. Danach unabhängige Architektur-/Policy-Freigabe und reguläre Integrationsentscheidung, kein vorzeitiger Merge.
