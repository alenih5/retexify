# Text Export Import Plugin

Ein vollständiges WordPress-Plugin zum selektiven Exportieren und Importieren von Website-Texten für die KI-gestützte Bearbeitung mit umfassender Page Builder-Unterstützung.

**Author:** alenseo  
**Version:** 1.2.0  
**Tested up to:** WordPress 6.4  
**Requires:** WordPress 5.0+, PHP 7.4+

## 🚀 Neue Features in Version 1.2.0

### ✨ Page Builder-Unterstützung
- **WPBakery Page Builder:** Export/Import von Custom CSS
- **Elementor:** Grundlegende Unterstützung (in Vorbereitung)
- **Beaver Builder:** Erkennung und Unterstützung
- **Divi Builder:** Erkennung und Unterstützung

### 🔧 Advanced Custom Fields (ACF)
- **Text-Felder Export:** Automatische Erkennung von ACF-Text-Feldern
- **Intelligente Filterung:** Nur relevante Text-Inhalte werden exportiert
- **Field-Name Mapping:** Preservation der Feld-Namen beim Import

### 🎯 Erweiterte Plugin-Erkennung
- **SEO-Plugins:** Yoast SEO, Rank Math, All in One SEO, AIOSEO
- **Page Builder:** WPBakery, Elementor, Beaver Builder, Divi, Oxygen
- **Custom Fields:** ACF, CFS, Pods
- **Automatische Konfiguration:** Plugin-spezifische Optimierungen

### 🛠️ Verbesserte System-Integration
- **Systemprüfung:** Automatische Überprüfung der Anforderungen
- **Performance-Monitoring:** Memory-Usage und Connection-Type
- **Erweiterte Debug-Tools:** Detaillierte System- und Plugin-Informationen
- **Robuste Fehlerbehandlung:** Bessere Recovery bei partiellen Fehlern

## Was macht das Plugin?

Das Plugin erfasst **ausgewählte Texte** Ihrer WordPress-Website und exportiert sie in eine benutzerfreundliche CSV-Datei. Nach der Bearbeitung mit KI-Tools können Sie die Texte wieder importieren.

### Verfügbare Textfelder:

- ✅ **Seitentitel** (Post/Page Titles)
- ✅ **Meta-Titel** (SEO-Titel von Yoast, Rank Math, All in One SEO, AIOSEO)
- ✅ **Meta-Beschreibungen** (SEO-Descriptions)
- ✅ **Seiteninhalt** (Post Content)
- ✅ **Excerpts** (Kurzbeschreibungen)
- ✅ **Alt-Texte von Bildern** (inkl. Featured Images)
- 🆕 **WPBakery Custom CSS** (Post CSS und Shortcode CSS)
- 🆕 **ACF Text-Felder** (Automatische Erkennung)

### Unterstützte Post-Typen:
- 📄 Blog-Beiträge (Posts)
- 📄 Seiten (Pages)
- 📄 Alle Custom Post Types
- 🎯 Status-Filter: Veröffentlicht, Entwürfe, Private

### Page Builder Integration:
- 🎨 **WPBakery Page Builder** - Vollständige CSS-Integration
- 🔧 **Elementor** - Basis-Unterstützung (erweitert in zukünftigen Versionen)
- 🏗️ **Beaver Builder** - Plugin-Erkennung
- 🎯 **Divi Builder** - Plugin-Erkennung
- ⚡ **Oxygen Builder** - Plugin-Erkennung

## Installation

### Methode 1: Manuelle Installation

1. Erstellen Sie den Ordner `/wp-content/plugins/text-export-import/`
2. Laden Sie alle Plugin-Dateien in diesen Ordner hoch:
   ```
   text-export-import/
   ├── text-export-import.php          (Hauptdatei)
   ├── assets/
   │   ├── admin-style.css             (CSS)
   │   └── admin-script.js             (JavaScript)
   └── README.md                       (Diese Datei)
   ```
3. Aktivieren Sie das Plugin unter "Plugins" im WordPress-Admin
4. Gehen Sie zu "Werkzeuge" → "Text Export/Import"

### Methode 2: ZIP-Upload

1. Komprimieren Sie alle Dateien in einer ZIP-Datei namens `text-export-import.zip`
2. Laden Sie die ZIP-Datei über "Plugins" → "Installieren" → "Plugin hochladen" hoch
3. Aktivieren Sie das Plugin

## 📋 Verwendung

### 1. Texte exportieren

1. Gehen Sie zu **Werkzeuge → Text Export/Import**
2. **Wählen Sie die gewünschten Post-Typen** (Posts, Seiten, etc.)
3. **Wählen Sie die Inhaltsfelder** die exportiert werden sollen
4. **Wählen Sie den Status** (Veröffentlicht, Entwürfe, Private)
5. **Vorschau anzeigen** (optional) - zeigt Anzahl der Einträge
6. Klicken Sie auf **"Auswahl exportieren"**
7. Laden Sie die generierte CSV-Datei herunter

### 2. Texte mit KI bearbeiten

- Öffnen Sie die CSV-Datei in Excel, Google Sheets oder einem Texteditor
- Bearbeiten Sie die Texte in den entsprechenden Spalten
- Verwenden Sie KI-Tools wie ChatGPT, Claude oder Gemini
- **Wichtig:** Lassen Sie die Spalten ID, Typ und URL unverändert
- Speichern Sie die Datei als CSV mit Semikolon als Trennzeichen

### 3. Texte importieren

1. Klicken Sie auf **"CSV-Datei auswählen"** oder verwenden Sie Drag & Drop
2. Akzeptierte Formate: `.csv` und `.xlsx`
3. Klicken Sie auf **"Texte importieren"**
4. **Bestätigen Sie den Import** (nach Backup-Erstellung!)

## 🔒 Sicherheit & Best Practices

### Automatisches Backup (NEU in v1.2.0)
- **Automatisch:** Vor jedem Import wird automatisch ein Backup erstellt
- **Speicherort:** `/wp-content/uploads/text-export-temp/backup-YYYY-MM-DD-HH-MM-SS.csv`
- **Retention:** Backup-Dateien werden nach 24 Stunden automatisch gelöscht
- **Wiederherstellung:** Backup-Dateien können jederzeit wieder importiert werden

### Import-Vorschau (NEU in v1.2.0)
```
📋 Import-Vorschau zeigt:
✓ Anzahl der zu aktualisierenden Posts
✓ Anzahl der zu aktualisierenden Bilder  
✓ Betroffene Post-Typen
⚠️ Warnungen bei fehlenden IDs
⚠️ Ungültige Referenzen
```

### Sicherheitsmaßnahmen
- **Nonce-Verifizierung:** Alle AJAX-Requests sind gegen CSRF geschützt
- **Benutzerrechte:** Nur Administratoren können das Plugin verwenden
- **Input-Sanitization:** Alle importierten Daten werden bereinigt
- **File-Validation:** Strenge Überprüfung von Upload-Dateien
- **Memory-Limits:** Automatische Überwachung des Speicherverbrauchs

### Empfohlene Workflow
1. **Staging-Environment:** Testen Sie Importe zunächst auf einer Kopie
2. **Kleine Batches:** Verarbeiten Sie große Websites in kleineren Portionen
3. **Backup-Verifizierung:** Prüfen Sie das automatische Backup vor dem Import
4. **Vorschau nutzen:** Verwenden Sie die Import-Vorschau zur Validierung
5. **Monitoring:** Überwachen Sie die Debug-Logs bei größeren Importen

### CSV-Format
- **Trennzeichen:** Automatische Erkennung (`;`, `,`, Tab, `|`)
- **Kodierung:** UTF-8 mit BOM (für Excel-Kompatibilität)
- **Leere Felder:** Werden ignoriert (bestehende Inhalte bleiben unverändert)
- **Spaltenreihenfolge:** Darf nicht verändert werden

### Systemanforderungen
- WordPress 5.0 oder höher
- PHP 7.4 oder höher
- Schreibrechte im WordPress Upload-Verzeichnis
- Für Excel-Import: ZIP-Unterstützung in PHP

## 🔧 Troubleshooting

### Import funktioniert nicht
1. **Überprüfen Sie das CSV-Format:**
   ```
   - Erste Zeile muss Header enthalten
   - Mindestens 11 Spalten erforderlich
   - Korrekte Post-IDs in Spalte A
   ```

2. **Debug-Modus aktivieren:**
   ```php
   // In wp-config.php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
   
3. **Fehlerlog prüfen:**
   ```
   /wp-content/debug.log
   ```

4. **Häufige Probleme:**
   - **Upload-Größe:** Prüfen Sie `upload_max_filesize` in PHP
   - **Memory Limit:** Erhöhen Sie `memory_limit` für große Dateien
   - **Execution Time:** Setzen Sie `max_execution_time` höher
   - **Berechtigungen:** Upload-Verzeichnis muss beschreibbar sein

### CSV-Formatierung (Version 1.2.0)
```csv
ID;Typ;URL;Titel;Meta Titel;Meta Beschreibung;Excerpt;Content;WPBakery CSS;ACF Felder;Custom Fields;Elementor Data;Bild ID;Alt Text;Bild Typ
123;post;https://...;Neuer Titel;SEO Titel;SEO Beschreibung;Kurzbeschreibung;Inhalt;.custom{...};field_name: value;meta_key: value;Elementor Text: Content;;;
123;image_alt;;;;;;;;;;;;456;Neuer Alt-Text;Content Image
```

### Page Builder Spezifische Formate

#### WPBakery Page Builder:
```css
/* Post Custom CSS */
.my-custom-class {
    color: #007cba;
    font-weight: bold;
}

/* Shortcodes Custom CSS */
.vc_column {
    padding: 20px;
}
```

#### Elementor Content:
```
Elementor Text: Ihr optimierter Text-Content

Elementor Überschrift: Neue Überschrift

Elementor Button: Call-to-Action Text

Elementor Bildtext: Beschreibender Alt-Text
```

#### ACF und Custom Fields:
```
field_name: Feld-Inhalt

another_field: Weiterer Text-Inhalt

custom_meta: Meta-Feld-Wert
```

### Excel-Import Probleme
- **Verwenden Sie .xlsx Format** (nicht .xls)
- **Speichern Sie als CSV** falls Excel-Import fehlschlägt
- **Prüfen Sie Zellformatierung** (Text, nicht Zahlen)

## 🔌 SEO-Plugin-Kompatibilität

Das Plugin erkennt und unterstützt automatisch:
- **Yoast SEO** (`_yoast_wpseo_title`, `_yoast_wpseo_metadesc`)
- **Rank Math** (`rank_math_title`, `rank_math_description`)
- **All in One SEO Pack** (`_aioseop_title`, `_aioseop_description`)

## 🎯 Funktionen im Detail

### Benutzeroberfläche
- **Moderne WordPress-Integration**
- **Live-Vorschau** der Export-Auswahl
- **Drag & Drop** für Import-Dateien
- **Progress-Indikatoren** für lange Operationen
- **Detaillierte Erfolgs- und Fehlermeldungen**

### Export-Features
- **Intelligente Auswahl:** Nur relevante Inhalte
- **Batch-Verarbeitung:** Große Websites unterstützt
- **UTF-8 Kodierung:** Internationale Zeichen
- **Excel-kompatibel:** Direktes Öffnen in Excel

### Import-Features
- **Robuste Verarbeitung:** Verschiedene CSV-Dialekte
- **Validierung:** Post-IDs und Attachment-IDs werden geprüft
- **Selective Updates:** Nur geänderte Felder werden überschrieben
- **Fehler-Recovery:** Partieller Import bei Fehlern möglich

### Sicherheitsfeatures
- **Nonce-Verifizierung** für alle AJAX-Requests
- **Benutzerrechte-Prüfung** (manage_options erforderlich)
- **Datei-Typ-Validierung** und MIME-Type-Prüfung
- **Input-Sanitization** für alle importierten Daten
- **XSS-Schutz** durch wp_kses_post()

## 📊 Performance-Optimierung

### Für große Websites (>1000 Posts):
```php
// In wp-config.php oder functions.php
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
```

### Server-Konfiguration:
```apache
# .htaccess
php_value upload_max_filesize 50M
php_value post_max_size 50M
php_value max_execution_time 300
php_value memory_limit 512M
```

## 💡 Tipps für KI-Bearbeitung

### Optimale Prompts für ChatGPT/Claude:

```
"Optimiere diese Meta-Titel für bessere SEO und Klickraten. 
Halte sie unter 60 Zeichen und verwende relevante Keywords."

"Verbessere diese Meta-Beschreibungen für höhere CTR. 
Verwende aktive Sprache und einen Call-to-Action. 
Maximal 160 Zeichen."

"Überarbeite diese Alt-Texte für bessere Barrierefreiheit 
und SEO. Beschreibe das Bild präzise und verwende Keywords wo sinnvoll."
```

### Stapelverarbeitung:
- Bearbeiten Sie ähnliche Inhalte zusammen
- Verwenden Sie Excel-Formeln für Wiederholungen
- Nutzen Sie KI-Tools mit CSV-Import-Funktionen

## 📝 Changelog

### Version 1.2.0 - Die Page Builder Edition
- ✨ **NEU:** WPBakery Page Builder - Vollständige Integration (Custom CSS Export/Import)
- ✨ **NEU:** Elementor - Text-Content Export/Import (Heading, Text-Editor, Button, Image-Caption)
- ✨ **NEU:** Advanced Custom Fields (ACF) - Automatische Text-Felder-Erkennung
- ✨ **NEU:** Custom Fields - Meta-Felder Export/Import mit intelligenter Filterung
- ✨ **NEU:** Import-Vorschau - Zeigt betroffene Posts und potentielle Probleme
- ✨ **NEU:** Automatisches Backup - Wird vor jedem Import erstellt
- ✨ **NEU:** Erweiterte Plugin-Erkennung - 15+ unterstützte Plugins
- 🔧 **VERBESSERT:** Robustere Excel-Verarbeitung (.xlsx ohne externe Libraries)
- 🔧 **VERBESSERT:** Systemprüfung bei Plugin-Aktivierung
- 🔧 **VERBESSERT:** Performance-Monitoring und Memory-Usage-Tracking
- 🔧 **VERBESSERT:** Automatische Datei-Bereinigung (täglicher Cleanup)
- 🔧 **VERBESSERT:** Erweiterte Debug-Tools mit detaillierten System-Informationen
- 🔧 **VERBESSERT:** CSV-Format erweitert auf 15 Spalten für neue Features
- 🔧 **VERBESSERT:** Bessere Fehlerbehandlung und Recovery-Mechanismen

### Version 1.1.0
- ✨ **NEU:** Selektiver Export mit Auswahl-Interface
- ✨ **NEU:** Live-Vorschau der Export-Anzahl
- 🔧 **VERBESSERT:** Robuste CSV-Verarbeitung
- 🔧 **VERBESSERT:** Excel-Import ohne externe Bibliotheken
- 🔧 **VERBESSERT:** Detaillierte Fehlerbehandlung und Debugging
- 🔧 **VERBESSERT:** Validierung von Post-IDs vor Update
- 🔧 **VERBESSERT:** Drag & Drop für Import-Dateien
- 🔧 **VERBESSERT:** Progress-Indikatoren und bessere UX

### Version 1.0.2
- Erste öffentliche Version
- Vollständige Export/Import-Funktionalität
- SEO-Plugin-Integration
- Responsive Admin-Interface

## 📄 Lizenz

GPL v2 oder höher - wie WordPress selbst.

## 👨‍💻 Support

Dieses Plugin wurde als vollständige, sofort einsatzbare Lösung entwickelt. Bei Problemen:

1. **Debug-Modus aktivieren** und Logs prüfen
2. **Browser-Konsole** für JavaScript-Fehler öffnen
3. **Datei-Berechtigungen** im Upload-Verzeichnis prüfen
4. **Server-Limits** für große Dateien anpassen

---

**Entwickelt von alenseo** - Ein professionelles WordPress-Plugin für effiziente Content-Bearbeitung mit KI-Tools.