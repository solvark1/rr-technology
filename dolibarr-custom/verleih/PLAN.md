# Modul „Verleih" — Planungsdokument

Stand: 2026-07-11 | Status: **In Umsetzung**
Modulverzeichnis: `custom/verleih/`

## Entscheidungen (2026-07-11)

- **Nur eine Schule.** Kein Multi-Mandanten-/Multi-Schulen-Datenmodell. Schulname/Adresse/Logo
  für die Etiketten kommen aus den Dolibarr-Stammdaten der eigenen Firma (`$mysoc` /
  Setup → Unternehmen), es wird **kein** eigenes Schule-Objekt und kein `fk_soc` auf den
  Fachobjekten benötigt. Das vereinfacht das Datenmodell in Abschnitt 3 gegenüber der
  ursprünglichen Fassung (kein `Societe`-Bezug mehr auf Klasse/Schüler/Exemplar/Ausleihe).
- **Eigener Objektkatalog**, aber angelehnt an das Produktmodul (Felder wie Ref/Label/
  Kategorie/Hersteller/Beschreibung), keine harte Abhängigkeit vom Produktmodul.
- **Kein Portal vorerst.** Erst der interne Dolibarr-Ablauf, das Portal ist ein späterer,
  separater Schritt.
- **Eigenständiger Label-Generator im Modul**, keine Abhängigkeit zu anderen Custom-Modulen
  (insbesondere nicht zu `dymolabels`/`labelprintx`). Nutzt nur core-TCPDF.

## Technische Objektnamen (Umsetzung)

| Fachbegriff (Plan) | Technischer Klassenname | Tabelle |
|---|---|---|
| Klasse/Jahrgang | `VerleihSchoolClass` | `llx_verleih_schoolclass` |
| Schüler | `VerleihStudent` | `llx_verleih_student` |
| Objekttyp/Katalog | `VerleihItemType` | `llx_verleih_itemtype` |
| Exemplar | `VerleihItem` | `llx_verleih_item` |
| Ausleihe (Kopf) | `VerleihLoan` | `llx_verleih_loan` |
| Ausleihe (Zeile) | `VerleihLoanLine` | `llx_verleih_loan_line` |

Zustandshistorie (Abschnitt 3.7) wird **nicht** als eigene Tabelle umgesetzt (Phase 2/optional) —
für die erste Version reicht der Zustand direkt am Exemplar (`itemcondition`) plus
`condition_out`/`condition_in` je Ausleihe-Zeile.

---

## 1. Zielsetzung

Verwaltung der Ausgabe von Lehrmitteln (Tablets, Bücher, Werkzeuge, Musikinstrumente, ...)
an Schüler über ein Schuljahr hinweg:

- Ausgabe von Objekten (mit Seriennummer + schuleigener Inventarnummer) an Schüler/Klassen
- Rücknahme am Schuljahresende mit Zustandsbewertung (**gut / nutzbar / austausch**)
- Beliebig viele Objektarten (n Objekte) und beliebig viele Schüler (n Schüler)
- Druck von Inventaraufklebern (Schulname, Adresse, Logo, Inventarnummer, ggf. Barcode/QR)

---

## 2. Datenmodell-Entscheidung

**Empfehlung: eigene Objekte statt Produkt/Kunde-Zweckentfremdung.**

Die ursprüngliche Idee (Objekte = Produkte, Schüler = Kunden) funktioniert nur bedingt:

| Dolibarr-Objekt | Problem bei Zweckentfremdung |
|---|---|
| `Product` | bildet nur den **Katalog/Typ** ab (z. B. „iPad 9. Gen"), nicht die einzelne **physische Einheit** mit Seriennummer, Inventarnummer, individuellem Zustand und Ausgabehistorie |
| `Societe` (Kunde) | Schüler sind keine Rechnungsempfänger; Buchhaltungsfelder (Zahlungsziel, USt-ID, Bankverbindung ...) passen nicht und verschmutzen die UI |

**Gewählter Ansatz — Hybrid:**

- **Schule → `Societe` (Thirdparty) wiederverwenden.** Liefert kostenlos Adresse, Logo,
  Ansprechpartner (Contacts), Suchfunktion. Wird als eigener Typ/Tag „Schule" markiert.
- **Objekttyp/Katalog → optional `Product` wiederverwenden**, wenn das Produktmodul aktiv
  ist (liefert Bild, Kategorie, Hersteller-Freitext). Fällt sonst auf ein einfaches,
  modul-eigenes Katalog-Objekt zurück. Rein optionale Verknüpfung — nicht zwingend.
- **Schüler, Exemplar (physisches Objekt) und Ausleihe → komplett neue,
  modul-eigene Objekte.** Das ist der Kern des Moduls.

Damit sind sowohl „n Objektarten" als auch „n Schüler" von Anfang an generisch abgebildet,
ohne fremde Module (Verkauf/Rechnung) zu verbiegen.

*Hinweis:* In dieser Dolibarr-Instanz existieren bereits die Module `customassets` und
`customerinventory` (thematisch verwandt: Asset-Tracking je Kunde). Vor Implementierungsstart
lohnt ein kurzer Blick auf deren Datenmodell/Code — ggf. lassen sich Muster oder sogar
Code-Bausteine (DAO-Grundgerüst, Label-PDF-Logik) übernehmen und Aufwand sparen.

---

## 3. Datenmodell im Detail

### 3.1 Schule
→ bestehendes `llx_societe` (Thirdparty), kein neues Objekt nötig. Typ/Tag „Schule".

### 3.2 Klasse/Jahrgang (`llx_verleih_klasse`) — optional, klein
| Feld | Beschreibung |
|---|---|
| rowid | PK |
| fk_soc | Schule |
| bezeichnung | z. B. „5a", „Jahrgang 7" |
| schuljahr | z. B. „2026/2027" |

### 3.3 Schüler (`llx_verleih_schueler`)
| Feld | Beschreibung |
|---|---|
| rowid | PK |
| fk_soc | Schule |
| fk_klasse | aktuelle Klasse (nullable) |
| vorname, nachname | |
| schueler_nr | optional, schuleigene ID |
| status | aktiv / inaktiv / abgegangen |
| entity, date_creation, tms | Dolibarr-Standardfelder |

### 3.4 Objekttyp/Katalog (`llx_verleih_objekttyp`)
| Feld | Beschreibung |
|---|---|
| rowid | PK |
| ref, bezeichnung | z. B. „Tablet iPad 9. Gen" |
| kategorie | Tablet / Buch / Werkzeug / Instrument / ... (frei erweiterbar) |
| hersteller | Freitext |
| fk_product | optionaler Link auf `llx_product`, falls Produktmodul genutzt wird |

### 3.5 Exemplar — physisches Objekt (`llx_verleih_exemplar`) — **Kernobjekt**
| Feld | Beschreibung |
|---|---|
| rowid | PK |
| fk_objekttyp | Verweis auf Katalog |
| fk_soc | Eigentümer-Schule (wichtig bei mehreren Schulen im selben Mandanten) |
| seriennummer | Herstellerseriennummer |
| inventarnummer | schuleigene Nummer, eindeutig je Schule |
| anschaffungsdatum | optional |
| zustand_aktuell | neu / gut / nutzbar / austausch / defekt |
| status | im_lager / ausgegeben / in_reparatur / ausgemustert |
| bemerkung | Freitext |
| entity, fk_user_creat, date_creation, tms | Standardfelder |

### 3.6 Ausleihe — Kopf/Zeilen-Modell (`llx_verleih_ausleihe` + `llx_verleih_ausleihe_ligne`)

Kopf/Zeilen, damit z. B. **30 Tablets in einem Vorgang an eine ganze Klasse** ausgegeben
und am Schuljahresende ebenso gesammelt zurückgenommen werden können.

**Kopf** (`llx_verleih_ausleihe`): rowid, ref, fk_soc, fk_klasse, schuljahr,
ausgabedatum, geplantes_rueckgabedatum, fk_user_ausgabe, status
(entwurf/aktiv/teilweise_zurueck/abgeschlossen), bemerkung

**Zeile** (`llx_verleih_ausleihe_ligne`): rowid, fk_ausleihe, fk_exemplar, fk_schueler,
zustand_bei_ausgabe, zustand_bei_ruecknahme, ruecknahmedatum, fk_user_ruecknahme, bemerkung

### 3.7 Zustandshistorie (`llx_verleih_zustandshistorie`) — optional, für Mehrjahresverlauf
rowid, fk_exemplar, datum, zustand, bemerkung, fk_user, fk_ausleihe_ligne

---

## 4. Modulstruktur (Dolibarr-Konventionen)

```
custom/verleih/
├── core/modules/modVerleih.class.php     ← Modulbeschreibung (Numero, Menüs, Rechte, Tabs)
├── core/triggers/                        ← optional: Statuswechsel-Logging
├── admin/setup.php                       ← Einstellungen (Zustandskategorien, Label-Layout)
├── class/
│   ├── verleihschueler.class.php
│   ├── verleihobjekttyp.class.php
│   ├── verleihexemplar.class.php
│   ├── verleihausleihe.class.php         ← Kopf, mit Zeilen-Handling
│   └── verleihklasse.class.php
├── sql/                                   ← llx_verleih_*.sql + .key.sql
├── langs/de_DE/verleih.lang               ← Deutsch als Leitsprache
├── langs/en_US/verleih.lang
├── schueler_list.php / schueler_card.php
├── exemplar_list.php / exemplar_card.php
├── ausleihe_list.php / ausleihe_card.php  ← Ausgabe- & Rücknahme-Workflow
├── etiketten.php                          ← Label-PDF-Generator (Einzel/Stapel)
├── index.php                              ← Dashboard
└── PLAN.md                                ← dieses Dokument
```

Modul-Numero: **501000** (geprüft: kollisionsfrei mit allen in `custom/*/core/modules/*.php`
vergebenen IDs in diesem Bereich, Stand heute).

---

## 5. Workflow

1. **Stammdaten pflegen**: Schule (Societe), Klassen, Schüler, Objekttypen anlegen.
2. **Inventar erfassen**: Exemplare anlegen (einzeln oder CSV-Import) → Status „im Lager".
3. **Etiketten drucken**: Inventaraufkleber je Exemplar (Schulname/Adresse/Logo/Inventarnummer,
   optional Barcode/QR mit Inventarnummer) — einzeln oder als Stapel für neu angeschaffte Geräte.
4. **Ausgabe** (Schuljahresbeginn): neue Ausleihe anlegen, Klasse wählen, Zuordnung
   Exemplar↔Schüler (automatisch oder manuell) → Status der Exemplare wechselt auf „ausgegeben".
5. **Rücknahme** (Schuljahresende): Rücknahme-Assistent pro Ausleihe-Kopf, je Zeile Zustand
   erfassen (gut/nutzbar/austausch) → Exemplarstatus wird aktualisiert, „austausch"-Geräte
   landen auf einer Ausmusterungs-/Ersatzliste.
6. **Reporting**: offene Ausleihen, Zustandsverteilung, Verlust-/Schadensfälle je Klasse/Schule.

---

## 6. Rechte-/Rollenkonzept

| Recht | Zweck |
|---|---|
| `lire` | Lesezugriff Inventar/Ausleihen |
| `schreiben` | Stammdaten & Ausleihen anlegen/bearbeiten |
| `ausgeben` | Ausgabe/Rücknahme durchführen (z. B. Klassenlehrer) |
| `loeschen` | Löschrecht |
| `etiketten_drucken` | Etikettendruck |
| `administration` | Konfiguration, Zustandskategorien, Label-Layout |

---

## 7. Etikettendruck

- TCPDF-basierter Label-Sheet-Generator (analog zu bestehenden Ansätzen wie den
  bereits installierten Modulen `dymolabels`/`labelprintx` — **vor Eigenentwicklung kurz
  prüfen, ob eines davon direkt wiederverwendet/integriert werden kann**, das spart
  potenziell 1–2 PT).
- Inhalt je Etikett: Schullogo, Schulname, Adresse, Inventarnummer, optional Barcode/QR.
- Layoutkonfigurierbar (Etikettengröße/Bogenformat) im Setup.
- Einzeldruck (aus Exemplarkarte) und Stapeldruck (Mehrfachauswahl/CSV-Neuzugänge).


