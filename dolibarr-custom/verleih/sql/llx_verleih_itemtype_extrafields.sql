-- Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- Shell table for extrafields on VerleihItemType. Actual extrafield columns
-- are added dynamically by Dolibarr's ExtraFields class when an admin defines
-- them via admin/itemtype_extrafields.php - do not add columns here.

CREATE TABLE llx_verleih_itemtype_extrafields(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
	tms TIMESTAMP,
	fk_object INTEGER NOT NULL,
	import_key VARCHAR(14)
) ENGINE=innodb;
