# Terminbuchung

Web-Anwendung, die den E-Mail-Prozess für Terminvergabe ersetzt. Organisatoren legen Angebote mit Terminen und Plätzen an. Mitarbeitende buchen, stornieren und buchen selbst um. Die Anwendung führt Verfügbarkeit, Bestätigung und Teilnehmerliste.

Stack: Laravel 12 auf PHP 8.3 (NTS), MariaDB 10.11 oder 11.4, IIS mit FastCGI und URL Rewrite, Anmeldung über Windows-Authentifizierung, Verzeichnis über LDAPS.

## Funktionen

| Bereich | Was die Anwendung kann |
| --- | --- |
| Mitarbeitende | Angebote ansehen, mit zwei Klicks buchen, stornieren, umbuchen, „Meine Termine“ mit Status |
| Organisatoren | Angebote und Termine anlegen und ändern, Zeitraster, Termine kopieren, Teilnehmerliste mit Live-Aktualisierung, Excel-/CSV-Export, Anwesenheitsliste, manuell ein- und austragen, Nachrichten an Teilnehmende, Wunschverteilung |
| Administration | Standorte, Standardfrist, Erinnerungsvorgabe, Standardtext der Bestätigung, Löschläufe, Protokoll mit Filter |
| Hintergrund | E-Mails mit `.ics`-Anhang, Erinnerungen, Hinweis bei abgelaufener Wunschfrist, täglicher Löschlauf |

Der Platz ist ab der Bestätigung gesperrt. Doppelbuchungen verhindert die Datenbank selbst: Ein eindeutiger Index erlaubt pro Termin nur eine bestätigte Buchung.

## Anforderungen

- PHP 8.3 oder neuer, **Non-Thread-Safe-Build** für IIS, mit den Erweiterungen `pdo_mysql`, `ldap`, `mbstring`, `openssl`, `intl`, `fileinfo`, `zip`, `xml`, `dom`
- Composer 2
- MariaDB 10.11 oder 11.4 (InnoDB, utf8mb4)
- Windows Server 2019 oder neuer, IIS mit Windows-Authentifizierung, FastCGI und URL Rewrite
- Zugang zu einem Active-Directory-Dienstkonto (nur Leserechte) und zum Exchange-SMTP-Server

## Lokale Einrichtung

```bash
git clone https://github.com/DeGi20021983/Terminvorschlag.git
cd Terminvorschlag
composer install
cp .env.example .env
php artisan key:generate
```

Für die Entwicklung ohne Active Directory setzen Sie in der `.env`:

```dotenv
APP_ENV=local
APP_DEBUG=true
TERMIN_FORCE_HTTPS=false
DIRECTORY_DRIVER=static
TERMIN_DEV_USER=anna.schmidt
```

Danach Datenbank anlegen, migrieren und Demodaten laden:

```bash
php artisan migrate --seed
php artisan serve
```

Die Demodaten (Standorte, Angebote, Termine, Konten) stehen in `database/seeders/DatabaseSeeder.php` und in `config/directory.php` unter `static`. Es sind keine echten Personen. `DIRECTORY_DRIVER=static` und `TERMIN_DEV_USER` wirken nur bei `APP_ENV=local`.

## Konfiguration

Alle Werte stehen in der `.env`. Zugangsdaten gehören nicht ins Repository.

| Variable | Bedeutung |
| --- | --- |
| `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false` | Produktionsbetrieb |
| `DB_*` | Datenbank. Eigener Benutzer mit Rechten nur auf diese Datenbank |
| `TERMIN_ALLOWED_NETWORKS` | Hausnetz als CIDR-Liste, z. B. `10.0.0.0/8,192.168.10.0/24`. Leer heißt in Produktion: alles gesperrt |
| `TERMIN_FORCE_HTTPS` | `true` leitet HTTP auf HTTPS um (Standard) |
| `DIRECTORY_DRIVER` | `ldap` im Betrieb. `static` ist in Produktion gesperrt |
| `LDAP_URI`, `LDAP_BIND_DN`, `LDAP_BIND_PASSWORD`, `LDAP_BASE_DN`, `LDAP_TLS_CA_FILE` | LDAPS-Verbindung mit Dienstkonto |
| `LDAP_GROUP_ORGANIZER`, `LDAP_GROUP_ADMIN` | Vollständige DNs der Rollengruppen, z. B. `CN=Termine-Organisatoren,OU=Gruppen,DC=example,DC=local` |
| `LDAP_CACHE_TTL` | Dauer, in der AD-Abfragen zwischengespeichert werden (Standard 300 Sekunden) |
| `MAIL_*`, `MAIL_FROM_ADDRESS` | SMTP über den Exchange-Server, Absender ist das Funktionspostfach |
| `TERMIN_AUDIT_RETENTION_DAYS` | Aufbewahrung des Protokolls (Standard 365 Tage) |
| `TERMIN_PURGE_GRACE_HOURS` | Wartezeit nach Terminende, bevor der Löschlauf die Buchungen entfernt (Standard 0) |

### Rollen

Die Rollen kommen aus den AD-Gruppen. Sie werden bei jeder Anmeldung aktualisiert, spätestens nach `LDAP_CACHE_TTL` Sekunden.

- **Mitarbeitende**: alle angemeldeten Konten
- **Organisator**: Mitglieder von `LDAP_GROUP_ORGANIZER`. Sie verwalten nur die Angebote, für die sie verantwortlich sind.
- **Administrator**: Mitglieder von `LDAP_GROUP_ADMIN`. Sie verwalten keine Angebote, damit die Rollen getrennt bleiben.

Die Berechtigungen prüfen Policies und Gates auf dem Server. Die Oberfläche blendet nur aus, was man nicht darf.

## Datenschutz und Löschung

- Gespeichert werden nur Name, dienstliche E-Mail, Organisationseinheit und die Buchung.
- Der Löschlauf (`php artisan terminvorschlag:loeschen`, täglich 03:10 Uhr) entfernt Buchungen und Wunschdaten nach Terminende. Erhalten bleibt eine anonyme Angabe, ob ein Termin belegt war.
- Protokolleinträge werden nach `TERMIN_AUDIT_RETENTION_DAYS` entfernt. Konten ohne Bezug und ohne Anmeldung seit 30 Tagen werden entfernt.
- Der Datenschutzhinweis ist unter `/datenschutz` erreichbar.
- Keine externen Ressourcen: Skripte, Stile, Schriften und Bilder kommen vom eigenen Server (Inter ist eingebunden). Die Content-Security-Policy erlaubt nur `'self'`.

## Tests

Die Tests laufen gegen eine eigene MariaDB-Datenbank, nie gegen die Betriebsdatenbank.

```bash
mysql -u root -p -e "CREATE DATABASE terminvorschlag_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cp .env.testing.example .env.testing   # Benutzer, Passwort und APP_KEY eintragen; die Datei ist nicht versioniert
php artisan test
vendor/bin/pint --test
```

Die Parallelitätstests (`tests/Feature/Booking/ConcurrentBookingTest.php`) starten echte Prozesse mit eigenen Datenbankverbindungen. Dafür braucht PHP die Erweiterung `pcntl`. Ohne sie werden diese Tests übersprungen.

Die LDAP-Anbindung und der SMTP-Versand lassen sich in den Tests nicht gegen echte Systeme prüfen. Die Tests verwenden dafür einen Fake-Verzeichnisdienst und die Mail-Fake-Funktion. Die Prüfung gegen das echte AD und den Exchange-Server ist ein manueller Schritt bei der Inbetriebnahme.

## Bereitstellung auf IIS

### 1. Server vorbereiten

1. Rollen und Dienste installieren: Webserver (IIS), Windows-Authentifizierung, Anforderungsfilterung, CGI (für FastCGI). Modul **URL Rewrite** nachinstallieren.
2. PHP 8.3 NTS nach `C:\php` entpacken. In der `php.ini` die oben genannten Erweiterungen aktivieren und `cgi.fix_pathinfo=0` setzen.
3. MariaDB installieren. Datenbank und Benutzer anlegen, der Benutzer erhält Rechte nur auf diese Datenbank:

   ```sql
   CREATE DATABASE terminvorschlag CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'terminvorschlag'@'localhost' IDENTIFIED BY '<Passwort>';
   GRANT ALL PRIVILEGES ON terminvorschlag.* TO 'terminvorschlag'@'localhost';
   ```

4. Anwendungsverzeichnis anlegen, z. B. `C:\inetpub\terminvorschlag`, und das Repository dort klonen.

### 2. IIS-Site und Anwendungspool

1. Anwendungspool **Terminbuchung**: .NET-CLR-Version „Kein verwalteter Code“, eigene Identität (Dienstkonto ohne weitere Rechte).
2. Site mit dem physischen Pfad `C:\inetpub\terminvorschlag\public` anlegen, mit HTTPS-Bindung und Zertifikat.
3. Handlerzuordnung für `*.php`: FastCGI zu `C:\php\php-cgi.exe`.
4. Authentifizierung: **Windows-Authentifizierung aktiv**, **anonyme Authentifizierung aus**. Nur so setzt IIS die Servervariable `REMOTE_USER`.
5. IP-Adress- und Domänenbeschränkungen: nur das Hausnetz zulassen. Die Anwendung prüft das zusätzlich über `TERMIN_ALLOWED_NETWORKS`.
6. `public\web.config` liegt im Repository. Sie leitet auf HTTPS um und liefert statische Dateien direkt aus.

### 3. Rechte

- Das Dienstkonto des Anwendungspools braucht **Ändern** auf `storage` und `bootstrap\cache`.
- Alles andere: nur Lesen. Die Datei `.env` ist nur für das Dienstkonto und Administratoren lesbar.

### 4. Konfiguration und erste Bereitstellung

```powershell
cd C:\inetpub\terminvorschlag
copy .env.example .env          # Werte eintragen, siehe „Konfiguration“
php artisan key:generate --show  # Wert in APP_KEY übernehmen
.\scripts\deploy.ps1 -AppPath C:\inetpub\terminvorschlag -Php C:\php\php.exe -Composer composer -AppPool Terminbuchung
```

Das Skript `deploy.ps1` holt den Stand per `git fetch` und `git merge --ff-only`, führt `composer install --no-dev`, `php artisan migrate --force` und die Cache-Befehle aus. Bei jeder Bereitstellung wiederholen.

### 5. Scheduler einrichten

```powershell
.\scripts\install-scheduler.ps1 -AppPath C:\inetpub\terminvorschlag -Php C:\php\php.exe
```

Die Aufgabe startet jede Minute `php artisan schedule:run`. Darüber laufen:

| Befehl | Takt | Aufgabe |
| --- | --- | --- |
| `queue:work --stop-when-empty` | jede Minute | E-Mails aus der Warteschlange versenden |
| `terminvorschlag:erinnerungen` | alle 15 Minuten | Erinnerungen vor dem Termin, je Angebot abschaltbar |
| `terminvorschlag:wunschfristen` | alle 15 Minuten | einmaliger Hinweis an den Organisator nach Ablauf der Wunschfrist |
| `terminvorschlag:loeschen` | täglich 03:10 | Löschlauf |

Die Wunschverteilung startet nicht automatisch. Der Organisator löst sie auf Knopfdruck aus.

### 6. Prüfung nach der Inbetriebnahme

- `https://<Host>/up` antwortet mit 200.
- Anmeldung mit einem Konto aus der Mitarbeitenden-Gruppe: Angebote sichtbar, keine Verwaltungsmenüs.
- Anmeldung mit einem Organisator-Konto: Angebot anlegen, veröffentlichen, buchen, Teilnehmerliste öffnen.
- Eine Testbuchung auslösen und prüfen, dass die Bestätigungsmail mit `termin.ics` ankommt (`php artisan queue:work --stop-when-empty` ausführen, wenn der Scheduler noch nicht läuft).
- Im Protokoll der Administration erscheint die Änderung.
- Der Löschlauf ist unter „Löschfristen“ mit Ergebnis sichtbar, sobald er einmal gelaufen ist.

## Barrierefreiheit

Die Oberfläche folgt WCAG 2.1 AA und BITV 2.0: Tastaturbedienung, sichtbarer Fokusring, semantisches HTML mit Landmarks und Überschriften, beschriftete Felder, Fehlermeldungen mit Zuordnung, Statusmeldungen für Screenreader, ausreichende Kontraste, Reduzierung von Bewegung. Tabellen mit horizontalem Scrollen sind per Tastatur erreichbar.

Geprüft mit axe-core auf allen Bildschirmen bei 390, 768 und 1280 Pixel Breite: keine Verstöße. Die manuelle Prüfung mit einem Screenreader steht noch aus.

## Projektstruktur

```
app/Services        Geschäftslogik: Buchen, Umbuchen, Angebote, Wunschverteilung, Teilnehmer, Löschlauf
app/Http            Controller, Requests, Middleware (Anmeldung über REMOTE_USER, HTTPS, Hausnetz, Header)
app/Directory       AD-Anbindung (LDAPS), Cache, statischer Adapter für Entwicklung
app/Events          Ereignisse nach dem Commit; Listener verschicken die E-Mails
app/Mail            Mailables (Text und Markdown)
app/Console         Befehle für Erinnerungen, Wunschfristen und Löschlauf
database/migrations Schema; der eindeutige Index auf active_slot_id verhindert Doppelbuchungen
tests               Feature-Tests (Buchung, Umbuchung, Wunschverteilung, Mails, Löschlauf, Oberfläche) und Unit-Tests
scripts             deploy.ps1 und install-scheduler.ps1 für Windows
```

## Offene Punkte

- Die Beteiligung der Personalvertretung ist nicht geklärt. Sie blockiert die Entwicklung nicht.
- Die Prüfung gegen das echte AD und den Exchange-Server steht bei der Inbetriebnahme noch aus.
