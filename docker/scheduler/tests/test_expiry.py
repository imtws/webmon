import unittest
from datetime import date, datetime, timedelta
from unittest.mock import AsyncMock, MagicMock, patch

from engines.asset import parse_whois_expiry, should_check_whois
from engines.report import _load_rows, build_html_report


class ExpiryReportTests(unittest.IsolatedAsyncioTestCase):
    async def test_report_keeps_both_enabled_dates_and_due_counts(self):
        today = date.today()
        rows = [
            {"group_name": "", "company": "SSL", "url": "https://example.com",
             "ssl_mon": 1, "ssl_expiry": today + timedelta(days=5),
             "dom_mon": 1, "dom_expiry": today + timedelta(days=90)},
            {"group_name": "", "company": "Domain", "url": "https://example.org",
             "ssl_mon": 1, "ssl_expiry": today + timedelta(days=120),
             "dom_mon": 1, "dom_expiry": today - timedelta(days=1)},
            {"group_name": "", "company": "Disabled", "url": "https://example.net",
             "ssl_mon": 1, "ssl_expiry": today + timedelta(days=1),
             "dom_mon": 0, "dom_expiry": today + timedelta(days=90)},
            {"group_name": "", "company": "Missing", "url": "https://example.edu",
             "ssl_mon": 1, "ssl_expiry": today + timedelta(days=2),
             "dom_mon": 1, "dom_expiry": None},
        ]
        cur = MagicMock()
        cur.execute = AsyncMock()
        cur.fetchall = AsyncMock(return_value=rows)
        conn = MagicMock()
        conn.cursor.return_value.__aenter__.return_value = cur
        pool = MagicMock()
        pool.acquire.return_value.__aenter__.return_value = conn
        context = MagicMock()
        context.__aenter__.return_value = pool

        with patch("engines.report.get_pool", return_value=context):
            result = await _load_rows(30)

        self.assertEqual(result[0]["dom_days"], 90)
        self.assertEqual(result[1]["ssl_days"], 120)
        self.assertIsNone(result[2]["dom_days"])
        self.assertIsNone(result[3]["dom_days"])
        rendered = build_html_report(today, result, 30, "https://webmon.example")
        self.assertIn("SSL 3 / 도메인 1", rendered)
        self.assertIn((today + timedelta(days=90)).isoformat(), rendered)
        self.assertIn((today + timedelta(days=120)).isoformat(), rendered)
        self.assertIn("1일 경과", rendered)
        self.assertEqual(rendered.count(">-</span>"), 2)


class WhoisTests(unittest.TestCase):
    def test_schedule_uses_successful_whois_time(self):
        now = datetime(2026, 11, 9, 9)
        expiry = date(2027, 1, 1)
        self.assertTrue(should_check_whois(expiry, None, now))
        self.assertFalse(should_check_whois(expiry, now - timedelta(days=6), now))
        self.assertTrue(should_check_whois(expiry, now - timedelta(days=7), now))
        self.assertTrue(should_check_whois(now.date() + timedelta(days=3), now, now))
        self.assertTrue(should_check_whois("bad", now, now))

    def test_parse_korean_and_international_dates(self):
        self.assertEqual(parse_whois_expiry("사용 종료일 : 2030. 01. 29."), "2030-01-29")
        self.assertEqual(parse_whois_expiry("Registry Expiry Date: 2028-09-14T04:00:00Z"),
                         "2028-09-14")


if __name__ == "__main__":
    unittest.main()
