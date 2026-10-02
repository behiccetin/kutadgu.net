# Roadmap

This roadmap covers the next twelve months. It is the work plan submitted to the NLnet Foundation's *Open Internet Stack: Restack* call (NGI); if that application is not funded, the order stays the same and the pace follows volunteer time.

Each milestone ends in something that can be checked from outside: a tagged release, a public document, or a running second instance.

## M1. Reproducible packaging and installation

- Container image and a plain-PHP installer that produce a working instance from an empty server
- Separate the instance configuration from the code (`ayar.php` split into shipped defaults and a local override), so upgrades never overwrite a site's settings
- Versioned releases with tags, release notes and an upgrade/migration path between versions
- Installation guide in English and Turkish

## M2. Test suite and maintainability

- Move the existing test scripts into the repository and run them in continuous integration on every change
- English developer guide and glossary for the Turkish identifiers (`yazi`, `hakem`, `kurul`, `tamga` and so on), so that contributors who do not read Turkish can work on the code
- Refactor the largest files (`api/index.php`, `panel.php`, `ortak.php`) into modules along the lines the tests draw

## M3. Interoperability with scholarly infrastructure

- JATS XML export for every published work
- Metadata ready for Crossref and DataCite deposit; complete the optional per-work Zenodo DOI flow
- COAR Notify (Linked Data Notifications) so that reviews and endorsements can be exchanged with preprint servers and review services
- Register the reference instance with OpenAIRE, BASE and DOAJ

## M4. Federation and preservation

- A mirroring protocol between instances: signed archive dumps, fingerprint verification and scheduled pulls, so any institution can hold a verifiable copy of another instance's archive
- Off-site backup that is configured during installation, not afterwards

## M5. Internationalisation

- Move interface strings from inline `k_c('tr', 'en')` pairs to standard translation files so that translators can contribute without touching PHP
- Keep the rule that machine translation is labelled as such and never treated as a publication

## M6. A second, independent instance

- Install and run Kutadgu for a partner journal or library outside the founding group, with a European partner, and document what had to change
- Act on the findings of the NLnet security and accessibility audits

## Afterwards

- A second maintainer with commit rights
- ISSN and DOAJ for the reference instance
- Mirrors of the reference archive in at least two other countries
