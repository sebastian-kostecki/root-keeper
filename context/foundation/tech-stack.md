---
starter_id: laravel
package_manager: composer
project_name: root-keeper
hints:
  language_family: php
  team_size: solo
  deployment_target: self-host
  ci_provider: github-actions
  ci_default_flow: auto-deploy-on-merge
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
---

## Why this stack

A solo developer building a genealogy web app in a 7-week after-hours window (hard deadline 2026-11-04) needs registration, login, per-user data isolation and private scan storage without spending the budget on foundations. The PRD requires that data and scans never reach third parties, and the owner deploys with Docker on their own VPS behind Traefik, which ruled out the managed-services default (Astro + Supabase + Cloudflare). Among self-hostable options the choice narrowed to Laravel versus Go; Laravel won because its official starter kits cover authentication, its filesystem layer stores scans on a local volume served only through authorized routes, and Eloquent with migrations fits the person–marriage–parent-child model, leaving most of the budget for the source-confirmation domain logic. Laravel fails the typed gate, accepted knowingly: compensation is strict_types, typed properties and return types, Larastan in CI, validation in Form Requests and ownership checks in policies, documented in the agent instruction file. CI runs on GitHub Actions with auto-deploy on merge to the VPS.
