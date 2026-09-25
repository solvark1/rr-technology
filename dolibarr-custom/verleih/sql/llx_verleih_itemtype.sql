-- Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_verleih_itemtype(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity INTEGER DEFAULT 1 NOT NULL,
	ref VARCHAR(32) NOT NULL,
	label VARCHAR(255) NOT NULL,
	itemcategory VARCHAR(64),
	manufacturer VARCHAR(128),
	description TEXT,
	status INTEGER DEFAULT 1 NOT NULL,
	date_creation DATETIME NOT NULL,
	tms TIMESTAMP,
	fk_user_creat INTEGER
) ENGINE=innodb;
