# KTSTA Bus Ticketing System v3.0
## Katsina State Transport Authority

## Quick Start
1. Copy `ktsta/` to `C:\xampp\htdocs\ktsta\`
2. Import `database.sql` then `database_v2.sql` into phpMyAdmin as `ktsta_db`
3. Visit http://localhost/ktsta

## Login Credentials
| Role | Email | Password |
|------|-------|----------|
| Admin | admin@ktsta.gov.ng | password |
| Officer | officer@ktsta.gov.ng | password |
| Driver | driver1@ktsta.gov.ng | password |
| Passenger | passenger@test.com | password |

## All Pages
- / index.php — Landing page
- /pages/login.php — Role-based login
- /pages/register.php — 3-step onboarding with OTP
- /pages/forgot-password.php — 3-step password reset with strength meter
- /pages/search.php — Trip search + seat map + booking
- /pages/routes.php — All routes
- /pages/schedule.php — Monthly calendar view
- /pages/fare-calculator.php — Fare estimator with promo codes
- /pages/track.php — Live Leaflet.js tracking map
- /pages/charter.php — Charter request form
- /pages/lost-found.php — Lost & found system
- /pages/reviews.php — Trip ratings & reviews
- /pages/print-ticket.php — Printable A5 ticket
- /pages/contact.php — Contact form
- /pages/about.php — About KTSTA
- /passenger/dashboard.php — Passenger overview
- /passenger/tickets.php — My tickets
- /passenger/wallet.php — Wallet & top-up
- /passenger/loyalty.php — Loyalty rewards (4-tier)
- /passenger/profile.php — Profile & password change
- /passenger/feedback.php — Feedback & complaints
- /admin/dashboard.php — 9-tab admin control panel
- /admin/staff.php — Staff management & assignments
- /admin/maintenance.php — Bus maintenance + reports + CSV export
- /officer/dashboard.php — QR scanner, manifest, create ticket
- /driver/dashboard.php — Driver trip panel
- /api/book.php — Booking API
- /api/promo.php — Promo validation API

## New Features in v3.0
- Full 3-step password reset (email OTP new-password)
- Monthly schedule calendar
- Interactive fare calculator with promo codes
- Live bus tracking with Leaflet.js map
- 4-tier loyalty rewards system
- Bus maintenance log with service scheduling
- Staff management with bus/trip assignments
- Revenue reports and CSV export
- Charter bus booking system
- Lost & Found portal
- Trip reviews and star ratings
- Professional printable A5 e-tickets
- Promo code system with admin management
- Contact page with emergency line
