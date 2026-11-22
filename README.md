# HG-content-plus

WordPress-Plugin: runder "+-Button" auf Galerie-Bildern (Container mit CSS-Klasse `mpii`), der pro Bild einen formatierten Infotext (Artikelnummer | Typ | Preis ...) einblendet. Vorbild: das "+"-Info-Overlay auf manuschmuck.de.

## Aufbau

- `HG-content-plus/HG-content-plus.php` - Plugin (Mediathek-Feld + TinyMCE-Editor, Einstellungen unter Einstellungen > Content Plus, Ajax-Endpunkt, GitHub-Updater)
- `HG-content-plus/hgcp.js` - Frontend (Button/Popup-Logik)

## Aktivierung im Frontend

Dem Tatsu-/Oshine-Modul (oder einem DIV um einzelne Bilder) die CSS-Klasse `mpii` geben. Optionale Overrides pro Element: `mpii-links`, `mpii-rechts`, `mpii-oben`, `mpii-unten`.

## Text pflegen

Mediathek -> Bild -> Datei-Detailseite: Box "Bild-Infotext" mit Format-Editor (fett, kursiv, Listen, Links). Alternativ Textfeld im Medien-Modal.

## Updates

Dieses Repo ist zugleich der Update-Server: Das Plugin prueft releases/latest (alle 6 h). Fuer ein Update:

1. Versionsnummer im Plugin-Header (Version: x.y.z) erhoehen, aendern committen.
2. GitHub-Release mit Tag vx.y.z erstellen.
3. Als Release-Anhang ein ZIP mit Ordner HG-content-plus/ (mit PHP + JS) hochladen.
4. WordPress zeigt unter Plugins das Update mit "Jetzt aktualisieren".

Historie: Nachfolger von mpi-bild-info; Bild-Infotexte und Einstellungen werden uebernommen.
