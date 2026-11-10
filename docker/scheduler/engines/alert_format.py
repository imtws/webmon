"""Problem/Recovery 알림 본문 생성: 항목 설명, 템플릿 렌더러, 기본 템플릿."""

import html
import re
from datetime import datetime

# 원인 코드 -> (표시 이름, 글자색, 배경색)
CAUSES = {
    "timeout": ("타임아웃", "#b45309", "#fef3c7"),
    "connect": ("접속 실패", "#6d28d9", "#ede9fe"),
    "keyword": ("키워드 탐지 불가", "#b91c1c", "#fee2e2"),
    "error": ("점검 오류", "#475569", "#e2e8f0"),
}

# HTML 모드에서도 이스케이프하지 않는 내부 값(색상)
RAW_VARS = {"cause_color", "cause_bg"}

DEFAULT_TEXT_TEMPLATE = """{#has_problems}[Problem] {problem_count}건 · {time}
{#problems}
{no}. {company} | {cause}{#status} | HTTP {status}{/status}
   URL : {url}
   키워드 : {keyword}
   조치 : {comment}
{/problems}{/has_problems}{#has_recoveries}
[Recovery] {recovery_count}건 · {time}
{#recoveries}- {company}{#duration} (지속 {duration}){/duration}
{/recoveries}{/has_recoveries}
웹사이트 모니터링 : {web_url}"""

DEFAULT_HTML_TEMPLATE = """<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;background-color:#f8fafc;padding:24px;font-family:'Malgun Gothic','맑은 고딕',Arial,sans-serif;">
<table role="presentation" width="760" align="center" cellspacing="0" cellpadding="0" style="max-width:760px;width:100%;background-color:#ffffff;border:1px solid #e2e8f0;border-top:4px solid #18aee7;border-radius:12px;">
  <tr><td style="padding:16px 20px;background-color:#ffffff;border-bottom:1px solid #e2e8f0;">
    <span style="color:#18aee7;font-size:18px;font-weight:800;">WebMon</span>&nbsp;<span style="color:#1e293b;font-size:15px;font-weight:700;">알림</span>
    <span style="color:#64748b;font-size:12px;">&nbsp;&nbsp;{time} · 장애 {problem_count} · 복구 {recovery_count}</span>
  </td></tr>
{#has_problems}  <tr><td style="padding:14px 20px 6px;font-size:14px;font-weight:800;color:#b91c1c;">Problem {problem_count}건</td></tr>
  <tr><td style="padding:0 20px 8px;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:12px;color:#334155;">
      <tr style="background-color:#e8f6fd;color:#0b86b8;font-size:11px;">
        <td width="24" align="center" style="padding:7px 6px;border:1px solid #cbd5e1;">#</td>
        <td width="120" align="center" style="padding:7px 6px;border:1px solid #cbd5e1;">업체</td>
        <td align="center" style="padding:7px 6px;border:1px solid #cbd5e1;">URL</td>
        <td width="100" align="center" style="padding:7px 6px;border:1px solid #cbd5e1;">원인</td>
        <td width="90" align="center" style="padding:7px 6px;border:1px solid #cbd5e1;">키워드</td>
      </tr>
{#problems}      <tr>
        <td rowspan="2" align="center" style="padding:7px 8px;border:1px solid #cbd5e1;color:#64748b;">{no}</td>
        <td style="padding:7px 8px;border:1px solid #cbd5e1;font-weight:800;color:#1e293b;">{company}</td>
        <td style="padding:7px 8px;border:1px solid #cbd5e1;"><a href="{url}" target="_blank" style="color:#18aee7;text-decoration:none;">{url}</a></td>
        <td align="center" style="padding:7px 8px;border:1px solid #cbd5e1;color:{cause_color};font-weight:800;">{cause_label}{#cause_note}<div style="font-size:10px;font-weight:400;color:#64748b;">{cause_note}</div>{/cause_note}{#status}<div style="font-size:10px;font-weight:400;color:#64748b;">HTTP {status}</div>{/status}</td>
        <td align="center" style="padding:7px 8px;border:1px solid #cbd5e1;">{keyword}</td>
      </tr>
      <tr><td colspan="4" align="left" style="padding:7px 8px;border:1px solid #cbd5e1;background-color:#f8fafc;text-align:left;"><b>조치사항 :</b> {comment}</td></tr>
{/problems}    </table>
  </td></tr>
{/has_problems}{#recoveries}- {company}{#duration} (지속 {duration}){/duration}
{/recoveries}{/has_recoveries}
웹사이트 모니터링 : {web_url}"""

DEFAULT_HTML_TEMPLATE = """<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;background-color:#f8fafc;padding:24px;font-family:'Malgun Gothic','맑은 고딕',Arial,sans-serif;">
<table role="presentation" width="760" align="center" cellspacing="0" cellpadding="0" style="max-width:760px;width:100%;background-color:#ffffff;border:1px solid #e2e8f0;border-top:4px solid #18aee7;border-radius:12px;">
  <tr><td style="padding:16px 20px;background-color:#ffffff;border-bottom:1px solid #e2e8f0;">
    <span style="color:#18aee7;font-size:18px;font-weight:800;">WebMon</span>&nbsp;<span style="color:#1e293b;font-size:15px;font-weight:700;">알림</span>
    <span style="color:#64748b;font-size:12px;">&nbsp;&nbsp;{time} · 장애 {problem_count} · 복구 {recovery_count}</span>
  </td></tr>
{#has_problems}  <tr><td style="padding:14px 20px 6px;font-size:14px;font-weight:800;color:#b91c1c;">Problem {problem_count}건</td></tr>
  <tr><td style="padding:0 20px 8px;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:12px;color:#334155;">
      <tr style="background-color:#e8f6fd;color:#0b86b8;font-size:11px;">
        <td width="24" align="center" style="padding:7px 6px;border:1px solid #cbd5e1;">#</td>
        <td width="120" align="center" style="padding:7px 6px;border:1px solid #cbd5e1;">업체</td>
        <td align="center" style="padding:7px 6px;border:1px solid #cbd5e1;">URL</td>
        <td width="100" align="center" style="padding:7px 6px;border:1px solid #cbd5e1;">원인</td>
        <td width="90" align="center" style="padding:7px 6px;border:1px solid #cbd5e1;">키워드</td>
      </tr>
{#problems}      <tr>
        <td rowspan="2" align="center" style="padding:7px 8px;border:1px solid #cbd5e1;color:#64748b;">{no}</td>
        <td style="padding:7px 8px;border:1px solid #cbd5e1;font-weight:800;color:#1e293b;">{company}</td>
        <td style="padding:7px 8px;border:1px solid #cbd5e1;"><a href="{url}" target="_blank" style="color:#18aee7;text-decoration:none;">{url}</a></td>
        <td align="center" style="padding:7px 8px;border:1px solid #cbd5e1;color:{cause_color};font-weight:800;">{cause_label}{#cause_note}<div style="font-size:10px;font-weight:400;color:#64748b;">{cause_note}</div>{/cause_note}{#status}<div style="font-size:10px;font-weight:400;color:#64748b;">HTTP {status}</div>{/status}</td>
        <td align="center" style="padding:7px 8px;border:1px solid #cbd5e1;">{keyword}</td>
      </tr>
      <tr><td colspan="4" align="left" style="padding:7px 8px;border:1px solid #cbd5e1;background-color:#f8fafc;text-align:left;"><b>조치사항 :</b> {comment}</td></tr>
{/problems}    </table>
  </td></tr>
{/has_problems}{#has_recoveries}  <tr><td style="padding:10px 20px 4px;font-size:14px;font-weight:800;color:#15803d;">Recovery {recovery_count}건</td></tr>
  <tr><td style="padding:0 20px 8px;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;font-size:12px;color:#334155;">
      <tr style="background-color:#e8f6fd;color:#0b86b8;font-size:11px;">
        <td width="24" align="center" style="padding:7px 6px;border:1px solid #e2e8f0;">#</td>
        <td width="120" align="center" style="padding:7px 6px;border:1px solid #e2e8f0;">업체</td>
        <td align="center" style="padding:7px 6px;border:1px solid #e2e8f0;">URL</td>
        <td width="150" align="center" style="padding:7px 6px;border:1px solid #e2e8f0;">복구 시각</td>
        <td width="80" align="center" style="padding:7px 6px;border:1px solid #e2e8f0;">지속 시간</td>
      </tr>
{#recoveries}      <tr><td style="padding:7px 6px;border:1px solid #e2e8f0;color:#94a3b8;">{no}</td><td style="padding:7px 6px;border:1px solid #e2e8f0;"><b>{company}</b></td><td style="padding:7px 6px;border:1px solid #e2e8f0;"><a href="{url}" target="_blank" style="color:#18aee7;text-decoration:none;">{url}</a></td><td style="padding:7px 6px;border:1px solid #e2e8f0;">{at}</td><td style="padding:7px 6px;border:1px solid #e2e8f0;">{duration}</td></tr>
{/recoveries}    </table>
  </td></tr>
{/has_recoveries}  <tr><td style="padding:12px 20px 18px;text-align:center;border-top:1px solid #f1f5f9;">
    <a href="{web_url}" target="_blank" style="display:inline-block;background-color:#18aee7;color:#ffffff;text-decoration:none;padding:7px 14px;font-size:12px;font-weight:800;border-radius:6px;">대시보드 바로가기</a>
    <div style="margin-top:10px;font-size:11px;color:#94a3b8;">본 메일은 WebMon에서 자동 발송되었습니다.</div>
  </td></tr>
</table>
</body>
</html>"""

_SECTION = re.compile(r"\{#(\w+)\}(.*?)\{/\1\}", re.S)
_VAR = re.compile(r"\{([A-Za-z_-]+)\}")


def normalize_comment(text: str) -> str:
    """Turn literal backslash-n sequences into real line breaks and drop blank lines."""
    text = re.sub(r"\\+n", "\n", text.replace("\r\n", "\n"))
    return "\n".join(line.strip() for line in text.split("\n") if line.strip())


def format_duration(start, end: datetime) -> str:
    if not start:
        return ""
    if isinstance(start, str):
        try:
            start = datetime.strptime(start, "%Y-%m-%d %H:%M:%S")
        except ValueError:
            return ""
    seconds = max(0, int((end - start).total_seconds()))
    if seconds <= 70:
        return "1분 이내 (일시적)"
    minutes = max(1, round(seconds / 60))
    hours, mins = divmod(minutes, 60)
    if hours:
        return f"약 {hours}시간 {mins}분" if mins else f"약 {hours}시간"
    return f"약 {mins}분"


def describe_problem(item: dict) -> dict:
    row = item["row"]
    cause = item.get("cause") or "keyword"
    label, fg, bg = CAUSES.get(cause, CAUSES["keyword"])
    if cause == "timeout":
        detail = f"{item.get('timeout', '')}초 내 응답 없음"
    elif cause == "connect":
        detail = "서버에 연결하지 못함 (응답 없음)"
    elif cause == "error":
        detail = (item.get("reason") or "").replace("에러 발생: ", "")
    elif item.get("empty_body"):
        detail = "접속은 되었으나 응답 본문이 비어 있음"
    else:
        detail = "접속은 되었으나 키워드가 없음"
    return {
        "company": str(row.get("company", "")),
        "url": str(row.get("url", "")),
        "keyword": str(row.get("contents", "")),
        "cause": label + (f"({item.get('timeout')}초 초과)" if cause == "timeout" and item.get("timeout") else ""),
        "cause_label": label,
        "cause_note": f"({item.get('timeout')}초 초과)" if cause == "timeout" and item.get("timeout") else "",
        "connected": cause == "keyword",
        "cause_color": fg,
        "cause_bg": bg,
        "detail": detail,
        "status": "" if cause == "timeout" else str(item.get("status") or ""),
        "engine": str(row.get("engine_type") or "lynx").upper(),
        "response_time": f"{float(item.get('elapsed', 0)):.3f}s",
        "comment": normalize_comment(str(row.get("comment", "") or "")) or "-",
    }


def describe_recovery(item: dict, now: datetime) -> dict:
    row = item["row"]
    since = row.get("fail_since")
    return {
        "company": str(row.get("company", "")),
        "url": str(row.get("url", "")),
        "since": since.strftime("%Y-%m-%d %H:%M:%S") if hasattr(since, "strftime") else str(since or ""),
        "at": now.strftime("%Y-%m-%d %H:%M:%S"),
        "duration": format_duration(since, now),
    }


def _subst(text: str, ctx: dict, escape: bool) -> str:
    def repl(match):
        name = match.group(1)
        value = ctx.get(name)
        if value is None or isinstance(value, (list, bool)):
            return match.group(0)
        value = str(value)
        if name == "comment":
            if escape:
                return html.escape(value, quote=True).replace("\n", "<br>")
            return value.replace("\n", "\n        ")
        if escape and name not in RAW_VARS:
            return html.escape(value, quote=True)
        return value

    return _VAR.sub(repl, text)


def _render(template: str, ctx: dict, escape: bool) -> str:
    # 치환 결과는 다시 해석하지 않도록 구간 단위로 한 번만 처리한다.
    out = []
    pos = 0
    for match in _SECTION.finditer(template):
        out.append(_subst(template[pos:match.start()], ctx, escape))
        value = ctx.get(match.group(1))
        if isinstance(value, list):
            for entry in value:
                out.append(_render(match.group(2), {**ctx, **entry}, escape))
        elif value:
            out.append(_render(match.group(2), ctx, escape))
        pos = match.end()
    out.append(_subst(template[pos:], ctx, escape))
    return "".join(out)


def render_alert(template: str, problems: list, recoveries: list,
                 now: str, web_url: str, html_mode: bool) -> str:
    ctx = {
        "time": now,
        "YYYY-MM-DD": now[:10],
        "web_url": web_url,
        "problem_count": len(problems),
        "recovery_count": len(recoveries),
        "has_problems": bool(problems),
        "has_recoveries": bool(recoveries),
        "problems": [{"no": n, **p} for n, p in enumerate(problems, 1)],
        "recoveries": [{"no": n, **r} for n, r in enumerate(recoveries, 1)],
    }
    return _render(template, ctx, html_mode).strip()
