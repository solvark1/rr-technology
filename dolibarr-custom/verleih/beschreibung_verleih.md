# Beschreibung des Dolibarr Custom Modules **Verleih**

## Überblick
Das Custom-Modul **Verleih** verwaltet die Ausgabe und Rücknahme von Lehrmitteln (Tablets,
Bücher, Werkzeuge, Musikinstrumente, Sportgeräte, ...) an Schüler einer Schule. Zum
Schuljahresbeginn werden Geräte an Klassen bzw. einzelne Schüler ausgegeben, zum
Schuljahresende wieder eingezogen und dabei in ihrem Zustand bewertet (**gut / nutzbar /
austausch**, zusätzlich **neu** und **defekt**). Das Modul druckt außerdem Inventaraufkleber
für die einzelnen Exemplare und bietet Auswertungen ("Listen") über Bestand und Ausleihen.

Das Modul ist bewusst **eigenständig** aufgebaut: keine Abhängigkeit zu anderen Custom-Modulen
(auch der Etikettendruck nutzt nur Dolibarr-Core-TCPDF, nicht z. B. `dymolabels`/`labelprintx`).
Schulname, -adresse und -logo für die Etiketten stammen aus den Dolibarr-Firmenstammdaten
(`$mysoc`), da das Modul für den Betrieb an **einer** Schule ausgelegt ist.

---

## Zweck & Hauptfunktionen

| Funktion | Beschreibung |
|---|---|
| **Klassenverwaltung** | Klassen/Jahrgänge mit Bezeichnung und Schuljahr (`VerleihSchoolClass`). |
| **Schülerverwaltung** | Schüler mit Zuordnung zu einer Klasse, Schülernummer, Status (`VerleihStudent`). |
| **Objektkatalog** | Beliebig viele Objekttypen/Gerätekategorien mit Referenz, Bezeichnung, Kategorie, Hersteller (`VerleihItemType`) — eigenständig, keine Abhängigkeit vom Dolibarr-Produktmodul. |
| **Exemplarverwaltung** | Jedes physische Gerät als eigener Datensatz mit Seriennummer, schuleigener Inventarnummer, Zustand und Status (im Lager/ausgegeben/in Reparatur/ausgemustert) (`VerleihItem`). |
| **Ausleihe (Ausgabe & Rücknahme)** | Kopf-/Zeilen-Modell (`VerleihLoan` + `VerleihLoanLine`): eine Ausleihe kann mehrere Exemplar-Schüler-Zuordnungen enthalten, z. B. für die Sammelausgabe an eine ganze Klasse. Rücknahme erfasst je Position den Zustand bei Rückgabe. |
| **Automatische Statuslogik** | Beim Ausgeben wechseln Exemplare auf „ausgegeben"; bei der Rücknahme automatisch zurück auf „im Lager" — außer der Zustand wird als „defekt" erfasst, dann wechselt das Exemplar auf „in Reparatur". |
| **Belegungsschutz** | Ein Exemplar kann nicht doppelt (auch nicht über verschiedene Ausleihen hinweg) vergeben werden, solange es noch nicht zurückgegeben wurde. |
| **Etikettendruck** | PDF-Inventaraufkleber (Schullogo, -name, -adresse, Inventarnummer, Barcode) für einzelne oder mehrere ausgewählte Exemplare. |
| **Listen/Auswertungen** | „Ausleihen je Schüler" (alles, was ein Schüler ausgeliehen hat, mit CSV-Export) und „Verteilung nach Objekttyp" (alle Exemplare eines Typs mit aktuellem Inhaber, mit CSV-Export). |
| **Quervernetzte Ansichten** | Von der Klasse zu ihren Schülern, vom Schüler zu seinen ausgeliehenen Objekten, vom Exemplar zu seiner kompletten Ausleihhistorie — alles gegenseitig verlinkt. |
| **Dashboard** | Kennzahlen auf einen Blick: Exemplare im Lager/ausgegeben/in Reparatur, offene Ausleihen, aktive Schüler. |

---

## Voraussetzungen

- Dolibarr **Version 21 oder neuer** (getestet mit V22).
- Keine weiteren Custom-Module erforderlich.
- Modulnummer **501000** (registriert in der lokalen Helper-Datenbank
  `dolibarr_development_helper.custom_modules`, um Kollisionen mit anderen Modulen in dieser
  Installation zu vermeiden).

---

## Installation & Aktivierung

1. Der Ordner `verleih` liegt bereits unter `/custom/` dieser Dolibarr-Instanz.
2. *Start → Einrichtung → Module → Module* → „Verleih" suchen und aktivieren. Dabei werden
   automatisch die sechs Tabellen (`llx_verleih_schoolclass`, `_student`, `_itemtype`,
   `_item`, `_loan`, `_loan_line`) angelegt.
3. Rollen/Berechtigungen prüfen (siehe Tabelle unten) — Standardnutzer erhalten nur
   Leserecht, die übrigen Rechte müssen bewusst vergeben werden.
4. Nach Änderungen am Menü (z. B. neue Menüpunkte durch ein Modul-Update) muss das Modul
   einmal deaktiviert und wieder aktiviert werden, damit Dolibarr die Menüeinträge neu in
   die Datenbank schreibt.

### Rechte

| Recht | Zweck |
|---|---|
| `lire` | Lesezugriff auf Stammdaten, Bestand, Ausleihen und Listen |
| `creer` | Stammdaten und Ausleihen (inkl. Positionen) anlegen/bearbeiten |
| `supprimer` | Löschen |
| `ausgeben` | Ausgabe (Checkout) und Rücknahme (Checkin) tatsächlich durchführen |
| `etiketten` | Etikettendruck |
| `configurer` | Modul-Setup (Etikettenlayout) |

---

## Datenmodell auf einen Blick

```
VerleihSchoolClass (Klasse)
   └── VerleihStudent (Schüler, fk_schoolclass)

VerleihItemType (Objekttyp/Katalog)
   └── VerleihItem (Exemplar, fk_itemtype, Seriennummer, Inventarnummer, Zustand, Status)

VerleihLoan (Ausleihe-Kopf: Klasse, Schuljahr, Ausgabedatum, Status)
   └── VerleihLoanLine (Zeile: fk_item, fk_student, Zustand bei Aus-/Rückgabe, Rückgabedatum)
```

Ein Exemplar gilt als „offen zugeordnet", solange eine `VerleihLoanLine` mit `date_return
IS NULL` existiert — darüber wird sowohl die Mehrfachvergabe verhindert als auch der
aktuelle Inhaber in den Listen/Ansichten ermittelt.

---

## Nutzung im Alltag: typischer Jahres-Workflow

### 1. Stammdaten pflegen (einmalig / bei Bedarf)
- Klassen anlegen (*Verleih → Klassen*).
- Schüler je Klasse anlegen (*Verleih → Schüler*), oder direkt über die Klassenkarte
  einsehen, welche Schüler bereits zugeordnet sind.
- Objekttypen anlegen (*Verleih → Objekttypen*), z. B. „IPad 6", „Schulbuch Mathematik 7".
- Exemplare je Objekttyp anlegen (*Verleih → Exemplare*) mit Seriennummer und
  Inventarnummer.

### 2. Etiketten drucken (bei Neuzugang)
- *Verleih → Etiketten* → Exemplare auswählen → PDF mit Schullogo/-adresse, Objekttyp,
  Inventarnummer und Barcode wird erzeugt und heruntergeladen.

### 3. Ausgabe zum Schuljahresbeginn
- *Verleih → Ausleihen → Neu* → Klasse und Schuljahr wählen, Ausgabedatum setzen.
- In der neu angelegten (noch im Entwurf befindlichen) Ausleihe je Schüler ein Exemplar
  zuordnen ("Position hinzufügen") — die Auswahllisten zeigen automatisch nur Schüler der
  gewählten Klasse und nur Exemplare, die aktuell im Lager und nicht bereits anderweitig
  offen zugeordnet sind.
- **Alternative für viele Schüler auf einmal: „Ausleihen mit Code"** — Schüler aus der
  Klassenliste anklicken, Barcode des Exemplars scannen (Inventarnummer, wie auf dem
  Inventaraufkleber), Zuordnung erfolgt automatisch, danach Sprung zum nächsten offenen
  Schüler. Kein zusätzliches Gerät nötig, ein handelsüblicher USB-Barcode-Scanner reicht
  (verhält sich wie eine Tastatur).
- Ist die Zuordnung vollständig: Button **„Ausgeben"** → alle zugeordneten Exemplare
  wechseln auf Status „ausgegeben", die Ausleihe wird „aktiv".

### 4. Rücknahme zum Schuljahresende
- Die aktive Ausleihe öffnen, betroffene Positionen ankreuzen, je Position den
  zurückgegebenen **Zustand** wählen (gut/nutzbar/austausch/defekt) → **„Rücknahme"**.
- Exemplare wechseln automatisch zurück auf „im Lager" — außer bei Zustand „defekt", dann
  auf „in Reparatur". Werden alle Positionen einer Ausleihe zurückgenommen, schließt sich
  die Ausleihe automatisch ab.

### 5. Auswertung
- *Verleih → Listen → „Ausleihen je Schüler"*: Schüler wählen → komplette Ausleih-Historie
  dieses Schülers, mit CSV-Export für z. B. Elterngespräche oder Schadensfälle.
- *Verleih → Listen → „Verteilung nach Objekttyp"*: Objekttyp wählen (oder „alle") → Liste
  aller Exemplare mit aktuellem Inhaber (Schüler + Klasse), mit CSV-Export für
  Inventurzwecke.
- Auch direkt über die Karten erreichbar: Klassenkarte zeigt ihre Schüler, Schülerkarte
  zeigt seine ausgeliehenen Objekte, Exemplarkarte zeigt seine komplette Historie.

---

## Beispiel-Workflow: Tablet-Ausgabe an eine Klasse

**Szenario:** Klasse 7b bekommt zu Schuljahresbeginn 2026/2027 jeweils ein IPad 6.

1. Unter *Verleih → Ausleihen → Neu* wird eine Ausleihe mit Klasse „7b (2026/2027)",
   Schuljahr „2026/2027" und Ausgabedatum heute angelegt.
2. Für jeden der z. B. 15 Schüler wird eine Position hinzugefügt: Exemplar (freies IPad 6
   aus dem Lager) + Schüler.
3. Nach Prüfung der Liste: **„Ausgeben"** klicken → alle 15 IPads wechseln auf „ausgegeben",
   die Ausleihe ist „aktiv".
4. Am Ende des Schuljahres wird dieselbe Ausleihe wieder geöffnet, jede Zeile bekommt den
   erfassten Rückgabezustand, **„Rücknahme"** klicken.
5. Ein IPad mit gesprungenem Display wird als „defekt" markiert → wechselt automatisch auf
   „in Reparatur" statt zurück ins reguläre Lager, taucht also in der nächsten Ausgaberunde
   nicht versehentlich wieder auf.
6. Über *Listen → Verteilung nach Objekttyp → IPad 6* lässt sich jederzeit der
   Gesamtzustand des Tablet-Bestands der Schule auswerten und als CSV für die Inventur
   exportieren.

---

## Entwicklerhinweise

- Alle Fachobjekte (`VerleihSchoolClass`, `VerleihStudent`, `VerleihItemType`,
  `VerleihItem`, `VerleihLoan`) erben von `CommonObject` und nutzen das moderne,
  `$fields`-array-getriebene CRUD (`createCommon`/`fetchCommon`/`updateCommon`/
  `deleteCommon`), analog zum offiziellen Modulebuilder-Template.
- `VerleihLoan` enthält die eigentliche Fachlogik: `addLine()` (mit Belegungsschutz),
  `checkout()` (Ausgabe, setzt Item-Status), `processReturn()` (Rücknahme inkl.
  Zustandslogik), `getNextNumRef()` (Referenzvergabe `AL-JJJJ-NNNN`).
- `VerleihLabelPdf` ist ein eigenständiger TCPDF-Wrapper (`core/lib/pdf.lib.php` von
  Dolibarr, keine weiteren Abhängigkeiten), konfigurierbares Spalten-/Zeilenraster über die
  Konstanten `VERLEIH_LABEL_COLS`/`VERLEIH_LABEL_ROWS` (Setup-Seite).
- Bewusst **nicht** umgesetzt (siehe `PLAN.md`): separate Zustandshistorien-Tabelle
  (Zustand wird direkt am Exemplar sowie je Ausleihe-Zeile geführt), Schüler-/Elternportal,
  Barcode-Scanner-Integration, CSV-Bulk-Import von Stammdaten.

---

## Changelog (Kurzfassung)

Siehe `changelog.md` im Modulordner für die vollständige Liste. Wichtigste Meilensteine:
- **1.0.0**: Erstumsetzung — Datenmodell, Stammdaten-CRUD, Ausleihe-Workflow, Etikettendruck,
  Dashboard, Setup.
- Nachträgliche Fixes/Erweiterungen: Kollisionsschutz gegen doppelte Exemplar-Vergabe,
  klassenbezogene Schülerfilterung im Ausleihe-Formular, Quervernetzung
  Klasse↔Schüler↔Exemplar, Listen/Reports mit CSV-Export.

---

## Fazit
**Verleih** bildet den kompletten Jahreszyklus der Lehrmittelausgabe an einer Schule ab —
von der Stammdatenpflege über Sammelausgabe und -rücknahme mit Zustandsbewertung bis zum
Etikettendruck und zu Bestandsauswertungen — als eigenständiges, abhängigkeitsfreies
Dolibarr-Modul.

*Stand: Juli 2026*
*Autor: Kim Wittkowski <kim@wittkowski-it.de>*
*Basierend auf `PLAN.md`, `readme.md`, `changelog.md` sowie den Klassen- und Seitendateien
des Moduls.*
