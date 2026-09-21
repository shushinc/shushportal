# Customer Management — setup guide

This module handles the parts Drupal can't do out of the box: generating the
Client ID + Hashed Key, hiding the key until "Show" is clicked, and emailing
the contact with the integration snippet. The content type, fields, and the
management/search page are built with core Drupal (Content types + Views) —
that's the more reliable way to do it and easiest for you to tweak later,
so no config import is required.

## 1. Install the module

Copy the `customer_management` folder into `modules/custom/`, then:

```
drush en customer_management -y
```

## 2. Create the vocabulary for Demand Partners (if it doesn't already exist)

Structure > Taxonomy > Add vocabulary → **Demand Partners** (machine name
`demand_partners`). Add a term for each existing partner (Twilio, Infobip,
etc.).

If "demand partners" already exists elsewhere in your site as a content
type or another vocabulary, just point the field below at that instead.

## 3. Create the "Customer" content type

Structure > Content types > Add content type:

- Name: **Customer**, machine name: `customer`
- Uncheck "Promoted to front page"
- Title field label: **Customer Name**

Add these fields:

| Field | Machine name | Type | Settings |
|---|---|---|---|
| Contact Name | `field_contact_name` | Text (plain) | Required |
| Contact Email | `field_contact_email` | Email | Required |
| Contact Phone | `field_contact_phone` | Telephone | Optional |
| Demand Partners | `field_demand_partners` | Entity reference → Taxonomy term (`demand_partners`) | Required, **Number of values: Unlimited**, widget: "Check boxes/radio buttons" or "Select list" (multi-select) |
| Client ID | `field_client_id` | Text (plain) | Required, hide from the add form (module disables/hides it — see below) |
| Hashed Key | `field_hashed_key` | Text (plain) | Required, same as above |

For `field_client_id` and `field_hashed_key`, on **Manage form display**
set both to a plain "Text field" widget — the module takes care of
disabling/masking them, you don't need special widgets configured.

On **Manage display**, you can safely leave Hashed Key out of the default
"full" view mode entirely (the edit-form toggle is what matters); if you do
show it anywhere, format it as **Hidden** so it never renders in page HTML.

## 4. Set permissions

People > Permissions → grant **"View customer hashed key"** only to the
roles that should ever see the plaintext key (e.g. Administrator). This
also controls access to the reveal-key endpoint and to the field on the
edit form.

## 5. Build the Customer Management page (View)

Structure > Views > Add view:

- View name: **Customer Management**
- Show: **Content** of type **Customer**, sorted by "Authored on (desc)" or
  "Title (asc)" — your call
- Create a page, path: `/customers`
- Display format: **Table**
- Fields: Title (Customer Name), Contact Name, Contact Email, Contact
  Phone, Demand Partners, and an **"Edit link"** field (rewrite the text to
  "Edit" if you like) — do **not** add Hashed Key as a field
- Filter criteria: add **Content: Published** = Yes
- Add **exposed filters** for search: Title (contains) and Contact Email
  (contains) are the two people usually search by; add Contact Name too if
  useful
- Access: restrict to a permission/role appropriate for your customer-admin
  team

That page is your customer management/search screen. The "Edit" link on
each row goes to `/node/{id}/edit`, which is your add/edit page (same form
Drupal already gives you for the "Add content > Customer" action) —
Contact Name, Contact Email, Contact Phone, Demand Partners, plus the
read-only Client ID and masked/"Show" Hashed Key.

## 6. Configure the site email (if not already set)

The notification email uses Drupal's normal mail system
(`plugin.manager.mail`), so it goes out through whatever transport you
already have configured (PHP mail, SMTP module, etc.) — no extra setup
needed beyond that.

## What happens on submit

1. Editor fills in Customer Name, Contact Name, Contact Email, Contact
   Phone (optional), Demand Partners, and saves.
2. `customer_management_node_presave()` generates a `field_client_id`
   (e.g. `cli_9f8a2b1c4d3e7a1f`) and a `field_hashed_key` (64-char hex
   secret) — only on creation, so re-saving an existing customer never
   regenerates them.
3. `customer_management_node_insert()` emails the contact with the Client
   ID, Hashed Key, and a code snippet (Node.js / Python / PHP) showing how
   to HMAC-SHA256 the phone number using the Hashed Key before sending it
   in a header (e.g. `X-Hashed-Phone`) to the demand partner. It also logs
   a **"Customer created"** history entry.
4. On the edit form, the Hashed Key field renders masked; clicking "Show"
   calls `/customer/{id}/reveal-key` (permission-gated) to fetch the
   plaintext value via AJAX, so it's never sitting in the page's HTML
   source by default.
5. Editing and saving Customer Name, Contact Name, Contact Email, Contact
   Phone, or Demand Partners logs a **"Customer updated"** history entry
   naming which fields changed.

## Resetting the hashed key

Beside "Show" is a **Reset** link (only visible to users with the new
**"Reset customer hashed key"** permission — grant it separately from
"View customer hashed key" since it's a destructive action). It goes to
`/customer/{id}/reset-key`, a standard Drupal confirmation page:

- Confirming generates a brand-new Hashed Key, saves it to the customer
  record, and emails the contact the new Client ID + Hashed Key +
  integration snippet (the old key stops working immediately).
- Logs a **"Hashed key reset"** history entry noting which user did it.
- Redirects back to the edit form with a status message.

## Activity history

Every customer's canonical page (`/node/{id}`) gets a collapsed
**History** details element (visible to anyone who can edit that
customer) listing "Customer created", "Customer updated", and "Hashed
key reset" entries, newest first, with date, action, and a short detail
line (e.g. which fields changed, or who reset the key). This comes from
a small custom table (`customer_management_log`, created by
`customer_management.install`) rather than a full entity type — enough
for an audit trail without the overhead of defining a new content
entity. If you'd rather see it on the edit page too, or want it as a
sortable/filterable View instead of a fixed table, that's a quick
follow-up.

## Notes / things worth deciding

- **Client ID format** — currently `cli_` + 16 hex chars. Change this in
  `CredentialGenerator::generateClientId()` if you want something else
  (e.g. matching an existing ID scheme).
- **Rotating the key** — there's currently no "regenerate" button; if a
  key is ever compromised you'd need a small follow-up action to
  regenerate it and re-notify the contact. Say the word if you want that
  added.
- **E.164 normalization** — the snippet assumes the phone number is
  normalized to E.164 (e.g. `+15551234567`) before hashing on the demand
  partner's side; worth calling out to whoever implements against this.
