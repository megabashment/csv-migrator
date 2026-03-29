# CSV Migrator — Portfolio Demo

Ein schlankes PHP-Tool zum Hochladen und Analysieren von CSV-Dateien.  
**Kein Framework. Kein Database-Setup. Läuft auf jedem PHP 7.4+ Webspace (inkl. Strato).**

![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
![No Framework](https://img.shields.io/badge/Framework-none-green)
![No DB required](https://img.shields.io/badge/Database-not%20required%20for%20v1-green)

---

## Was es macht (V1)

- **CSV Upload** per Klick oder Drag & Drop
- **Auto-Delimiter-Erkennung** (`,` `;` `TAB` `|`)
- **Encoding-Erkennung** (UTF-8, ISO-8859-1, Windows-1252)
- **Spalten-Typen** automatisch erkannt: `text` `integer` `decimal` `date`
- **Live-Vorschau**: erste 100 Zeilen als Tabelle
- **Spalten-Statistiken**: Unique-Count, Fill-Rate, Min/Max
- **Datenschutz**: Upload-Dateien werden nach der Analyse sofort gelöscht

---

## Deployment auf Strato (oder jedem PHP-Webspace)

```bash
# 1. Dateien hochladen (FTP oder Git)
scp -r csv-migrator/ user@deinserver.de:/html/csv-migrator/

# 2. Uploads-Verzeichnis beschreibbar machen
chmod 755 csv-migrator/uploads/

# 3. Fertig — kein .env, kein Composer, keine DB
```

**PHP-Anforderungen:** 7.4+, `fileinfo`, `mbstring` (auf Strato standardmäßig aktiv)

---

## Roadmap

- [x] V1: Upload + Vorschau + Spalten-Analyse
- [ ] V2: Mapping-Interface (Spalte → DB-Feldname umbenennen)
- [ ] V3: MariaDB-Import (CREATE TABLE + INSERT automatisch)
- [ ] V4: Export (JSON, SQL-Dump)
- [ ] V5: Multi-File-Merge

---

## Warum dieses Projekt?

Fast jeder KMU-Kunde hat irgendwo eine Excel/CSV-Datei mit Kundendaten, Produktlisten  
oder Bestellhistorien — und kein sauberes System dahinter. Dieses Tool ist der erste  
Schritt einer vollständigen Migration zu einer strukturierten Datenbank.

**Freelance-Einsatz:** 300–1.500 € pro Migrationsprojekt je nach Datenkomplexität.

---

## Lizenz

MIT — frei verwendbar, auch kommerziell.
