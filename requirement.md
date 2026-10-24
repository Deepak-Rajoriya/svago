# Hotel Booking Plugin — Requirements

Version: 1.0
Last Updated: Oct 2026
Stack: WordPress + WooCommerce + Elementor + Custom Plugin
OTA Integration: Two-way (pull availability/rates, push bookings) — API pending
Status: In Scope only (extra features excluded)

---

## 1. Overview

A WordPress site with a simple, clean booking flow for a hotel. Guests search dates, see live availability, select a room + rate, enter guest details, review policy, pay via Razorpay, and get a confirmation. Bookings are pushed to the OTA engine. Admin manages rooms, rates, availability, and onsite bookings from a simple panel.

Design principles:
- Simple, clean, easy to manage
- No complex custom features
- Mock data fallback when OTA API is unavailable
- Flexible OTA provider layer — swap mock to real API without rewriting front-end

---

## 2. Scope

### In Scope

1. ~10 pages via Elementor
2. Custom plugin for OTA API integration
3. Search widget (dates, guests, child ages)
4. Room results with rate plans
5. Guest details form
6. Review & policy acceptance
7. WooCommerce + Razorpay payment
8. Push booking status to OTA API
9. Confirmation page + invoice + calendar
10. Twilio WhatsApp integration
11. Manage booking (admin-side for onsite customers)
12. CM push for modifications + cancellations
13. Admin panel (WooCommerce defaults + basic config)
14. Promo codes (WooCommerce default coupons only)
15. Email SMTP (standard setup only)
16. Analytics + Meta/Google pixels (basic install)
17. Privacy policy + consent (client provides content)
18. Edge case handling (standard error handling)
19. Best-rate message (text banner only)
20. Low-stock badge (display only, if OTA returns count)

### Out of Scope (Excluded)

- 15-min room hold + auto-release (OTA handles)
- Add-ons & cart
- Multi-room booking
- Cancellation policy engine + snapshot (custom rule builder)
- Refund flow via Razorpay API (auto-calculated)
- Razorpay webhook reconciliation job
- Reports + Excel export
- Partial advance payment
- GST invoice fields + slab config
- "Add to calendar" button

---

## 3. Architecture

WordPress Site
  Elementor Pages (~10)
  WooCommerce + Razorpay
  Custom Plugin (OTA Bridge)
        |
  OTA Provider Layer
    Mock Provider  |  Real API Provider
        |
  OTA Channel Manager API

Key Principle: Provider Abstraction
All OTA calls go through a single interface. When the real API arrives, only the RealProvider class is written — front-end and plugin logic stay unchanged.

interface OTA_Provider_Interface {
    public function get_availability( array $params ): array;
    public function get_rates( string $room_id, array $dates ): array;
    public function get_rooms(): array;
    public function push_booking( array $booking ): array;
    public function push_modification( array $booking ): array;
    public function push_cancellation( array $booking ): array;
    public function push_availability( string $room_id, array $dates, int $qty ): array;
    public function check_health(): bool;
}

---

## 4. Data Model

### 4.1 WooCommerce Products = Rooms

Custom meta fields:

| Meta Key | Type | Description |
|----------|------|-------------|
| _ota_room_id | string | OTA engine room ID |
| _ota_rate_ids | json | Array of rate plan IDs + names |
| _room_size | string | e.g., "350 sq ft" |
| _bed_type | string | e.g., "King size Bed" |
| _max_adults | int | Max adults |
| _max_children | int | Max children |
| _amenities | json | Array of amenity labels |
| _room_images | json | Gallery image IDs |

### 4.2 WooCommerce Orders = Bookings

Custom order meta:

| Meta Key | Type | Description |
|----------|------|-------------|
| _booking_check_in | date | Check-in date |
| _booking_check_out | date | Check-out date |
| _booking_adults | int | Adults |
| _booking_children | int | Children |
| _booking_child_ages | json | Child ages |
| _booking_rate_plan | string | Selected rate plan |
| _booking_ota_id | string | OTA booking ID (after push) |
| _booking_status | string | pending, confirmed, cancelled |
| _booking_source | string | website, onsite_admin |
| _booking_consent | bool | WhatsApp/email consent |

### 4.3 Fallback Data (JSON)

Location: wp-content/plugins/hotel-booking/data/fallback/

| File | Purpose |
|------|---------|
| rooms.json | Static room list (mirrors WooCommerce products) |
| availability.json | Static availability map per room per date |
| rates.json | Static rate plans per room |
| config.json | Provider mode, last sync timestamp, error log |

---

## 5. Phased Development Plan

### Phase 1: Foundation & Mock Provider (Week 1-2)

Goal: Plugin skeleton, provider layer, mock data, admin settings.

| Task | Detail |
|------|--------|
| 1.1 Plugin scaffold | Main plugin file, activation hook, folder structure |
| 1.2 Provider interface | OTA_Provider_Interface with all methods |
| 1.3 Mock provider | Reads from data/fallback/*.json, returns structured arrays |
| 1.4 Provider factory | OTA_Provider_Factory::get() — returns mock or real based on setting |
| 1.5 Admin settings page | Toggle: Mock / Live. API credentials fields (disabled until live). Health check button |
| 1.6 Fallback loader | Helper class to load JSON fallback when API fails |
| 1.7 Error logger | Simple log table + admin view for API errors |
| 1.8 WooCommerce room meta | Add custom fields to product edit screen (OTA room ID, rate IDs, amenities, etc.) |

Deliverable: Plugin activates, admin can toggle mock mode, room meta fields visible, mock data loads.

### Phase 2: Search & Room Display (Week 2-3)

Goal: Search widget, room results, availability display — all from mock provider.

| Task | Detail |
|------|--------|
| 2.1 Search shortcode | [hotel_search] — check-in, check-out, rooms, adults, children (with ages), promo field, search button |
| 2.2 Date picker | Blocked dates greyed out (from mock availability) |
| 2.3 Room results shortcode | [hotel_rooms] — calls provider, renders cards |
| 2.4 Room card | Photos, size, bed type, max occupancy, amenities, rate plans with price per night + total, taxes shown |
| 2.5 Low-stock badge | Show "Only X left" if provider returns inventory count |
| 2.6 Sold-out state | Show "Sold out" + "try other dates" |
| 2.7 AJAX endpoints | wp_ajax_get_availability, wp_ajax_get_rates — return JSON for front-end |
| 2.8 Fallback handling | If provider fails — load fallback/availability.json + show subtle notice |

Deliverable: Guest can search, see rooms with rates, blocked dates, sold-out state. All from mock data.

### Phase 3: Booking Flow & Guest Details (Week 3-4)

Goal: Select room — guest form — review — policy acceptance.

| Task | Detail |
|------|--------|
| 3.1 "Book now" flow | Click — store selection in WC session — redirect to guest details page |
| 3.2 Guest details form | Name, mobile (country code), email, special requests, ETA, consent checkbox |
| 3.3 Review page | Summary: dates, room, rate plan, guests, taxes, total + cancellation policy text |
| 3.4 Mandatory checkbox | "I agree to cancellation policy and hotel terms" — pay button disabled until ticked |
| 3.5 Pre-payment availability check | AJAX call to provider — if unavailable, show friendly message: "This room is no longer available for your dates. Please try other dates or rooms." — do NOT show error |
| 3.6 Fallback | If provider fails during check — use fallback JSON — if still fails, show "call to book" message with hotel number |

Deliverable: Guest can go from room selection to review page. Availability re-checked before payment with friendly messaging.

### Phase 4: Payment & Confirmation (Week 4-5)

Goal: WooCommerce + Razorpay payment, order creation, confirmation page.

| Task | Detail |
|------|--------|
| 4.1 WooCommerce order | Create order on review submit with all booking meta |
| 4.2 Razorpay integration | Use official Razorpay WooCommerce plugin — configure test/live keys, webhook URL |
| 4.3 Payment flow | Razorpay checkout opens — on success — order marked paid |
| 4.4 Confirmation page | Booking ID, summary, download invoice (WooCommerce default), hotel address + map link |
| 4.5 Push to OTA | After payment confirmed — call provider->push_booking() |
| 4.6 Retry on push failure | If push fails — log error — retry via WP-Cron (3 attempts, 5 min apart) |
| 4.7 Email | WooCommerce confirmation email + copy to hotel reservations inbox |

Deliverable: Guest pays, order created, confirmation shown, booking pushed to OTA (mock success in Phase 4).

### Phase 5: Notifications (Week 5)

Goal: Twilio WhatsApp integration + email SMTP.

| Task | Detail |
|------|--------|
| 5.1 Twilio setup | Account SID, Auth Token, WhatsApp sender number in admin |
| 5.2 Template mapping | Map WooCommerce order status to Twilio template |
| 5.3 Booking confirmed message | Trigger on order_status_completed or payment captured |
| 5.4 Payment failed message | Trigger on order_status_failed |
| 5.5 Pre-arrival reminder | WP-Cron: 1 day before check-in |
| 5.6 Cancellation message | Trigger on order cancelled |
| 5.7 Consent gating | Only send if _booking_consent = true |
| 5.8 Retry + log | Failed WhatsApp — log — admin can resend from order screen |
| 5.9 SMTP | Configure WP Mail SMTP plugin — standard setup only |

Deliverable: WhatsApp + email sent on booking events. Consent respected.

### Phase 6: Admin Panel & Onsite Booking (Week 6)

Goal: Simple admin panel for room availability, onsite bookings, and basic management.

| Task | Detail |
|------|--------|
| 6.1 Availability manager | Admin edits room quantity/availability per date — save to WooCommerce product meta — push to OTA via provider->push_availability() |
| 6.2 Onsite booking screen | Admin creates booking for walk-in/phone customer: select room, dates, guest details, mark as paid (cash/UPI) — creates WooCommerce order — pushes to OTA |
| 6.3 Booking list | WooCommerce orders list with filters (date, status, source) — default WC + custom columns |
| 6.4 Resend confirmation | Button on order screen — resend WhatsApp + email |
| 6.5 Manual cancel | Admin cancels order — calls provider->push_cancellation() — sends WhatsApp + email |
| 6.6 Room/rate mapping UI | Simple table: WooCommerce product <-> OTA room ID + rate IDs |
| 6.7 Activity log | Simple log: who changed what, when (WP users + actions) |

Deliverable: Admin can manage availability, create onsite bookings, cancel, resend messages, and view logs.

### Phase 7: Edge Cases, Fallbacks & Polish (Week 7)

Goal: Error handling, fallback behavior, edge cases.

| Task | Detail |
|------|--------|
| 7.1 API down handling | Detect provider failure — load fallback JSON — show subtle "Live availability temporarily unavailable — showing cached rates" notice |
| 7.2 Room gone during checkout | Re-check before payment — friendly message (not error) |
| 7.3 Duplicate webhook | Idempotency check on Razorpay webhook — confirm booking only once |
| 7.4 WhatsApp/email fails | Retry + log + admin resend button |
| 7.5 Empty fallback | If fallback JSON also missing — show "call to book" with hotel number |
| 7.6 Health check | Admin dashboard widget: OTA API status (green/red) + last sync time |
| 7.7 Log rotation | Keep last 500 log entries, auto-purge older |

Deliverable: Graceful degradation everywhere. No raw errors shown to guests.

### Phase 8: OTA API Integration (When API Arrives)

Goal: Write RealProvider class, wire it in, test.

| Task | Detail |
|------|--------|
| 8.1 API client | HTTP client with auth (key/secret or OAuth per docs) |
| 8.2 Endpoint mapping | Map interface methods to actual API endpoints |
| 8.3 Payload transformation | Convert WooCommerce order data — OTA booking payload |
| 8.4 Response parsing | Convert OTA response — internal array format |
| 8.5 Error mapping | Map OTA error codes — user-friendly messages |
| 8.6 Sandbox testing | Test all methods against OTA sandbox |
| 8.7 Go-live checklist | Flip provider mode to Live, run end-to-end test, verify OTA inventory drops |

Deliverable: Real OTA integration live. Mock mode still available as fallback.

---

## 6. Provider Interface — Detailed

### 6.1 Methods

| Method | Input | Output | Used In |
|--------|-------|--------|---------|
| get_rooms() | — | Array of room objects | Room results page |
| get_availability($params) | check_in, check_out, adults, children, child_ages | Array of room_id — availability + price + inventory | Search results |
| get_rates($room_id, $dates) | room_id, check_in, check_out | Array of rate plans | Room card |
| push_booking($booking) | Order data (dates, room, guest, payment) | { success, ota_booking_id } | After payment |
| push_modification($booking) | Booking ID + changed fields | { success } | Admin edit |
| push_cancellation($booking) | Booking ID | { success } | Admin cancel |
| push_availability($room_id, $dates, $qty) | Room + dates + qty | { success } | Admin availability edit |
| check_health() | — | { ok, latency, message } | Admin dashboard |

### 6.2 Fallback Logic

try:
    result = provider.method()
    save_to_cache(result)  // optional
    return result
catch:
    log_error()
    fallback = load_json_fallback(method)
    if fallback:
        return fallback with "cached" flag
    else:
        return error — show "call to book" message

---

## 7. Admin Controls

### 7.1 Settings Page (Hotel Booking — Settings)

| Setting | Type | Default |
|---------|------|---------|
| Provider Mode | Radio: Mock / Live | Mock |
| OTA API URL | Text | — |
| OTA API Key | Text | — |
| OTA API Secret | Password | — |
| Hotel Phone (for "call to book") | Text | — |
| Twilio Account SID | Text | — |
| Twilio Auth Token | Password | — |
| Twilio WhatsApp Number | Text | — |
| Enable WhatsApp | Checkbox | Yes |
| Enable Email | Checkbox | Yes |
| Health Check | Button | — |

### 7.2 Availability Manager

- Screen: Hotel Booking — Availability
- Table: Room | Date | Qty | Status
- Inline edit — save — push to OTA (push_availability)
- Bulk update option

### 7.3 Onsite Booking

- Screen: Hotel Booking — Onsite Booking
- Form: Room, Check-in, Check-out, Adults, Children, Guest Name, Phone, Email, Payment Method (Cash/UPI/Card), Amount
- Save — creates WooCommerce order (source = onsite_admin) — pushes to OTA
- Sends WhatsApp + email (if consent)

### 7.4 Booking List

- Default WooCommerce orders list
- Custom columns: Check-in, Check-out, Room, OTA ID, Source
- Filters: Date, Status, Source

### 7.5 Activity Log

- Screen: Hotel Booking — Logs
- Columns: Time, User, Action, Details
- Auto-purge after 500 entries

---

## 8. Fallback Data (JSON)

### 8.1 data/fallback/rooms.json

{
  "rooms": [
    {
      "id": "RM001",
      "name": "Deluxe Room",
      "description": "Spacious room with city view.",
      "size": "350 sq ft",
      "bed_type": "King size Bed",
      "max_adults": 2,
      "max_children": 1,
      "amenities": ["Private Balcony", "King size Bed", "Shower area", "TV Unit", "Walk-In Wardrobe", "Dressing area"],
      "images": ["/wp-content/uploads/rooms/deluxe-1.jpg"]
    }
  ]
}

### 8.2 data/fallback/availability.json

{
  "RM001": {
    "2026-10-01": { "available": true, "price": 94755, "inventory": 2 },
    "2026-10-02": { "available": true, "price": 94755, "inventory": 2 },
    "2026-10-03": { "available": false, "price": 0, "inventory": 0 }
  }
}

### 8.3 data/fallback/rates.json

{
  "RM001": [
    { "rate_id": "RT001", "name": "Room Only", "price_per_night": 70854, "tax_included": true },
    { "rate_id": "RT002", "name": "With Breakfast", "price_per_night": 78000, "tax_included": true }
  ]
}

### 8.4 data/fallback/config.json

{
  "provider_mode": "mock",
  "last_sync": "2026-10-15T10:00:00Z",
  "fallback_active": false
}

---

## 9. User Flows

### 9.1 Guest Booking Flow

Home page
  — Search widget (dates, guests)
  — Room results (cards with rates)
  — Select room + rate
  — Guest details form (name, mobile, email, ETA, consent)
  — Review page (summary, policy, checkbox)
  — [Pre-payment availability re-check]
      — If available: Razorpay checkout
      — If unavailable: friendly message + alternatives
  — Payment success
  — Confirmation page (booking ID, invoice, hotel info)
  — Push to OTA (async, retry on failure)
  — WhatsApp + email sent

### 9.2 Admin Onsite Booking Flow

Admin — Hotel Booking — Onsite Booking
  — Select room + dates
  — Guest details
  — Payment method (cash/UPI/card) + amount
  — Save — WooCommerce order created
  — Push to OTA
  — WhatsApp + email sent (if consent)

### 9.3 Availability Update Flow

Admin — Hotel Booking — Availability
  — Edit room qty for a date
  — Save
  — Push to OTA (push_availability)
  — Log action

---

## 10. Edge Cases

| Situation | Expected Behavior |
|-----------|-------------------|
| OTA API down | Load fallback JSON, show subtle "cached rates" notice |
| Room sold during checkout | Pre-payment re-check — friendly message + alternatives (no error) |
| Fallback JSON missing | Show "call to book" with hotel phone number |
| Payment webhook received twice | Idempotency check — confirm only once |
| WhatsApp/email fails | Retry 3x — log — admin resend button |
| Push to OTA fails | Retry via WP-Cron (3 attempts, 5 min apart) — log — admin alert |
| Guest cancels after payment | Admin cancels — push cancellation to OTA — send WhatsApp + email |
| Provider returns empty availability | Show "sold out" + "try other dates" |

---

## 11. File Structure

wp-content/plugins/hotel-booking/
- hotel-booking.php              # Main plugin file
- includes/
    - class-plugin.php           # Core bootstrap
    - class-provider-factory.php # Returns mock or real provider
    - interface-ota-provider.php # Provider interface
    - class-mock-provider.php    # Mock provider (reads JSON)
    - class-real-provider.php    # Real OTA provider (Phase 8)
    - class-fallback-loader.php  # JSON fallback loader
    - class-availability.php     # Availability manager
    - class-onsite-booking.php   # Onsite booking screen
    - class-logger.php           # Activity + error logger
    - class-settings.php         # Admin settings
    - class-shortcodes.php       # [hotel_search], [hotel_rooms]
- data/
    - fallback/
        - rooms.json
        - availability.json
        - rates.json
        - config.json
- assets/
    - css/
    - js/
- templates/
    - search-widget.php
    - room-results.php
    - guest-details.php
    - review.php
- logs/
    - .htaccess                  # Deny direct access

---

## 12. Token Optimization Notes

- Reusable shortcodes — no page-specific code
- Single provider interface — swap mock to real without touching front-end
- Centralized fallback loader — one place handles all API failures
- WooCommerce defaults — use order screens, email, coupons, tax classes
- Admin settings in one page — no scattered options
- Phased delivery — each phase is independently testable and shippable

---

## 13. Dependencies & Blockers

| Dependency | Status | Impact |
|------------|--------|--------|
| OTA API docs + sandbox | Pending | Blocks Phase 8 only |
| Razorpay live keys | Pending | Needed for Phase 4 go-live |
| Twilio WhatsApp templates approved | Pending (start early) | Needed for Phase 5 |
| Client provides policy/terms content | Pending | Needed for Phase 3 |
| Hotel phone number for "call to book" | Pending | Needed for Phase 7 |

---

## 14. Assumptions (Pending OTA API Confirmation)

1. OTA API returns inventory count per room per date
2. OTA supports push for create/modify/cancel
3. Rate plans are per-room, not global
4. Prices are tax-inclusive or include tax fields
5. OTA supports availability push from our side
6. Auth is key/secret or OAuth (not IP-whitelist only)
7. OTA returns error codes we can map to user messages

Any mismatch = scope clarification / extra charge.

---

## 15. Go-Live Checklist

[ ] All 8 phases complete
[ ] OTA API tested against sandbox
[ ] Razorpay live keys set + webhook configured
[ ] Twilio templates approved
[ ] SMTP tested (emails reach Gmail inbox)
[ ] Fallback JSON verified (simulate API down)
[ ] Pre-payment availability re-check tested
[ ] End-to-end test: success, failure, cancel, refund (manual)
[ ] Test booking appears in OTA + inventory drops
[ ] Admin onsite booking tested
[ ] Availability update pushes to OTA
[ ] Logs working + auto-purge
[ ] SSL active
[ ] Daily DB backups

---

End of Requirements