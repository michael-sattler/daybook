-- Repair projects that have members but no resolvable owner (no stored owner_user_id
-- and no admin member). Without a resolvable owner the "Project Owner" assignee option
-- falls back to a generic label and cannot be stored.

-- Promote the earliest member to admin for projects that have members but no admin
-- and no stored owner.
UPDATE project_members pm
JOIN (
  SELECT sub.project_id, (
    SELECT pm3.user_id
    FROM project_members pm3
    WHERE pm3.project_id = sub.project_id
    ORDER BY pm3.created_at, pm3.id
    LIMIT 1
  ) AS first_user_id
  FROM project_members sub
  JOIN projects p ON p.id = sub.project_id
  WHERE p.owner_user_id IS NULL
  GROUP BY sub.project_id
  HAVING SUM(sub.role = 'admin') = 0
) x ON x.project_id = pm.project_id AND x.first_user_id = pm.user_id
SET pm.role = 'admin';

-- Record the (now guaranteed) first admin member as the stored owner.
UPDATE projects p
SET owner_user_id = (
  SELECT pm.user_id
  FROM project_members pm
  WHERE pm.project_id = p.id AND pm.role = 'admin'
  ORDER BY pm.created_at, pm.id
  LIMIT 1
)
WHERE p.owner_user_id IS NULL
  AND EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id);
