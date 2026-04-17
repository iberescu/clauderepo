# Solar Proposal MVP

A sales-focused web app that turns a street address into a rooftop solar
proposal: candidate discovery, Solar API analysis, deterministic panel
layout, AI-enhanced rendering, pricing, and a branded PDF offer.

The project is built in phases. Each phase is self-contained, runnable, and
has tests.

| Phase | Scope                                                   | Status |
| ----: | ------------------------------------------------------- | :----: |
|   1   | Laravel 11 skeleton, filesystem persistence, status tracking, street search & candidate discovery | done |
|   2   | Solar analysis + deterministic panel layout engine      | done |
|   3   | Roof overlay rendering + Gemini enhancement             | done |
|   4   | Pricing engine + HTML/PDF proposal generator            | todo |
|   5   | Vue 3 SPA (search → street → building → proposal)       | todo |
|   6   | Docker Compose + end-to-end tests + finalised README    | todo |

## Architecture

- **Backend:** PHP 8.3+ / Laravel 11, clean layering (controllers → form
  requests → services → repositories → DTOs).
- **Persistence:** local filesystem only. Every project lives under
  `backend/storage/app/projects/{project_id}/`. Every step writes a human-
  readable `.txt` summary plus a `.json` sidecar for round-tripping.
- **No database, no Redis, no auth, no queue.** Long-running work is
  synchronous for the MVP and structured behind service interfaces so it can
  be moved to background jobs later.
- **External providers:** Google Maps, Google Solar API, and Gemini image
  generation sit behind interfaces. Set `FAKE_PROVIDERS=true` (default) to
  use deterministic in-process doubles and run the full flow offline.

## Phase 1 — what's shipped

Backend:

```
backend/
  app/
    DTOs/                        # ProjectInput, NormalizedQuery, Candidate
    Http/Controllers/Api/V1/     # Project, Candidate, Settings controllers
    Http/Requests/               # Form-request validation
    Providers/AppServiceProvider # Fake ↔ Google binding via FAKE_PROVIDERS
    Repositories/                # ProjectFileRepository (file-backed CRUD)
    Services/
      Contracts/                 # GeoSearch + CandidateDiscovery interfaces
      Fake/                      # Deterministic offline doubles
      Google/                    # Google Geocoding / Places implementations
      ProjectOrchestrationService
      StatusFileService          # current_status.txt, progress.txt, logs
      ConfigFileService          # editable storage/app/config text files
    Support/                     # Slug, ProjectPaths, TextKv
  config/solar.php               # provider + default assumptions
  routes/api.php                 # /api/v1/* endpoints
  tests/                         # PHPUnit feature + unit tests (14 passing)
```

API (Phase 1 subset):

| Method | Path                                                | Purpose                          |
| -----: | :-------------------------------------------------- | :------------------------------- |
|    GET | `/api/v1/health`                                    | liveness                         |
|    GET | `/api/v1/projects`                                  | list existing project IDs        |
|   POST | `/api/v1/projects`                                  | create project + resolve street  |
|    GET | `/api/v1/projects/{id}`                             | full project summary             |
|    GET | `/api/v1/projects/{id}/status`                      | current status, progress, errors |
|    GET | `/api/v1/projects/{id}/candidates`                  | list candidates                  |
|   POST | `/api/v1/projects/{id}/select-candidate`            | choose candidate by index        |
|   POST | `/api/v1/projects/{id}/analyze-building`            | Solar API insights + data layers |
|    GET | `/api/v1/projects/{id}/analysis`                    | parsed solar analysis summary    |
|   POST | `/api/v1/projects/{id}/generate-layout`             | deterministic panel layout       |
|    GET | `/api/v1/projects/{id}/layout`                      | layout summary + coordinates     |
|   POST | `/api/v1/projects/{id}/generate-render`             | base + overlay + Gemini render   |
|    GET | `/api/v1/projects/{id}/render`                      | render notes + prompt + paths    |
|    GET | `/api/v1/projects/{id}/render/images/{name}`        | stream PNG (base/overlay/render) |
|    GET | `/api/v1/settings`                                  | read editable config             |
|   POST | `/api/v1/settings`                                  | update a config section          |

### Rendering pipeline (Phase 3)

- `RenderingService` produces three PNGs from the stored layout, all using
  the exact panel coordinates from `layout/panel_coordinates.txt`:
  - `render/roof_base.png` — clean top-down roof plates, grid + labels
  - `render/roof_overlay.png` — same base with deterministic panel rectangles
    drawn on top (this is the mask handed to the AI)
  - `render/roof_render.png` — photorealistic version returned by Gemini
- `GeminiImageServiceInterface` has a real client
  (`GoogleGeminiImageService`, calls `models/gemini-2.5-flash-image:generateContent`)
  and a deterministic fake (`FakeGeminiImageService`) that applies a
  brightness/contrast/gradient/vignette treatment so the downstream proposal
  always has a plausible render, even offline.
- AI never touches geometry. If Gemini fails, the overlay is saved as the
  render and the error is recorded in `render/render_notes.txt`.
- Prompt is stored verbatim in `render/gemini_prompt.txt` so proposals stay
  reproducible.

### Solar analysis and layout engine (Phase 2)

- `SolarApiServiceInterface` — real `GoogleSolarApiService` hits Building
  Insights + Data Layers; `FakeSolarApiService` derives a deterministic roof
  geometry (2–4 segments with pitch, azimuth, sunshine hours, shading) from
  a hash of the selected candidate so results are reproducible offline.
- `RoofLayoutService` is pure geometry: it fits rectangular panels per
  segment in both portrait and landscape, applies configurable setbacks +
  row/column gaps, picks the orientation with the most panels (tiebreak on
  annual kWh) and returns segment-local `(x, y, w, h)` coordinates plus
  totals.
- Every step writes human-readable `.txt` summaries alongside `.json`
  sidecars so the downstream renderer / proposal engine can reload the
  layout without re-calling any service.

## Getting started

```bash
cd backend
cp .env.example .env            # FAKE_PROVIDERS=true by default
composer install
php artisan key:generate
php artisan serve
```

Smoke test:

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/projects \
  -H 'Content-Type: application/json' \
  -d '{"street":"Rua das Flores","city":"Lisbon","country":"Portugal"}'
```

The response contains the generated `project_id`. The resulting folder tree
lives under `backend/storage/app/projects/{project_id}/` and matches the
specification (`input/`, `candidates/`, `building/`, `solar/`, `layout/`,
`pricing/`, `render/`, `proposal/`, `logs/`, `status/`).

## Tests

```bash
cd backend
php artisan test
```

Current suite: 14 tests, 61 assertions.

## Environment

Copy `backend/.env.example`. Relevant variables:

```
FAKE_PROVIDERS=true            # use deterministic doubles
GOOGLE_MAPS_API_KEY=           # required when FAKE_PROVIDERS=false
GOOGLE_SOLAR_API_KEY=          # required when FAKE_PROVIDERS=false
GEMINI_API_KEY=                # required when FAKE_PROVIDERS=false
FRONTEND_URL=http://localhost:5173   # used for CORS
```
