# Hotel Booking Plugin (`svago`)

A modern, lightweight hotel booking plugin for WordPress integrating with WooCommerce, Elementor, and Online Travel Agency (OTA) Channel Managers.

Designed with an **abstraction-first architecture**, the plugin functions seamlessly offline using realistic mock fallback datasets, allowing full end-to-end booking flow development and testing before live OTA credentials arrive, and seamless switching to production live APIs with zero frontend changes.

---

## Features

- **OTA Provider Abstraction:** Seamlessly switch between Mock Mode and Live Production OTA API without altering frontend code or widgets.
- **Realistic Mock Engine:** Simulates inventory drops on booking, inventory recovery on cancellation, date-range availability calculations, and occupancy limit enforcement.
- **Production OTA Channel Manager Integration (Phase 8):** Full `Real_Provider` implementation with authenticated HTTP client (HMAC-SHA256, API Key, Bearer Token), RESTful endpoint mapping, payload transforms, response parsing, error mapping, and sandbox simulation mode.
- **Resilience & Edge-Case Protection (Phase 7):**
  - **Outage Fallback Notice:** Displays `"Live availability temporarily unavailable — showing cached rates"` when live API is unreachable.
  - **Checkout Inventory Lock:** Pre-payment re-check prevents double-booking if a room was booked during checkout.
  - **Webhook Idempotency:** SHA-256 HMAC signature verification and strict deduplication check (`_booking_ota_pushed === 'yes'`) prevent duplicate order processing.
  - **Empty Fallback Protection:** Surfaces a prominent `"Call to Book"` card with direct phone link when room catalog or inventory is empty.
  - **Admin Dashboard Health Widget:** Real-time operational dot indicator, latency, and last sync timestamp.
  - **Log Auto-Purge:** Automatic log rotation capping records at the 500 newest entries.
- **Frontend Shortcodes:**
  - `[hotel_search]`: Interactive search bar with check-in/out date pickers, adult/child selectors, dynamic child age dropdowns, and promo code support.
  - `[hotel_rooms]`: Live room catalog with photos, room specs (size, bed, occupancy), amenity chips, rate plan pricing, low-stock badges, and sold-out states.
  - `[hotel_booking]`: Unified 4-step booking funnel (`?step=search`, `?step=details`, `?step=review`, `?step=confirmation`).
  - `[hotel_guest_details]`, `[hotel_review]`, `[hotel_confirmation]`: Dedicated standalone views for custom page builders.
- **Razorpay Payment Integration:** Seamless modal checkout, HMAC payment verification, and automated order confirmation.
- **Twilio WhatsApp & Transactional Emails:** Automated templates for booking confirmation, payment failure recovery, pre-arrival reminder (1 day before check-in), and cancellations with strict opt-in consent gating.
- **Admin Management Suite (Hotel Booking menu):**
  - **Dashboard:** Central overview showing OTA operational status, response latency, last sync timestamp, mode badge, and quick stats.
  - **Settings:** API credentials, Razorpay gateway keys, Twilio credentials, and cancellation policy controls.
  - **Availability:** 14-day interactive grid and bulk updater pushing directly to Channel Manager.
  - **Onsite Booking:** Front-desk interface for walk-in and phone reservations with instant pricing and cash/UPI/card logging.
  - **Bookings:** Comprehensive reservations table with stay dates, guest contacts, financials, and OTA push status.
  - **Room Mapping:** Overview linking WooCommerce products to OTA room IDs and rate plans.
  - **Logs:** Activity audit trail, staff overrides, error traces, and log rotation / auto-purge.
- **Themed Rooms & Auto-Seeder:**
  - Includes the 4 themed room types: **Jazz Room**, **Literature Room**, **Love Room**, and **Theatre Room**.
  - Automatically seeds WooCommerce with simple room products complete with pricing, amenities, room sizes, bed types, and OTA metadata upon plugin activation.

---

## System Requirements

- **PHP:** 8.1 or higher (Tested and compatible up to PHP 8.5)
- **WordPress:** 6.0 or higher
- **WooCommerce:** 7.0 or higher (recommended for products and payment processing)
- **Web Server:** Apache or Nginx with URL rewriting enabled

---

## Installation & Setup

1. **Upload / Place Plugin Directory:**  
   Ensure the plugin folder is located at:
   ```
   wp-content/plugins/svago/
   ```

2. **Activate the Plugin:**  
   - Navigate to **Plugins -> Installed Plugins** in the WordPress admin area.
   - Locate **Hotel Booking (svago)** and click **Activate**.
   - Upon activation, the plugin automatically creates the `wp_svago_logs` database table, registers default settings, schedules background cron tasks, and flushes rewrite rules.

3. **Verify Admin Menus:**  
   A new top-level menu item titled **Hotel Booking** (building icon) will appear on the admin sidebar:
   - **Hotel Booking -> Settings:** Main configuration screen.
   - **Hotel Booking -> Availability:** Date-by-date inventory & rate overrides.
   - **Hotel Booking -> New Onsite Booking:** Front-desk walk-in & phone reservation screen.
   - **Hotel Booking -> Room Mapping:** WooCommerce product <-> OTA catalog mapping.
   - **Hotel Booking -> Logs:** Activity and error log viewer.

---

## Frontend Shortcodes & Elementor Usage

You can embed the booking system into any WordPress page, post, or Elementor Shortcode widget:

### 1. `[hotel_search]`
Displays the hotel availability search bar.

```
[hotel_search]
```

**Attributes:**
- `action_url`: (Optional) URL of the results page to redirect to when searching from an external page (e.g. `action_url="/rooms/"`).
- `check_in`: (Optional) Default check-in date in `YYYY-MM-DD` format.
- `check_out`: (Optional) Default check-out date in `YYYY-MM-DD` format.
- `adults`: (Optional) Default number of adults (default: `2`).
- `children`: (Optional) Default number of children (default: `0`).

### 2. `[hotel_rooms]`
Renders room cards with live availability, rate plan selector, and pricing.

```
[hotel_rooms]
```

**Features included:**
- **Best Rate Guarantee Banner:** Direct booking callout banner.
- **Stay Summary Bar:** Displays selected dates, number of nights, guest breakdown, and a link to modify the search.
- **Room Cards:** Photo galleries with thumbnail switchers, room size, bed configuration, occupancy limits, and amenity badges.
- **Rate Plans Selector:** Room Only, With Breakfast, and Half/Full Board with nightly and total stay pricing.
- **Urgent Low-Stock Badge:** Displays `🔥 Only X left!` when inventory is $\le 2$.
- **Sold-Out Handling:** Clean `🚫 Sold Out` badge and friendly guidance when no inventory remains for selected dates.
- **Fallback Notice:** If the external API fails, cached rates are displayed with a notice: `"Live availability temporarily unavailable — showing cached rates."`.
- **Call to Book Card:** If no rooms are available or fallback is missing, displays a direct phone card: `"Call Reservations: +91 98765 43210"`.

### 3. `[hotel_booking]` (All-in-One Booking Funnel)
Embeds the complete, seamless 4-step booking funnel on a single page:

```
[hotel_booking]
```

Dynamically detects the current query parameter `?step=` and renders:
- `step=search` / Default: Search bar + available room cards.
- `step=details`: Guest details form with stay summary sidebar.
- `step=review`: Reservation summary, cancellation policies, and gated payment button.
- `step=confirmation`: Verified booking receipt, download invoice button, and hotel map directions.

### 4. `[hotel_guest_details]`, `[hotel_review]` & `[hotel_confirmation]`
Dedicated shortcodes for multi-page architectures:
- `[hotel_guest_details]`: Lead guest contact inputs (First/Last name, Email, Country code mobile picker, ETA dropdown, WhatsApp/Email consent checkbox).
- `[hotel_review]`: Complete stay recap, pricing with taxes included, cancellation policies card, and mandatory agreement checkbox `#svago-policy-checkbox` gating the `#svago-proceed-payment-btn` button.
- `[hotel_confirmation]`: Standalone confirmation receipt screen featuring Booking Reference ID (`#SV-{id}`), OTA confirmation tag, room and guest recap, hotel address, Google Maps link, and a print/download receipt action button.

---

## WordPress Admin Panel Structure

The admin panel is organized under a top-level **Hotel Booking** menu in the exact operational sequence:

```
Hotel Booking
├── Dashboard         # Overview KPIs, OTA health status, quick links & recent logs
├── Availability      # Calendar grid with date-by-date inventory overrides & rates
├── Onsite Booking    # Front-desk terminal for walk-in and phone reservations
├── Bookings          # Filterable table of all online & walk-in reservations
├── Room Mapping      # OTA Room ID mapping, live rate breakdown & "Auto-Map & Sync"
├── Shortcodes        # Quick reference guide & click-to-copy table of all shortcodes
├── Logs              # Real-time activity log viewer with level filtering & auto-purge
└── Settings          # Provider mode (Mock/Live), credentials, Razorpay & Twilio
```

---

## Room Mapping & "Auto-Map & Sync" Workflow

The **Room Mapping** screen (`Hotel Booking -> Room Mapping`) bridges native WooCommerce products with OTA Room Identifiers:

1. **⚡ Auto-Map & Sync Button:**
   - Automatically fetches available rooms and details from the active OTA API (or mock catalog).
   - Creates corresponding WooCommerce simple products with full descriptions, amenities, room sizes, and bed types under the "Hotel Rooms" product category.
   - Maps the WooCommerce products to OTA Room IDs using both `_ota_room_id` and `_svago_ota_room_id`.
   - Synchronizes initial availability (30-day inventory) and base pricing.
2. **Normal Product Editing Preserved:**
   - After auto-sync, administrators can freely edit WooCommerce products normally (e.g. adjust regular prices, add sale prices, modify marketing descriptions, upload custom image galleries) via the direct **✏️ Edit Product** button without breaking the OTA mapping.
   - Future auto-sync runs respect and preserve manual price adjustments made by the admin.
3. **Manual Mapping & Unlinking:**
   - Link existing products or unlink products with a single click via the **🔗 Unlink** button.

---

## Admin Configuration (`Hotel Booking -> Settings`)

- **Provider Mode:**
  - `Mock (Offline / Development)`: Default setting. Uses internal JSON fallback datasets stored in `data/fallback/`. No external network calls are made.
  - `Live (Production OTA API)`: Communicates with the live OTA Channel Manager using `Real_Provider`.
- **OTA API Credentials:** (Automatically active when Live mode is selected)
  - **OTA API URL:** Base endpoint (e.g., `https://api.ota-provider.com/v1` or `https://sandbox.channelmanager.com/v1`).
  - **OTA API Key:** Channel Manager API key.
  - **OTA API Secret:** Channel Manager API secret (used for Bearer token and HMAC-SHA256 signatures).
- **Payment & Razorpay Settings:**
  - **Razorpay Key ID:** Test or Live key ID (e.g., `rzp_test_...`).
  - **Razorpay Key Secret:** Secret key for transaction signature verification.
  - **Razorpay Webhook Secret:** Secret for verifying `X-Razorpay-Signature` webhook callbacks.
- **Notification Settings:**
  - **Enable WhatsApp:** Toggle Twilio WhatsApp booking updates.
  - **Twilio Account SID, Auth Token & WhatsApp Number:** Credentials for WhatsApp delivery.
  - **Enable Email:** Toggle WooCommerce / WP transactional emails.
- **Hotel Info & Call-to-Book:**
  - **Hotel Phone:** Displayed to guests if both live API and fallback data are unavailable ("Call to book").
  - **Hotel Reservations Email:** Inbox receiving instant bcc copies of all confirmed bookings.
  - **Hotel Physical Address:** Rendered on confirmation receipts and invoices.
  - **Google Maps URL:** Direct navigation directions link for guests.
- **Provider Health Check:**
  - Click the **Check Provider Health** button to instantly test provider connectivity and latency.

---

## Shortcodes Quick Reference Guide (`Hotel Booking -> Shortcodes`)

Navigate to **Hotel Booking -> Shortcodes** for an interactive, click-to-copy reference table:

| Label | Shortcode | Description | Recommended Placement |
|:---|:---|:---|:---|
| **Complete Booking Flow** | `[hotel_booking]` | All-in-one 5-step interactive booking flow (Search, Rooms, Details, Review, Confirmation). | Dedicated `/booking` page |
| **Room Search Widget** | `[hotel_search]` | Compact search widget with check-in, check-out dates, adult/child counters, and promo code. Optional attributes: `action_url`, `check_in`, `check_out`, `adults`, `children`, `promo_code`. | Homepage hero, landing pages |
| **Room Results & Selection** | `[hotel_rooms]` | Displays available room cards with rates, amenities, low-stock badges, and rate plan options. | Catalog / Step 2 page |
| **Guest Details Form** | `[hotel_guest_details]` | Standalone form collecting primary guest contact details and special requests. | Step 3 checkout page |
| **Booking Review & Pricing** | `[hotel_review]` | Pricing summary with taxes, policy agreement checkbox, and Razorpay payment button. | Step 4 review page |
| **Booking Confirmation** | `[hotel_confirmation]` | Receipt screen displaying reservation reference, stay summary, and map directions. | Step 5 confirmation page |

---

## Edge Cases, Fallbacks & Resilience (Phase 7)

The plugin implements comprehensive defenses against external service disruptions:

1. **API Outage Degradation:** If the OTA API experiences a connection failure or timeout, `Provider_Factory::execute_with_fallback()` automatically catches the exception, logs error traces to `Logger`, loads cached fallback JSON, and surfaces a polite notice: `"Live availability temporarily unavailable — showing cached rates."`
2. **Room Unavailable During Checkout:** Pre-payment inventory check in `Order_Manager::ajax_create_order()` re-validates availability right before opening Razorpay checkout. If another guest booked the room in the interim, it returns a polite notification: *"This room is no longer available for your dates. Please try other dates or rooms."* (No raw SQL or unhandled exceptions).
3. **Razorpay Webhook Idempotency:** The webhook endpoint verifies HMAC signatures using `svago_razorpay_webhook_secret`. If duplicate callbacks arrive for an already confirmed order (`_booking_ota_pushed === 'yes'`), the webhook returns HTTP 200 with `status: 'already_processed'` without reprocessing payments or dispatching duplicate OTA pushes.
4. **WhatsApp/Email Failure Recovery:** If Twilio times out, the error is recorded in `_svago_whatsapp_status = 'failed'` and `_svago_whatsapp_error`. Reception staff can trigger a manual resend via the **Hotel WhatsApp Notifications** metabox on the order screen or via WooCommerce Order Actions (`svago_resend_confirmation`).
5. **Admin Dashboard Health Widget:** Registered on `wp_dashboard_setup`, displaying live OTA status (green/red dot), latency in ms, last sync timestamp, and quick navigation links.
6. **Log Rotation:** `Logger::purge_old(500)` strictly caps the activity log table at 500 entries, deleting older records during database operations.

---

## Production OTA Channel Manager Architecture (Phase 8)

The `Real_Provider` class implements a production-ready client for external Channel Managers:

- **Authenticated Client (Task 8.1):** Requests include `X-API-Key`, `Authorization: Bearer <secret>`, `X-OTA-Timestamp`, and `X-OTA-Signature` (HMAC-SHA256 signature calculated across method, endpoint, timestamp, and payload body).
- **Endpoint Mapping (Task 8.2):** Full mapping to RESTful Channel Manager endpoints:
  - `GET /rooms`: Catalog query
  - `POST /availability`: Date-range availability matrix
  - `POST /rooms/{id}/rates`: Room rate plans
  - `POST /bookings`: Booking creation
  - `PATCH /bookings/{id}`: Booking modification
  - `POST /bookings/{id}/cancel`: Cancellation push
  - `PUT /availability`: Availability inventory updates
  - `GET /health`: Connectivity and ping check
- **Payload Transformations (Task 8.3):** Formats internal WooCommerce orders into canonical OTA schemas with standard references (`#SV-{id}`), stay dates, occupancy, lead guest details, and pricing.
- **Response Parsing (Task 8.4):** Standardizes external OTA responses into internal structures with cumulative multi-night availability calculations and tax-inclusive rate flags.
- **Error Code Mapping (Task 8.5):** Maps HTTP status codes (401 Auth Failed, 404 Not Found, 409 Inventory Conflict, 422 Validation Error, 429 Rate Limit, 503 Outage) to friendly localized messages.
- **Sandbox Simulation Mode (Task 8.6):** When pointing to a sandbox URL (`https://sandbox...`), `Real_Provider` provides realistic sandbox simulation responses for end-to-end testing without blocking on live production credentials.
- **Go-Live Switching (Task 8.7):** Toggle between Mock and Live mode in settings. If credentials are incomplete, the plugin safely falls back to Mock mode while logging an admin warning.

---

## Running Verification Tests

Complete unit and integration test suites cover all 8 phases:

```bash
# Phase 1: Foundation & Mock Provider (43 assertions)
php "scratch/test-phase1.php"

# Phase 2: Search & Room Display (31 assertions)
php "scratch/test-phase2.php"

# Phase 3: Booking Flow & Guest Details (47 assertions)
php "scratch/test-phase3.php"

# Phase 4: Payment & Confirmation (56 assertions)
php "scratch/test-phase4.php"

# Phase 5: WhatsApp Notifications & Cron (52 assertions)
php "scratch/test-phase5.php"

# Phase 6: Admin Panel & Onsite Booking (58 assertions)
php "scratch/test-phase6.php"

# Phase 7: Edge Cases, Fallbacks & Polish (40 assertions)
php "scratch/test-phase7.php"

# Phase 8: OTA API Integration & Real Provider (34 assertions)
php "scratch/test-phase8.php"
```

### Master Regression Run (All 8 Suites):
```powershell
$files = 1..8 | ForEach-Object { "scratch/test-phase$_.php" }
foreach ($f in $files) { php $f }
```

**Master Suite Result:**
```
Phase 1: 43 / 43 Passed (100%)
Phase 2: 31 / 31 Passed (100%)
Phase 3: 47 / 47 Passed (100%)
Phase 4: 56 / 56 Passed (100%)
Phase 5: 52 / 52 Passed (100%)
Phase 6: 58 / 58 Passed (100%)
Phase 7: 40 / 40 Passed (100%)
Phase 8: 34 / 34 Passed (100%)
======================================================
MASTER TOTAL: 361 / 361 ASSERTIONS PASSED (100%)
======================================================
```

---

## License

GPL v2 or later.
