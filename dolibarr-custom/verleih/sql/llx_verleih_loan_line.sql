-- Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE llx_verleih_loan_line(
	rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
	fk_loan INTEGER NOT NULL,
	fk_item INTEGER NOT NULL,
	fk_student INTEGER NOT NULL,
	condition_out INTEGER NOT NULL,
	condition_in INTEGER,
	date_return DATE,
	fk_user_return INTEGER,
	note VARCHAR(255),
	date_creation DATETIME NOT NULL,
	tms TIMESTAMP
) ENGINE=innodb;
