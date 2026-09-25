-- Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_verleih_item(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity INTEGER DEFAULT 1 NOT NULL,
	fk_itemtype INTEGER NOT NULL,
	serialnumber VARCHAR(128),
	inventorynumber VARCHAR(64) NOT NULL,
	purchasedate DATE,
	itemcondition INTEGER DEFAULT 1 NOT NULL,
	status INTEGER DEFAULT 0 NOT NULL,
	note VARCHAR(255),
	date_creation DATETIME NOT NULL,
	tms TIMESTAMP,
	fk_user_creat INTEGER
) ENGINE=innodb;
