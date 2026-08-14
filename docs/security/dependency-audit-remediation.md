# Dependency Audit Remediation

Date: 2026-08-15

## Summary

Phase 1A.1 remediated the initial dependency audit findings without changing Laravel major versions or adding Phase 1B functionality.

- Initial Composer audit: 38 advisories affecting 13 packages.
- Final Composer audit: 0 advisories.
- Initial npm audit: 10 vulnerable package nodes, covering 36 advisory entries.
- Final npm audit: 0 vulnerabilities.
- Machine-readable artifacts:
  - `docs/security/composer-audit-initial.json`
  - `docs/security/composer-audit-final.json`
  - `docs/security/npm-audit-initial.json`
  - `docs/security/npm-audit-final.json`

## Composer Inventory

| Package | Initial | Final | Direct or transitive | Introduced by | Prod/dev | Advisory identifiers | Severity | Affected range | Patched version used | Functionality used | Remediation | Risk | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `guzzlehttp/guzzle` | 7.10.0 | 7.15.3 | Transitive | `laravel/framework` | Prod | PKSA-gcrk-3vtt-1r14, PKSA-cnw1-2ytm-cgr8, PKSA-fy2t-3c5f-827y, PKSA-qxvb-2bpp-dnk6, PKSA-bbs6-q5q9-f3t4, PKSA-bcdd-5xc7-gwfb, PKSA-pwsk-hy21-4gby, PKSA-93qv-9n9h-6k6p, PKSA-k22t-f949-t9g6 | 1 high, 8 medium | `<7.15.2`, `<7.15.1`, `<7.14.2`, `<7.12.3`, `<7.12.1` | 7.15.3 | No app-specific outbound HTTP flow found; Laravel may use HTTP client when configured. | Updated Laravel and transitive dependencies with `--with-all-dependencies`. | Low; patch/minor within Laravel 12 constraints. | Resolved |
| `guzzlehttp/psr7` | 2.8.0 | 2.13.0 | Transitive | `guzzlehttp/guzzle` | Prod | PKSA-vznr-tgp9-fd7d, PKSA-7qs6-zvnz-h66r, PKSA-gm5x-j3mz-71n9, PKSA-jj5t-2zs1-dcfm | Medium | `<2.12.3`, `<2.12.1`, `<2.10.2` | 2.13.0 | Used indirectly by HTTP stack only. | Updated through Guzzle. | Low. | Resolved |
| `laravel/framework` | 12.28.1 | 12.66.0 | Direct | Root requirement | Prod | PKSA-m5cs-t1y6-qpcs, PKSA-3r5d-mb8f-1qw9, PKSA-mdq4-51ck-6kdq | 1 high, 1 medium, 1 unspecified | `<12.61.1`, `<12.60.0`, `>=12.0.0,<12.60.0` | 12.66.0 | Yes; framework, validation, routing, signed URL support available. | Updated within Laravel 12. | Medium; broad framework patch/minor update, covered by full PHP and browser verification. | Resolved |
| `league/commonmark` | 2.7.1 | 2.10.0 | Transitive | `laravel/framework` | Prod | PKSA-5mzr-szzf-z6cn, PKSA-cqd6-fg4n-nxpf, PKSA-1q6p-sqkj-8mmj, PKSA-mc58-w91n-f5gv, PKSA-t21r-vtr5-3mdz, PKSA-scnn-p8mm-jbft, PKSA-21fb-n1x5-5nf7, PKSA-2cx9-ynrq-qdk3 | 4 high, 4 medium | `>=2.0.0,<2.9.0`, `>=1.5.0,<2.9.0`, `<=2.8.3`, `<=2.8.1`, `<=2.8.0` | 2.10.0 | No user-facing Markdown parsing found in Phase 1A; Laravel mail/notifications may use it. | Updated through Laravel. | Low. | Resolved |
| `phpunit/phpunit` | 11.5.36 | 11.5.56 | Direct | Root dev requirement | Dev | PKSA-z3gr-8qht-p93v | High | `>=11.0.0,<11.5.50` | 11.5.56 | Used for tests only. | Updated PHPUnit 11. | Low; same major. | Resolved |
| `psy/psysh` | 0.12.10 | 0.12.24 | Transitive | `laravel/tinker` | Dev/local tooling | PKSA-4s4z-t146-6123 | Medium | `<=0.11.22`, `>=0.12.0,<=0.12.18` | 0.12.24 | Used by Tinker/local console only. | Updated Tinker and transitive dependency. | Low. | Resolved |
| `symfony/http-foundation` | 7.3.3 | 7.4.16 | Transitive | `laravel/framework`, `symfony/http-kernel`, `fruitcake/php-cors` | Prod | PKSA-y6py-qpv1-h52p, PKSA-365x-2zjk-pt47 | 1 high, 1 medium | `>=7.3.0,<7.4.0`, `>=7.4.0,<7.4.13`, `>=7.3.0,<7.3.7` plus older branches | 7.4.16 | Yes; request/response handling. | Updated via Laravel/Symfony minor. | Medium; covered by route, auth, and browser verification. | Resolved |
| `symfony/mailer` | 7.3.3 | 7.4.15 | Transitive | `laravel/framework` | Prod | PKSA-28rh-rzzn-djk4 | Medium | `>=7.3.0,<7.4.0`, `>=7.4.0,<7.4.12` plus older branches | 7.4.15 | Mailer available; no production mail flow verified in Phase 1A. | Updated via Laravel/Symfony minor. | Low. | Resolved |
| `symfony/mime` | 7.3.2 | 7.4.16 | Transitive | `laravel/framework`, `symfony/mailer` | Prod | PKSA-wtxr-p26d-nn42, PKSA-2n2k-66v2-bwg3 | 1 high, 1 medium | `>=7.3.0,<7.4.0`, `>=7.4.0,<7.4.12` plus older branches | 7.4.16 | Mail and MIME support available. | Updated via Laravel/Symfony minor. | Low. | Resolved |
| `symfony/polyfill-intl-idn` | 1.33.0 | 1.38.1 | Transitive | `symfony/mime`/Symfony stack | Prod | PKSA-dwsq-ppd2-mb1x | Low | `>=1.17.1,<1.38.1` | 1.38.1 | Used indirectly for IDN handling. | Updated Symfony polyfills. | Low. | Resolved |
| `symfony/process` | 7.3.3 | 7.4.13 | Transitive | `laravel/framework`, `laravel/pail`, `laravel/sail` | Prod/dev tooling | PKSA-rkkf-636k-qjb3 | Medium | `>=7.3,<7.3.11`, `>=7.4,<7.4.5` plus older branches | 7.4.13 | Used by console/process tooling. | Updated via Laravel/Symfony minor. | Low. | Resolved |
| `symfony/routing` | 7.3.2 | 7.4.15 | Transitive | `laravel/framework` | Prod | PKSA-bf7t-jnpz-492k, PKSA-yc7t-91v9-99xs | Medium | `>=7.3.0,<7.4.0`, `>=7.4.0,<7.4.13`, `>=7.4.0,<7.4.12` plus older branches | 7.4.15 | Yes; route generation. | Updated via Laravel/Symfony minor. | Medium; covered by route list and browser navigation. | Resolved |
| `symfony/yaml` | 7.3.3 | 7.4.15 | Transitive | `laravel/sail` | Dev/local tooling | PKSA-v5yj-8nmz-sk2q, PKSA-ft77-7h5f-p3r6, PKSA-b14r-zh1d-vdrc | Low | `>=7.3.0,<7.4.0`, `>=7.4.0,<7.4.12` plus older branches | 7.4.15 | Used by local/dev tooling; no app YAML parsing found. | Updated Sail/Symfony. | Low. | Resolved |

Composer packages updated included `laravel/framework`, `laravel/tinker`, `laravel/pail`, `laravel/pint`, `laravel/sail`, `phpunit/phpunit`, `nunomaduro/collision`, `spatie/laravel-permission`, `mallardduck/blade-lucide-icons`, and their transitive dependencies. `webmozart/assert` was removed by dependency resolution because it was no longer required.

## npm Inventory

| Package | Initial | Final | Direct or transitive | Introduced by | Prod/dev | Advisory identifiers | Severity | Affected range | Patched version used | Functionality used | Remediation | Risk | Status |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `vite` | 7.1.5 | 7.3.6 | Direct | Root dev dependency | Dev/build | GHSA-93m4-6634-74q7, GHSA-4w7w-66w2-5vf9, GHSA-v2wj-q39q-566r, GHSA-p9ff-h696-f583, GHSA-v6wh-96g9-6wx3, GHSA-fx2h-pf6j-xcff | 3 high, 3 moderate | `>=7.0.0 <=7.3.4`, `>=7.1.0 <=7.1.10`, `>=7.0.0 <=7.3.1` | 7.3.6 | Yes; dev server and production build. | Updated Vite 7 only. | Medium; build and browser smoke passed. | Resolved |
| `rollup` | 4.50.1 | 4.62.4 | Transitive | `vite` | Dev/build | GHSA-mw96-cpmx-2vgc | High | `>=4.0.0 <4.59.0` | 4.62.4 | Used by Vite build. | Non-forced `npm audit fix` updated lockfile. | Low. | Resolved |
| `picomatch` | 2.3.1 / 4.0.3 | 2.3.2 / 4.0.5 | Transitive | `tailwindcss`, `vite`, `@inertiajs/vite`, `laravel-vite-plugin` | Dev/build | GHSA-3v7f-55p6-f55p, GHSA-c2c7-rcm5-vvqj | High/moderate | `<2.3.2`, `>=4.0.0 <4.0.4` | 2.3.2 and 4.0.5 | Used by glob matching in build tooling. | Compatible updates plus non-forced audit fix. | Low. | Resolved |
| `glob` | 10.4.5 | 10.5.0 | Transitive | `tailwindcss` via `sucrase` | Dev/build | GHSA-5j98-mcp5-4vw2 | High | `>=10.2.0 <10.5.0` | 10.5.0 | Build-chain CLI dependency; app does not call glob CLI. | Non-forced audit fix. | Low. | Resolved |
| `minimatch` | 9.0.5 | 9.0.9 | Transitive | `glob` | Dev/build | GHSA-3ppc-4f35-3m26, GHSA-7r86-cg39-jmmj, GHSA-23c5-xmqv-rm74 | High | `>=9.0.0 <9.0.7` | 9.0.9 | Build-chain matching. | Non-forced audit fix. | Low. | Resolved |
| `brace-expansion` | 2.0.x/2.1.3 range | 2.1.4 | Transitive | `minimatch` | Dev/build | GHSA-f886-m6hf-6m8v, GHSA-3jxr-9vmj-r5cp, GHSA-mh99-v99m-4gvg, GHSA-rgw5-rvv9-x895 | High/moderate | `>=2.0.0 <2.1.4` | 2.1.4 | Build-chain matching. | Non-forced audit fix. | Low. | Resolved |
| `shell-quote` | 1.8.3 | 1.9.0 | Transitive | `concurrently` | Dev tooling | GHSA-w7jw-789q-3m8p, GHSA-395f-4hp3-45gv | Critical/high | `>=1.1.0 <=1.8.3`, `<=1.8.4` | 1.9.0 | Used by local concurrent dev script. | Updated `concurrently` to 9.2.4. | Low. | Resolved |
| `concurrently` | 9.2.1 | 9.2.4 | Direct | Root dev dependency | Dev tooling | Via `shell-quote` | Critical aggregate | 9.2.1 pulled vulnerable `shell-quote` | 9.2.4 | Used by Composer `dev` script. | Updated within major. | Low. | Resolved |
| `tar` | 7.4.3 | Removed | Transitive | Unused `@tailwindcss/vite` | Dev/build | GHSA-34x7-hfp2-rc4v, GHSA-8qq5-rm4j-mr97, GHSA-83g3-92jg-28cx, GHSA-qffp-2rhf-9h96, GHSA-9ppj-qmqm-q256, GHSA-r6q2-hw4h-h46w, GHSA-vmf3-w455-68vh, GHSA-w8wr-v893-vjvp, GHSA-23hp-3jrh-7fpw, GHSA-8x88-c5mf-7j5w, GHSA-gvwx-54wh-qm9j, GHSA-r292-9mhp-454m | Critical/high/moderate | `<=7.5.20` | Removed from tree | Not used; `@tailwindcss/vite` was not referenced by `vite.config.js` and app uses Tailwind 3 PostCSS. | Removed unused direct dependency `@tailwindcss/vite`. | Low; build verified. | Resolved |
| `yaml` | 2.8.1 | 2.9.0 | Transitive | `tailwindcss`/`postcss-load-config`, optional peer of Vite | Dev/build | GHSA-48c2-rrv3-qjmp | Moderate | `>=2.0.0 <2.8.3` | 2.9.0 | Build config loading. | Non-forced audit fix. | Low. | Resolved |

npm packages updated or changed included `vite`, `tailwindcss`, `@tailwindcss/forms`, `@tailwindcss/typography`, `autoprefixer`, `postcss`, `concurrently`, `laravel-vite-plugin`, and transitive build packages. `@tailwindcss/vite` was removed because it was unused and was the source of the `tar` critical chain. `playwright` was added as a dev-only verification dependency for browser smoke testing against local Chrome.

No npm overrides were added. No forced audit fix was used.

## Remaining Advisories

None.
