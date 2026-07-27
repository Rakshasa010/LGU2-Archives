# Debug Session: cpanel-charts-missing
**Status:** [OPEN]
**Created:** 2026-07-25
**Issue:** Charts render on localhost but show blank/crash on deployed cPanel website

## Hypotheses (To Be Verified)
| # | Hypothesis | Likelihood | Test Method |
|---|------------|------------|-------------|
| H1 | Chart.js CDN is blocked/failing to load on cPanel server (CSP, firewall, or network issue) | High | Check browser console for 404/blocked errors on chart.js CDN |
| H2 | JavaScript execution order issue: Chart init runs before Chart.js library is fully loaded | High | Check if `typeof Chart === 'undefined'` warning appears in console |
| H3 | Tailwind CSS CDN loading order or CSS clash hides the canvas (height 0, overflow hidden) | Medium | Check computed styles of canvas elements in DevTools |
| H4 | MIME type or server config issue: cPanel serves .php files with headers that break JavaScript | Medium | Check Network tab for Content-Type headers on the page |
| H5 | PHP error/warning in archives-landing.php on deployed server injects HTML into JSON/script blocks, breaking JS syntax | High | View page source on deployed site - check for PHP warnings inside <script> tags |

## Evidence Log
| Timestamp | Event | Data |
|-----------|-------|------|
| - | Session initialized | - |

## Instrumentation Plan
1. Add inline error boundary checks
2. Verify Chart.js availability
3. Check canvas element dimensions
