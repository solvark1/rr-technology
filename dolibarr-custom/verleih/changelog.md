# Changelog - Modul Verleih

## 1.3.0 - 2026-07-11

- Neu: "Ausleihen mit Code" (`loan_scan.php`) — Schüler aus der Klassenliste anklicken,
  dann Barcode des Exemplars scannen (Inventarnummer, wie auf den Inventaraufklebern
  gedruckt), Zuordnung erfolgt automatisch, danach automatischer Sprung zum nächsten noch
  offenen Schüler. Kein JavaScript nötig - Barcode-Scanner verhalten sich wie eine
  Tastatur und lösen den Formular-Submit über Enter aus. Erreichbar über den neuen Button
  "Ausleihen mit Code" auf der Ausleihe-Karte (nur solange die Ausleihe im Entwurf ist).
  Nutzt dieselbe `VerleihLoan::addLine()`-Logik (inkl. Belegungsschutz) wie die manuelle
  Positions-Erfassung.

## 1.2.0 - 2026-07-11

- Neu: Dolibarr-Standard-Extrafelder für Klassen, Schüler, Objekttypen und Exemplare.
  Definition je Objekt unter *Verleih → Einstellungen → Zusatzfelder* (eigene
  `admin/*_extrafields.php`-Seiten, wie im Dolibarr-Kern üblich). Auf den Karten
  (Anlegen/Bearbeiten/Ansicht) über `$object->showOptionals()` eingebunden.
- Neu: Extrafelder sind in allen vier klassischen Listenansichten über den
  Standard-"Hamburger"-Spaltenwähler (Kästchen-Symbol rechts über der Tabelle) einzeln
  ein-/ausblendbar, inkl. dauerhafter Speicherung der Auswahl je Benutzer — exakt wie im
  Dolibarr-Standard (z. B. bei Produkten). Umgesetzt über `$arrayfields` +
  `multiSelectArrayWithCheckbox()`. Suche nach Extrafeld-Werten in der Liste ist (noch)
  nicht enthalten, nur Anzeige.
- Die neue Kachelansicht (1.1.0) zeigt vorerst keine Extrafelder, nur die klassische
  Tabellenansicht.

## 1.1.0 - 2026-07-11

- Neu: zweites, moderneres UI als Zusatzoption (Kachelansicht) für alle Hauptlisten,
  Reports und Dashboard, umschaltbar über "Liste"/"Kacheln"-Buttons, Wahl bleibt
  session-übergreifend erhalten. Klassische Ansicht bleibt unverändert Standard.
  Design-System `css/verleih.css` (adaptiert aus `protoflow/css/protoflow.css`).
  Details/Nachschlagewerk: `css_changes.md`.
- Neu: Menüpunkt "Listen" mit zwei Auswertungen inkl. CSV-Export — "Ausleihen je Schüler"
  und "Verteilung nach Objekttyp" (mit aktuellem Inhaber).
- Neu: Quervernetzung der Kartenansichten — Klasse zeigt ihre Schüler, Schüler zeigt seine
  ausgeliehenen Objekte, Exemplar zeigt seine komplette Ausleihhistorie.
- Fix: dasselbe Exemplar konnte mehrfach (auch über verschiedene Ausleihen hinweg) vergeben
  werden, da der Item-Status erst beim tatsächlichen Ausgeben wechselt. `addLine()` prüft
  jetzt zusätzlich auf offene Ausleihe-Zeilen für das Exemplar.
- Fix: Schülerauswahl im Ausleihe-Formular berücksichtigt jetzt die Klasse der Ausleihe.
- **Vom User im Browser getestet und als funktionierend bestätigt (2026-07-11).**

## 1.0.0 - 2026-07-11

- Erste Umsetzung: Modulgerüst, Datenmodell (Klasse, Schüler, Objekttyp, Exemplar,
  Ausleihe Kopf/Zeilen), Stammdaten-CRUD, Ausleihe-Workflow (Ausgabe/Rücknahme mit
  Zustandsbewertung gut/nutzbar/austausch/defekt), eigenständiger Etiketten-PDF-Generator,
  Dashboard, Setup-Seite.
