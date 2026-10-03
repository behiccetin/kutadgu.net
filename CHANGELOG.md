# Changelog

All notable changes to Kutadgu are recorded here. Versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- The test scripts (`sinama/`, 82 scripts) are now in the repository; the founders' e-mail addresses are read from a local file, never stored in the repository; the folder is closed to the web.
- Public source repository at https://github.com/behiccetin/kutadgu.net, published as a history-free snapshot; `GONDER.bat` updates it before deploying, and stops if it cannot.
- Optional per-work DOI through Zenodo: drafts are prepared automatically, publishing is always a manual action by a named person; sandbox mode for testing.
- `README.md`, `GOVERNANCE.md`, `ROADMAP.md` and this changelog.
- `varsayilan_yazar` setting for records whose author field was left empty.

### Changed
- Public contact address is now editor@kutadgu.net (OAI-PMH `Identify`, security reports) instead of a personal address.
- The site footer now names the founding board and links to the source code, instead of naming one person.
- Structured data (schema.org) lists the founding board as founders, read from the founding record.
- The declaration is written in the voice of the founding board; the clause protecting names now protects the names of the founding board.
- Contact and support forms are addressed to the publication's management rather than to one person.
- `ana_site` is empty by default and no longer links to a personal website; it is shown only if an instance sets it.
- Admin session key and trusted-browser cookie renamed to `kutadgu_yonetim` and `kutadgu_guven`; existing trusted-browser cookies keep working until they expire. Administrators are signed out once after upgrading.
- Licence metadata in `CITATION.cff` and `.zenodo.json` aligned with `LISANS.md`: AGPL-3.0-only.

### Removed
- Leftover comments and defaults that referred to an older, unrelated backend.

## [1.0.0] - 2026-08-13

First public release, deposited on Zenodo: concept DOI [10.5281/zenodo.21919023](https://doi.org/10.5281/zenodo.21919023), version DOI [10.5281/zenodo.21919024](https://doi.org/10.5281/zenodo.21919024).

- Two publication tracks, open signed peer review, board votes with recorded reasoning
- Permanent identifiers (Tamga) with check digit
- OAI-PMH 2.0, full archive dump with fingerprint, sitemap
- ORCID sign-in and verification
- Turkish and English interface, six labelled machine-translated languages
- Progressive web app
