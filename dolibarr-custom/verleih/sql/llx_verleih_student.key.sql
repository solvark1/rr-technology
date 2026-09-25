-- Copyright (C) 2026 Kim Wittkowski <kim@wittkowski-it.de>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

ALTER TABLE llx_verleih_student ADD INDEX idx_verleih_student_entity (entity);
ALTER TABLE llx_verleih_student ADD INDEX idx_verleih_student_fk_schoolclass (fk_schoolclass);
