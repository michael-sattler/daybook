-- Repair projects that have no project_members rows (breaks assignee dropdown and owner resolution).

-- Accepted invites should have corresponding membership.
INSERT IGNORE INTO project_members (project_id, user_id, role, created_at)
SELECT pi.project_id, u.id, pi.role, COALESCE(pi.accepted_at, pi.created_at)
FROM project_invites pi
INNER JOIN users u ON LOWER(u.email) = LOWER(pi.email)
WHERE pi.accepted_at IS NOT NULL;

-- Stored owner should always be a member.
INSERT IGNORE INTO project_members (project_id, user_id, role, created_at)
SELECT p.id, p.owner_user_id, 'admin', UNIX_TIMESTAMP()
FROM projects p
WHERE p.owner_user_id IS NOT NULL;

-- Projects still without members: use earliest item author as admin/owner.
INSERT IGNORE INTO project_members (project_id, user_id, role, created_at)
SELECT p.id, (
  SELECT i.created_by_user_id
  FROM items i
  WHERE i.project_id = p.id AND i.created_by_user_id IS NOT NULL
  ORDER BY i.created_at, i.id
  LIMIT 1
), 'admin', UNIX_TIMESTAMP()
FROM projects p
WHERE NOT EXISTS (SELECT 1 FROM project_members pm WHERE pm.project_id = p.id)
  AND EXISTS (
    SELECT 1 FROM items i
    WHERE i.project_id = p.id AND i.created_by_user_id IS NOT NULL
  );

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
