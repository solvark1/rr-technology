-- Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_verleih_loan ADD INDEX idx_verleih_loan_entity (entity);
ALTER TABLE llx_verleih_loan ADD INDEX idx_verleih_loan_fk_schoolclass (fk_schoolclass);
ALTER TABLE llx_verleih_loan ADD UNIQUE INDEX uk_verleih_loan_ref (entity, ref);
