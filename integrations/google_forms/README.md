# Google Forms → AssetVault Manage Concerns

This integration keeps Google Forms as the student/teacher reporting interface and imports each submitted response into the existing MySQL `concerns` table. The reports then appear in **Manage Concerns** with a **Google Form** source badge.

## Flow

Google Form → linked Google Sheets response spreadsheet → Apps Script form-submit trigger → `google_form_webhook.php` → MySQL `concerns` → `admin_concerns.php`.

## 1. Apply the database migration

Back up `capstone_system` first. In phpMyAdmin, select that database and import:

`database/migrations/2026_11_11_google_forms_concerns.sql`

The migration adds:
- `reporter_name`: the name supplied by the respondent;
- `source`: distinguishes internal reports from Google Form reports;
- `external_submission_id`: prevents duplicate imports.

The migration also inserts a placeholder setting. Replace it with a long random secret in the `app_settings` table:

```sql
UPDATE app_settings
SET v = 'PASTE_A_LONG_RANDOM_SECRET_HERE'
WHERE k = 'google_form_webhook_secret';
```

Use the same secret in Apps Script. Do not commit the real secret to GitHub.

## 2. Deploy the PHP endpoint

Upload `google_form_webhook.php` to the same directory as `config.php` on a server reachable from Google over HTTPS.

The endpoint URL will look like:

`https://YOUR-DOMAIN/YOUR-ASSETVAULT-PATH/google_form_webhook.php`

A Google Apps Script running in Google's cloud cannot reach a development-only `localhost` URL. For local XAMPP development, use a secure HTTPS tunnel or deploy the PHP application to an HTTPS-accessible test server. Do not expose phpMyAdmin or the MySQL port.

## 3. Configure Apps Script

1. Open the Google Form's linked response spreadsheet.
2. Open **Extensions → Apps Script**.
3. Replace the starter code with `integrations/google_forms/Code.gs`.
4. In **Project Settings → Script Properties**, add:
   - `ASSETVAULT_WEBHOOK_URL` = the public HTTPS endpoint URL;
   - `ASSETVAULT_WEBHOOK_SECRET` = the exact secret stored in `app_settings`.
5. Confirm the question titles in `Code.gs` match your form. The code currently recognizes Asset Tag, Asset Name, Location, Problem / Concern, and Reporter Name (plus common alternatives).
6. Run `installTrigger()` once and approve the requested Google permissions.

After this, every new form submission is sent to AssetVault automatically. Check the Apps Script **Executions** page if an import fails.

## 4. Verify

Submit a test concern using the Google Form. Then check:
- the response appears in the linked Google Sheet;
- Apps Script shows a successful `onFormSubmit` execution;
- the report appears in AssetVault → **Manage Concerns** with status **Open** and source **Google Form**.

The endpoint matches the submitted Asset Tag against `items.asset_code`. If no matching asset is found, the report is still imported with no linked asset so IT Staff can review it.

## Notes

- Existing internal concern submissions remain unchanged.
- The webhook uses an authorization secret and a unique external response ID for duplicate protection.
- Reports imported from Google Forms use the system's dedicated `reports@spist.edu.ph` reporting account for the required `concerns.user_id` relationship; the actual respondent name is stored separately in `concerns.reporter_name`.
- This code does not remove or modify the PWA files.
