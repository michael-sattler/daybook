-- Virtual assignee: "Project Owner" resolves to projects.owner_user_id at read time.
ALTER TABLE items
  ADD COLUMN assigned_to_project_owner TINYINT(1) NOT NULL DEFAULT 0 AFTER assigned_user_id;
