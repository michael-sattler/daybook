# User Layer

## Purpose

Allow more than one user to access the Daybook tool, with project-scoped roles and lightweight account management. Manageable above all else — no platform bloat.

---

## Roles

Roles are layered. **Daybookstaff** is site-wide; the rest are **per-project membership roles**.

| Role | Scope | Summary |
|------|-------|---------|
| **Daybookstaff** | Site-wide | Seeded bootstrap account. Non-deletable. Full cross-project access; only role that can delete any project or edit global statuses. |
| **Admin** | Per project | Project owner capabilities. Manages members, metadata, and all tasks on projects they own. Can create projects. |
| **Manager** | Per project | Broad task editing and assignment within a project once they have visibility (see matrix). Cannot manage members or project metadata. |
| **Contributor** | Per project | Edits assigned items and status; can reorder. Cannot create items or assign tasks. |
| **Viewer** | Per project | Read-only access to tasks they can see; can reorder (sort). |

### Ownership (in addition to role)

Some rules reference **ownership**, not just membership role:

- **Project owner** — the user who created the project (`projects.owner_user_id`).
- **Item owner** — the user who created the item (`items.created_by_user_id`).

A user can be **Admin** on a project they did not create (invited as Admin). Ownership and role are separate checks where the matrix distinguishes them.

---

## Bootstrap (user #1)

- Seed a **Daybookstaff** user via migration/script (email + password hash in config or migration seed data).
- This account is **non-deletable** and is used for all downstream activities (globals, initial project ownership, supporting other users off-platform).
- Existing data (e.g. the **General** project) should be assigned to this user as **project owner** during migration.

---

## Auth

| Topic | Decision |
|-------|----------|
| Login | **Email + password** per user. Retire the shared `$authPasswordHash` login. |
| Registration | **Invite-only.** No open registration. Invited users follow a token link to create their account. |
| Invitations | Daybookstaff and project Admins **copy an invite link** and send it themselves (Slack, email, etc.). No outbound email from the app for invites. |
| Password reset (forgot password) | **Out of scope for v1.** Daybookstaff and project Admins handle resets off-platform. |
| Change password (logged in) | Users can change their own password from account settings (no email required). |
| Change email | Users can update their own email from account settings. |

---

## Task assignment

- **One assignee per item** (nullable — items may be unassigned).
- Assignee shown as a new column in the grid.
- **Daybookstaff, Admin, and Manager** can assign or reassign tasks (within projects where they have that permission).

---

## Permission matrix

**Daybookstaff** column covers site-wide actions. Other columns apply **within a project** where the user is a member at that role.

Unless a row says otherwise, checks may also depend on **project ownership** or **item ownership** (see column labels).

| Action | Daybookstaff | Admin | Manager | Contributor | Viewer |
|--------|:------------:|:-----:|:-------:|:-----------:|:------:|
| **Projects** | | | | | |
| Create project | yes | yes | no | no | no |
| Delete project (any) | yes | no | no | no | no |
| Delete project (owned by them) | yes | yes | no | no | no |
| Edit project name / colors | yes | yes | no | no | no |
| View project (in project switcher) | yes | yes | yes | yes | yes |
| View tasks in a project (any) | yes | no | no | no | no |
| View tasks in a project (they created) | yes | yes | no | no | no |
| View tasks in a project (assigned ≥1 task) | yes | yes | yes | yes | yes |
| Export a list of tasks for a project | yes | yes | yes | yes | yes |
| **Members** | | | | | |
| Invite new users (copy invite link) | yes | yes | no | no | no |
| Revoke pending invite | yes | yes | no | no | no |
| Change member role | yes | yes | no | no | no |
| Remove member from project | yes | yes | no | no | no |
| Leave project voluntarily | yes | yes | no | no | no |
| Transfer project ownership | yes | yes | no | no | no |
| **Tasks (items)** | | | | | |
| Create new item | yes | yes | yes | no | no |
| Delete item (they own) | yes | yes | yes | no | no |
| Delete item (on project they own) | yes | yes | no | no | no |
| Edit any item (all fields) | yes | yes | yes | no | no |
| Edit assigned item only | yes | yes | yes | yes | no |
| Edit unassigned item | yes | yes | yes | no | no |
| Change item status (any item) | yes | yes | yes | yes | no |
| Assign / reassign task to user | yes | yes | yes | no | no |
| Move item to another project (they own) | yes | yes | no | no | no |
| Reorder items / drag within priority | yes | yes | yes | yes | no |
| **Docs & notes** | | | | | |
| View docs & notes | yes | yes | yes | yes | yes |
| Add / edit / delete docs & notes (any item) | yes | no | no | no | no |
| Add / edit / delete docs & notes (project they own) | yes | yes | no | no | no |
| Add / edit / delete docs & notes (assigned item) | yes | yes | yes | yes | no |
| **Project metadata** | | | | | |
| Edit categories (project they own) | yes | yes | no | no | no |
| Edit subsystems (project they own) | yes | yes | no | no | no |
| Edit priorities (project they own) | yes | yes | no | no | no |
| **Globals** | | | | | |
| Edit global statuses | yes | no | no | no | no |
| **Account** | | | | | |
| Update own email | yes | yes | yes | yes | yes |
| Change own password (while logged in) | yes | yes | yes | yes | yes |

### Permission notes (for implementation)

**Task visibility** — a user can see a task in a project if **any** applicable row is yes for their situation:

1. Daybookstaff → all tasks in any project.
2. User created the item → visible to Daybookstaff and Admin.
3. User is assignee on ≥1 task in the project → visible to all roles (unlocks the project task list for Manager, Contributor, Viewer).

**Admin + “edit any item”** — Admin has edit-any on projects where they are Admin. Implementation should grant full task visibility within those projects (not only created/assigned), so edit rights remain usable.

**Docs & notes** — use the most specific matching row (assigned item → project owned → any item for Daybookstaff).

**Delete item** — “they own” = item creator; “on project they own” = project owner deleting any item in that project.

**Move item** — target project must also be owned by the user.

**Export** — export tasks the user can see under the visibility rules above.

---

## User stories

- Users log in with **email and password**.
- A seeded **Daybookstaff** user exists and cannot be deleted.
- Daybookstaff and Admins can **create projects**; creator becomes **project owner**.
- Daybookstaff and Admins can **copy an invite link**; invitees create an account and join at the invited role.
- Users see **only projects they belong to** in the project switcher.
- Task visibility follows the matrix (assignment gate for Manager / Contributor / Viewer; broader access for Admin and Daybookstaff).
- Daybookstaff, Admin, and Manager can **create items** and **assign** them to project members.
- Users can **export** a task list for projects they can access.
- Users can **update their email** and **change their password** from account settings.
- Project owners (and Daybookstaff) manage **categories, subsystems, and priorities** on owned projects.
- Only **Daybookstaff** edits **global statuses**.

---

## UI (lightweight)

Keep all new UI simple and minimal.

| Surface | Purpose |
|---------|---------|
| Login page | Email + password (replace password-only login). |
| Accept-invite page | Token link → set password, create account, join project. |
| Account settings | Change email, change password. |
| Project members panel | Invite (copy link), pending invites, roles, remove member, transfer ownership. |
| Project gear / manage | Existing project UI + members entry point (Admin / Daybookstaff). |
| Grid: assignee column | Show and edit assignee (when permitted). |
| Export control | Export visible tasks for current project. |
| Permission-aware controls | Hide or disable actions the current role cannot perform. |

API endpoints must enforce the same rules as the UI — not UI-only checks.

---

## OUT OF SCOPE FOR NOW

- Open registration (sign up without an invite)
- App-sent email for invitations
- App-sent email for password reset / forgot-password flow
- Audit log / activity feed
- “Last edited by” on items
- Per-field permissions (e.g. edit notes but not status)
- Org/team layer above projects
- Two-factor authentication (2FA)
- Remember-me / long-lived sessions
- Multiple assignees per item
- Request-access flow (access is always invite-driven)
- Super-admin console beyond Daybookstaff + project Admin roles
