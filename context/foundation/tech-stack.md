---
starter_id: laravel
package_manager: composer
project_name: root-keeper
hints:
  language_family: php
  team_size: solo
  deployment_target: self-host
  ci_provider: github-actions
  ci_default_flow: manual-promotion
  bootstrapper_confidence: verified
  path_taken: custom
  quality_override: true
  self_check_answers:
    typed: true
    from_official_starter: true
    conventions: true
    docs_current: true
    can_judge_agent: true
  has_auth: true
  has_payments: false
  has_realtime: false
  has_ai: false
  has_background_jobs: false
  frontend_framework: react-inertia-typescript
---

## Why this stack

A solo developer building a genealogy web app in a 7-week after-hours window (hard deadline 2026-11-04) needs registration, login, per-user data isolation and private scan storage without spending the budget on foundations. The PRD requires that data and scans never reach third parties, and the owner self-hosts with Docker on a VPS behind Traefik, which ruled out managed-services stacks. Laravel won over Go: its filesystem layer keeps scans on a local volume served only through authorized routes, and Eloquent with migrations fits the person–marriage–parent-child model. The frontend is React with TypeScript via Inertia, from Laravel's official React starter kit: it ships registration and login, needs no separate API, and React's ecosystem best serves the graphical tree view and zoomable scan preview. Laravel fails the typed gate, accepted knowingly: compensation is strict_types, typed properties and return types, Larastan in CI, Form Requests, ownership policies, and TypeScript on the frontend. CI (lint, static analysis, tests) runs on GitHub Actions; deploys are manual: the owner runs `scripts/deploy.sh` on the VPS after merging to `main`.
