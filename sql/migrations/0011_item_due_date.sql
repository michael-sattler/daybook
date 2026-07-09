-- Date-only due date on items (no time component).
ALTER TABLE items
  ADD COLUMN due_date DATE NULL AFTER status_id;
