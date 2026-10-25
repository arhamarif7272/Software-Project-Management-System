# Software PMS: JSON API

Base URL: `http://localhost:8000/api` · Always send `Accept: application/json`.
Auth: Laravel Sanctum bearer token. Optional on project endpoints: `?status_date=YYYY-MM-DD`.

| Method | Endpoint | Returns |
|---|---|---|
| POST | `/login` | Bearer token (`email`, `password`) |
| POST | `/logout` | Revokes the current token |
| GET | `/me` | Authenticated user |
| GET | `/projects` | All projects |
| GET | `/projects/{id}` | Charter, plan thresholds, stakeholders, team, milestones |
| GET | `/projects/{id}/dashboard` | Progress, budget, deadline, EVM, warnings, pending CRs, delays, critical defects |
| GET | `/projects/{id}/schedule` | Gantt rows, critical path, delays and their dependants, projected finish |
| GET | `/projects/{id}/evm` | PV, EV, AC, CPI, SPI, EAC, warnings, S-curve data |
| GET | `/projects/{id}/requirements` | Filters: `status`, `in_scope` |
| GET | `/projects/{id}/change-requests` | Every CR with before/after budget and deadline |
| GET | `/change-requests/{id}/impact` | Scope, budget and deadline effect of one CR |
| GET | `/projects/{id}/defects` | Filters: `severity`, `status`, `unresolved=1` |
| GET | `/projects/{id}/risks` | Ranked by likelihood x impact |

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/login -H "Accept: application/json" \
  -d email=you@example.com -d password=secret | jq -r .token)

curl -H "Accept: application/json" -H "Authorization: Bearer $TOKEN" \
  "http://localhost:8000/api/projects/1/evm?status_date=2026-08-15"
```
