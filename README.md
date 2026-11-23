# Online Voting System

## Project overview

Online Voting System is a PHP and MySQL web application for running a scheduled
election. Administrators set up the ballot, manage accounts, and monitor turnout.
Voters sign in with a Voter ID, cast one vote per position, and view a receipt
for their selections. The project includes an SQL setup file with sample data
for a local XAMPP demonstration.

## Technologies

- PHP 8 with PDO, `pdo_mysql`, `mbstring`, and session support
- MySQL or MariaDB with InnoDB and `utf8mb4`
- Apache and phpMyAdmin through XAMPP for local setup
- HTML, CSS, JavaScript, Bootstrap 5.3, and Bootstrap Icons. The browser loads
  Bootstrap and its icons from jsDelivr.

## Features

- **Accounts and sign-in.** Voters can register with a unique Voter ID and
  email address. Administrators can create and remove voter or administrator
  accounts and filter the user list by role and voting activity.
- **Positions and candidates.** Administrators can add, edit, and delete
  positions and candidates. Candidate profiles include an affiliation, an
  optional platform statement, and an optional JPG, PNG, or WebP photo up to 2 MB.
- **Election scheduling.** Administrators can set the election title and voting
  window from **Schedule**. **Start Voting** opens voting for 48 hours from the
  time of the action, and **Close Voting** ends the window immediately.
- **Ballot and receipt.** Voters can select candidates for some or all positions
  and return for the remaining positions while voting is open. Submitted
  selections and their timestamps appear under **My Votes**.
- **Turnout and results.** The administrator dashboard displays registration,
  turnout, and recent voting activity. The results page shows vote counts and
  vote shares by position. Administrators can view it at any time, and signed-in
  voters can view it after the election ends.

## Local setup with XAMPP

1. Place this project in `C:\xampp\htdocs\online-voting-system`. If you keep
   the existing `Online Voting System` folder name, use the matching URL in
   step 5.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin/`. From the main **Import** tab, choose
   [`database_setup.sql`](database_setup.sql) and run the import. The script
   creates and selects `online_voting_db`, then loads the tables and sample data.
4. Check [`db_connect.php`](db_connect.php) if your local database settings
   differ from the included defaults. Update `$db_host`, `$db_name`, `$db_user`,
   `$db_pass`, and `$db_port` as needed. The defaults are `localhost`,
   `online_voting_db`, `root`, an empty password, and port `3306`.
5. Open `http://localhost/online-voting-system/`. For the folder named
   `Online Voting System`, open `http://localhost/Online%20Voting%20System/`.
   Use `http://localhost:PORT/` in the setup URLs if Apache uses a different port.
6. Sign in with a demo ID below or choose **Register as Voter** to create a
   local voter account. If the ballot is closed, sign in as the administrator
   and adjust **Schedule** before testing submissions. Schedule inputs use the
   PHP server's configured time zone.

### Local demo accounts

These accounts are created by the sample SQL import. Enter the ID in the
**Voter ID / Admin ID** field. Use these credentials for local demos only.

| Role | Sign-in ID | Password |
| --- | --- | --- |
| Administrator | `ADMIN-001` | `admin123` |
| Voter | `2026-0001`, `2026-0002`, or `2026-0003` | `voter123` |

## Roles and permissions

- **Administrator** manages positions, candidates, user accounts, and the
  election schedule. The administrator can see turnout, the current tally, and
  the voter and candidate associated with recent votes.
- **Voter** reviews candidates, votes during the scheduled window, and views
  their own receipt. The **Final Results** link becomes available after the
  window closes. Self-registration creates a `voter` account.

The sign-in page verifies password hashes and regenerates the session ID after
successful authentication. The vote handler checks the session, form token,
voting window, and candidate's position before saving a submission in a database
transaction. The database enforces one vote per voter per position.

## Testing each role locally

Use a fresh local demo database for this walkthrough.

1. Sign in as `ADMIN-001`. Open **Positions**, **Candidates**, and **Users** to
   confirm the sample records are present. Open **Schedule** and select
   **Start Voting**.
2. Sign in as a demo voter in a separate browser profile. Open **Official
   Ballot**, select a candidate for one position, choose **Submit Official
   Ballot**, and confirm the submission.
3. Check **My Votes** for the recorded candidate and timestamp. Return to the
   ballot and confirm the recorded position is locked and the remaining
   positions are available. Confirm the voter menu has no results link yet.
4. In the administrator session, check **Dashboard** and **Results** for the
   new vote, then choose **Close Voting** under **Schedule**.
5. Refresh the voter session. Confirm that ballot submission is closed and
   **Final Results** shows the tally. Sign out and check that opening a
   protected page returns you to sign-in.

## Database setup and backup

See the [database diagram with Tagalog explanations](DATABASE_DIAGRAM.md) for
the five tables, their relationships, and an example vote.

Import the setup file into a fresh database for a demo. Reimporting it into an
existing database can add duplicate sample candidates. To back up an existing
installation, export the database structure and data through phpMyAdmin and save
a copy of `uploads/` so candidate photos can be restored with their records.

## Deployment preparation

1. Create a database and database user through your hosting provider. If the
   provider assigns the database name, select that database in its import tool
   and remove the `CREATE DATABASE` and `USE` statements from a copy of the
   setup SQL before importing it.
2. Set the hosting database values in the server's copy of `db_connect.php`
   and set `$is_production = true` to hide PHP error details from visitors.
   Keep the hosting credentials out of GitHub.
3. During private setup, create a unique administrator from **Users**, sign in
   as that administrator, and remove the seeded accounts before making the
   site accessible. Use HTTPS and confirm that sign-in and role redirects work
   at the installed URL.
4. Keep SQL setup files and database backups outside the public web root. Give
   PHP write access to `uploads/` for candidate photos and verify that the host
   honors the supplied Apache rules that restrict script execution there.

## Known limitations

- The application stores one election schedule and tally. Changing the
  schedule keeps the existing votes and does not create a new election.
- Votes are linked to voter accounts. Administrators can see voter names and
  selections in recent activity, so the system does not implement a secret
  ballot. Voter registration accepts self-entered details without checking an
  eligibility roster.
- The project has no automated test suite. Shared-host deployment and the full
  browser-based voting workflow were outside the verification below.

## Local verification

- All 21 PHP files passed `php -l` using XAMPP PHP 8.1.25.
- The local PHP CLI reported `PDO`, `pdo_mysql`, `mbstring`, and `session`.
- The four documented demo logins were checked with `password_verify()`
  against their hashes in `database_setup.sql`.

## Project structure

| Path | Contents |
| --- | --- |
| `index.php`, `login.php`, `register.php`, `logout.php` | Entry point and account routes |
| `admin/` | Dashboard, position and candidate management, users, schedule, and results |
| `voter/` | Ballot, vote submission handler, and voting receipt |
| `includes/` | Shared layout, access checks, and helpers |
| `assets/css/` | Application styles |
| `uploads/` | Candidate photos and Apache upload directory rules |
| `db_connect.php`, `database_setup.sql` | Database connection and demo schema with sample data |

## Publishing the source on GitHub

Keep this `README.md` and the demo setup SQL at the repository root. Before
pushing, review `db_connect.php`, SQL exports, and `uploads/` so the repository
contains only local defaults and sample data you have permission to publish.
