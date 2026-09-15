#!/usr/bin/env bash
# API smoke test using the local dev-login bypass (APP_ENV=local). No Azure needed.
# Usage:  bash scripts/smoke.sh   (with the API running and DevSeed loaded)
set -u
BASE="${API_BASE:-http://localhost:8080/api/v1}"
pass=0; fail=0
ok(){ echo "  ok  $1"; pass=$((pass+1)); }
no(){ echo "  ERR $1"; fail=$((fail+1)); }

# extract the access token from a JSON response without jq
token_of(){ sed -n 's/.*"access_token":"\([^"]*\)".*/\1/p'; }

login(){ # $1=email -> prints access token
  curl -s -X POST "$BASE/auth/dev-login" -H 'Content-Type: application/json' \
    -d "{\"email\":\"$1\"}" | token_of
}
auth_get(){ curl -s -o /dev/null -w '%{http_code}' "$BASE/$2" -H "Authorization: Bearer $1"; }
body_get(){ curl -s "$BASE/$2" -H "Authorization: Bearer $1"; }

echo "# Health"
[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/../../health")" = "200" ] && ok "health" || no "health"

echo "# Dev-login (4 roles)"
ADMIN=$(login admin@bilbypixel.com);   [ -n "$ADMIN" ] && ok "global_admin token" || no "global_admin token"
STAFF=$(login designer@bilbypixel.com); [ -n "$STAFF" ] && ok "agency_staff token" || no "agency_staff token"
OWNER=$(login owner@acme.com);         [ -n "$OWNER" ] && ok "client_owner token" || no "client_owner token"
MEMBER=$(login member@acme.com);       [ -n "$MEMBER" ] && ok "client_member token" || no "client_member token"

echo "# Scoped reads"
[ "$(auth_get "$ADMIN" requests)" = "200" ] && ok "admin GET /requests 200" || no "admin GET /requests"
ADMIN_N=$(body_get "$ADMIN" requests | grep -o '"id"' | wc -l)
STAFF_N=$(body_get "$STAFF" requests | grep -o '"id"' | wc -l)
echo "    admin sees $ADMIN_N requests; staff sees $STAFF_N (assigned-only)"
[ "$STAFF_N" -le "$ADMIN_N" ] && ok "staff scope <= admin scope" || no "staff scope"

echo "# Authorization gates"
[ "$(auth_get "$OWNER" team-members)" = "403" ] && ok "client blocked from /team-members (403)" || no "client /team-members not blocked"
[ "$(auth_get "$ADMIN" team-members)" = "200" ] && ok "admin /team-members 200" || no "admin /team-members"
[ "$(auth_get "$OWNER" subscriptions)" = "200" ] && ok "owner /subscriptions 200 (own only)" || no "owner /subscriptions"

echo "# Client create + approve flow"
NEWID=$(curl -s -X POST "$BASE/requests" -H "Authorization: Bearer $OWNER" -H 'Content-Type: application/json' \
  -d '{"title":"Smoke test request","type":"graphic_design"}' | sed -n 's/.*"id":\([0-9]*\).*/\1/p' | head -1)
[ -n "$NEWID" ] && ok "owner created request #$NEWID" || no "owner create request"
[ "$(auth_get "$MEMBER" "requests/$NEWID")" = "200" ] && ok "member sees same-tenant request" || no "member tenant read"

echo "========================================"
echo "PASS $pass   FAIL $fail"
[ "$fail" -eq 0 ] || exit 1
