# Verleih

Dolibarr module for managing school equipment rental: lending and returning of
teaching materials (tablets, books, tools, musical instruments, sports equipment,
...) to students, including condition assessment on return and printing of
inventory labels.

**For German-speaking users:** Vollständige deutsche Dokumentation siehe
[beschreibung_verleih.md](beschreibung_verleih.md).

## Overview

This module manages the complete lifecycle of school equipment loans:
- **At the start of the school year:** Equipment is issued to classes or
  individual students
- **During the school year:** Track who has which items, monitor equipment status
- **At the end of the school year:** Items are returned and their condition is
  assessed (**good / usable / replace**, plus **new** and **defective**)
- **Inventory management:** Print inventory labels with barcode for each item,
  scan barcodes during checkout
- **Reporting:** Lists and reports on inventory, loans, and distribution by item
  type or student

The module is deliberately **standalone**: no dependencies on other custom modules
(even label printing uses only Dolibarr core TCPDF, not external label modules).
School name, address, and logo for labels come from Dolibarr company master data
(`$mysoc`), as the module is designed for operation at **one** school.

## Key Features

### Core Objects
- **SchoolClass** — Classes/grades with name and school year
- **Student** — Students assigned to classes, with student number and status
- **ItemType** — Equipment catalog/categories (tablets, books, tools, etc.) —
  independent, no dependency on Dolibarr's product module
- **Item** — Individual physical devices with serial number, school inventory
  number, condition, and status (in stock/on loan/in repair/retired)
- **Loan** (head/lines) — Rental documents that can contain multiple
  item-student assignments, e.g., for batch checkout to an entire class

### Workflow
1. **Checkout (Ausgabe):**
   - Create loan document for a class and school year
   - Assign items to students (manually or via barcode scan)
   - Duplicate protection: an item cannot be assigned twice while still on loan
   - On confirmation, items automatically switch to "on loan" status

2. **Return (Rücknahme):**
   - Record condition for each returned item: good/usable/replace/new/defective
   - Automatic status logic: items return to "in stock" unless marked "defective"
     (then switch to "in repair")

3. **Barcode Scanning:**
   - `loan_scan.php`: Click student from class list, scan item barcode (inventory
     number as printed on labels), assignment happens automatically, then auto-jump
     to next student. No JavaScript needed — barcode scanners behave like keyboards
     and trigger form submit via Enter.

### Inventory Labels
- PDF label generator using TCPDF (Dolibarr core)
- Labels include: school logo, name, address, inventory number, barcode
- Print for individual items or batch-print for multiple selected items
- Label layout configurable in module settings

### Reports & Views
- **"Loans by Student":** Everything a student has borrowed, with CSV export
- **"Distribution by Item Type":** All items of a type with current holder, CSV
  export
- **Cross-linked views:** From class → students → their borrowed items → item
  loan history — everything interconnected
- **Dashboard:** Key metrics at a glance (items in stock/on loan/in repair, open
  loans, active students)

### Modern UI (optional)
- Two UI modes: classic table view (default) and modern tile/card view
- Toggle via "List"/"Tiles" buttons, choice persists across sessions
- Responsive design system in `css/verleih.css`

### Extrafields
- Standard Dolibarr extrafields support for all four main objects
  (classes, students, item types, items)
- Column selector ("hamburger" icon) in list views to show/hide extrafields
- Configuration at *Verleih → Settings → Extra Fields*

## Requirements

- **Dolibarr Version 21 or newer** (tested on V22)
- No other custom modules required
- Module number **501000** (registered in local helper database to avoid collisions)

## Installation

1. Copy the `verleih` folder to your Dolibarr `/custom/` directory
2. Navigate to *Home → Setup → Modules*
3. Find "Verleih" and activate it
4. On activation, six database tables are created automatically:
   - `llx_verleih_schoolclass` (+ extrafields)
   - `llx_verleih_student` (+ extrafields)
   - `llx_verleih_itemtype` (+ extrafields)
   - `llx_verleih_item` (+ extrafields)
   - `llx_verleih_loan`
   - `llx_verleih_loan_line`
5. Configure user permissions (see table below)
6. After menu changes (e.g., module updates), deactivate and reactivate the
   module once to refresh menu entries in the database

## Permissions

| Permission | Purpose |
|------------|---------|
| `lire` | Read access to master data, inventory, loans, and reports |
| `creer` | Create and edit master data and loans (including loan lines) |
| `supprimer` | Delete |
| `ausgeben` | Actually perform checkout and checkin operations |
| `etiketten` | Print inventory labels |
| `configurer` | Configure module settings (label layout) |

**Important:** Standard users receive only read permission — other permissions must
be explicitly granted.

## Data Model

```
VerleihSchoolClass (Class)
   └── VerleihStudent (Student, fk_schoolclass)

VerleihItemType (Item Type/Catalog)
   └── VerleihItem (Item, fk_itemtype, serial#, inventory#, condition, status)

VerleihLoan (Loan Header: class, school year, issue date, status)
   └── VerleihLoanLine (Loan Line: fk_item, fk_student, condition on return)
```

### Automatic Status Logic
- **On Checkout:** Item status changes to "on loan" (ausgegeben)
- **On Return:** Item returns to "in stock" (im Lager)
- **Exception:** If condition is marked "defective" (defekt), item automatically
  switches to "in repair" (in Reparatur)

### Duplicate Protection
Items cannot be assigned to multiple loans simultaneously. The system checks:
1. Item status (must not already be "on loan")
2. Open loan lines (no other active loan line for this item)

## Documentation

- **[PLAN.md](PLAN.md)** — Complete functional and technical planning
  (German only)
- **[beschreibung_verleih.md](beschreibung_verleih.md)** — Comprehensive German
  documentation with detailed workflow descriptions
- **[changelog.md](changelog.md)** — Version history and changes
- **[css_changes.md](css_changes.md)** — UI/CSS design system reference

## Development Status

**Current Version:** 1.3.0

The module is in active development and used productively at a school. All core
features are implemented and tested.

See [changelog.md](changelog.md) for detailed version history.

## Technical Notes

- **Modulebuilder compatible:** Can be installed on different Dolibarr systems
- **No core modifications:** All functionality via proper module structure
- **Database tables:** Created automatically during module activation
- **Clean MVC architecture:** Standard Dolibarr DAO classes, proper separation of
  concerns
- **Follows Dolibarr conventions:** Standard extrafields, menu system, permissions
- **Modern UI optional:** Classic table view remains the default, tile view is
  opt-in

## License

GPL-3.0-or-later

## Author

Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>

## Feedback & Support

This is not an official Dolibarr module, but something built for our own needs.
Feedback and improvement suggestions are welcome!

For questions or issues, please use the GitHub issues section.
