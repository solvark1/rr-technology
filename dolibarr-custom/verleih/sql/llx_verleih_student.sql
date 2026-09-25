-- Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_verleih_student(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
	entity INTEGER DEFAULT 1 NOT NULL,
	fk_schoolclass INTEGER,
	firstname VARCHAR(64) NOT NULL,
	lastname VARCHAR(64) NOT NULL,
	studentnumber VARCHAR(32),
	status INTEGER DEFAULT 1 NOT NULL,
	note VARCHAR(255),
	date_creation DATETIME NOT NULL,
	tms TIMESTAMP,
	fk_user_creat INTEGER
) ENGINE=innodb;
