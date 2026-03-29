# CLAUDE.md — CSV Migrator

## Projekt-Übersicht

Ein schlankes PHP-Tool zur Analyse und Migration von CSV-Dateien in MariaDB.  
Kein Framework, kein Composer, kein Node. Läuft auf jedem PHP 7.4+ Webspace (Strato-kompatibel).

---

## Stack & Constraints

| Was | Details |
|-----|---------|
| Language | PHP 7.4+ |
| Database | MariaDB (ab V3) |
| Frontend | Vanilla JS, kein Framework |
| Hosting | Strato Webspace (shared hosting) |
| Dependencies | Keine (kein Composer) |
| PHP Extensions | `fileinfo`, `mbstring` (Strato-Standard) |

**Wichtig:** Kein `exec()`, kein `shell_exec()` — Strato erlaubt das nicht.  
**Wichtig:** Kein `.htaccess`-Rewriting voraussetzen — optionales Feature.

---

## Dateistruktur

```
csv-migrator/
├── index.php          # Haupt-App (Upload + Vorschau) — V1 fertig
├── CLAUDE.md          # Diese Datei
├── README.md          # GitHub-Doku
├── .gitignore
└── uploads/           # Temporäre Uploads (werden sofort gelöscht)
    └── .gitkeep
```

**Geplante Erweiterungen:**
```
├── map.php            # V2: Mapping-Interface
├── import.php         # V3: MariaDB-Import
├── export.php         # V4: Export (JSON, SQL-Dump)
└── config.php         # Ab V3: DB-Credentials (nicht in Git!)
```

---

## Aktuelle Version: V1

### Was fertig ist
- CSV Upload (Klick + Drag & Drop)
- Auto-Delimiter-Erkennung: `,` `;` `TAB` `|`
- Encoding-Erkennung + Konvertierung → UTF-8 (ISO-8859-1, Windows-1252)
- Spalten-Typen: `text` `integer` `decimal` `date`
- Spalten-Statistiken: unique count, fill rate, min/max
- Daten-Vorschau: erste 100 Zeilen
- Datei wird nach Analyse sofort gelöscht (GDPR-clean)

### Kern-Funktionen in index.php

```php
handleUpload(array $file): array        // Upload-Handling + Validierung
parseCSV(string $path, string $name): array  // CSV einlesen + analysieren
detectDelimiter(string $sample): string // Trennzeichen erkennen
detectColumnTypes(array $rows, int $n): array // Typen pro Spalte
buildColStats(array $rows, ...): array  // Statistiken pro Spalte
```

---

## Roadmap

### V2 — Mapping-Interface (`map.php`)
- Spalten umbenennen (CSV-Header → DB-Feldname)
- Datentyp pro Spalte manuell überschreiben
- Leere Spalten ignorieren/überspringen
- State wird per PHP Session zwischen V1 → V2 → V3 weitergegeben

### V3 — MariaDB-Import (`import.php`)
- `CREATE TABLE` Statement automatisch aus Mapping generieren
- `INSERT` Batches (1000 Rows pro Query)
- Fehler-Logging: welche Zeilen konnten nicht importiert werden?
- Dry-Run Modus: SQL-Vorschau ohne tatsächlichen Import

### V4 — Export (`export.php`)
- SQL-Dump Download
- JSON Export
- Bereinigtes CSV (UTF-8, einheitlicher Delimiter)

### V5 — Multi-File Merge
- Mehrere CSVs mit gleicher Struktur zusammenführen
- Duplikat-Erkennung per konfigurierbarem Key-Feld

---

## Coding-Regeln für dieses Projekt

1. **Eine Datei pro Feature** — kein Monolith über V1 hinaus
2. **Kein Output-Buffering** — PHP direkt ausgeben
3. **Alle User-Inputs escapen** — `htmlspecialchars()` immer
4. **Prepared Statements** — ab V3 für alle DB-Queries
5. **Session-basierter State** — kein localStorage, kein Cookie-Trick
6. **Max. 100 Rows Vorschau** — Konstante `MAX_PREVIEW_ROWS`
7. **Max. 5 MB Upload** — Konstante `MAX_FILE_SIZE`
8. **Uploads sofort löschen** — nach Parse-Schritt immer `unlink()`

---

## Design-System

```css
--bg: #0e0e10          /* Hintergrund */
--surface: #18181c     /* Karten */
--surface2: #222228    /* Table-Header, Hover */
--border: #2e2e38      /* Borders */
--accent: #6aff8e      /* Grün — Erfolg, Zahlen, CTAs */
--accent2: #5b6bff     /* Blau — Typen, Links */
--warn: #ffb347        /* Orange — Warnungen, Date-Typ */
--text: #e8e8f0        /* Haupttext */
--muted: #7a7a90       /* Labels, Metadaten */
--font-head: 'Syne'    /* Überschriften, UI */
--font-mono: 'Space Mono' /* Code, Labels, Tabellen */
```

---

## Typische Kunden-Anfragen (Freelance-Kontext)

- "Ich habe eine Excel-Exportdatei mit 5.000 Kunden — kannst du die in meine Datenbank importieren?"
- "Unsere alte Software exportiert nur CSV — wir brauchen das in MySQL"
- "Die Datei hat komische Sonderzeichen" → Encoding-Problem (häufigster Fall)
- "Manche Spalten brauchen wir nicht" → V2 Mapping
- "Kannst du das automatisch jeden Montag machen?" → V5+ / Cron-Job Erweiterung

---

## Nächster Schritt: V2 starten

```
Prompt für Claude Code:
"Baue map.php für das CSV Migrator Projekt. 
Session-State aus index.php nutzen (headers, col_types).
UI: Tabelle mit einer Zeile pro Spalte — 
  [CSV-Header] → [Input: DB-Feldname] [Select: Typ] [Checkbox: ignorieren]
Submit → speichert Mapping in Session → redirect zu import.php (Stub ok)"
```
