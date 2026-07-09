-- Backfill projects.owner_user_id from the earliest admin member when unset.
UPDATE projects p
SET owner_user_id = (
  SELECT pm.user_id
  FROM project_members pm
  WHERE pm.project_id = p.id AND pm.role = 'admin'
  ORDER BY pm.created_at, pm.id
  LIMIT 1
)
WHERE p.owner_user_id IS NULL;
