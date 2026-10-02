# Kutadgu

**Fee-free scholarly publishing with open, signed peer review. Self-hostable, no database, AGPL-3.0.**

Kutadgu is publishing software for journals, societies and research communities that want to publish without charging authors or readers, and that want the review process itself to be part of the public record. Every referee report is published under the referee's name, every editorial decision is published with its reasons, and the whole archive can be downloaded and mirrored by anyone.

The reference instance runs at **[kutadgu.net](https://kutadgu.net)**. The software is written so that any university, library or learned society can run its own instance on ordinary shared PHP hosting.

[Türkçe özet aşağıda](#türkçe)

---

## Why it exists

Most scholarly communities outside the large commercial publishers face the same choice: pay article processing charges, depend on a single centralised platform, or run heavyweight software that needs a database administrator. Review usually stays closed, so whether a decision was fair cannot even be argued.

Kutadgu takes a different set of defaults:

- **Nothing is charged, ever.** No processing fee, no subscription, no access fee, no advertising. These rules are not just policy text: `ayar.php` is filtered at load time (`tg_ayar_ilke_suz`) and any setting that would introduce a fee or gate the archive is neutralised and logged.
- **Review is open by default.** Reports carry the referee's name, decisions carry their reasoning, waiting times are measured and published, and disagreements between author and referee go to a recorded board vote.
- **The archive belongs to everyone.** The full archive is downloadable as one file with a SHA-256 fingerprint, harvestable over OAI-PMH 2.0, and published under CC BY 4.0. Nothing can be put behind a login.
- **Identifiers do not depend on anyone.** Each work receives a permanent *Tamga* (`KTG-YYYY-NNNNN-C`, with a check digit) that survives a change of domain or host. Optional per-work DOIs via Zenodo are in progress.
- **Easy to run, easy to copy.** PHP and flat JSON files outside the web root. No database, no build step, no package manager. Mirroring an instance is a file copy.

## Features

- Two publication tracks: peer reviewed, and non reviewed with endorsement by researchers
- Open, signed peer review with author replies, measured waiting times and public reviewer statistics
- Recorded board votes for disputes and for appointments, with reasoning visible
- Permanent internal identifiers (Tamga) with check digit and resolver
- OAI-PMH 2.0, full archive dump with fingerprint, sitemap, schema.org metadata
- ORCID sign-in and ownership verification
- Interface in Turkish and English (human reviewed) plus six machine-translated languages that are labelled as such on every page
- Explicit policy and declaration form for the use of generative tools by authors
- Progressive web app with offline page; accessibility work guided by WCAG 2.1
- Transparency pages: statistics, waiting times, open items ("what we do not yet meet")

## Status

Version **1.0.0** was released on 13 August 2026 ([10.5281/zenodo.21919023](https://doi.org/10.5281/zenodo.21919023)). The reference instance has been live since August 2026 and is at an early stage: the first works are in review and the founding board consists of five researchers from four universities. See [`CHANGELOG.md`](CHANGELOG.md) and [`ROADMAP.md`](ROADMAP.md).

## Running it

### Requirements

- PHP 8.0 or newer (developed on 8.4). Required extensions: `mbstring`, `json`, `dom`. Optional: `gd` (share cards), `zip` (archive package), `curl` (notifications, Zenodo), `intl` (date formatting).
- Apache 2.4 with `mod_rewrite` and `mod_headers` for production. The rules live in `.htaccess`.
- A writable data directory **outside** the web root.

### Local trial

```sh
mkdir -p ~/kutadgu-data
git clone https://github.com/behiccetin/kutadgu.net.git && cd kutadgu
KUTADGU_DATA=~/kutadgu-data php -S 127.0.0.1:8080 -t .
```

Open `http://127.0.0.1:8080/` (English by default, `?lang=tr` for Turkish). The built-in server does not apply `.htaccess`; see [`CONTRIBUTING.md`](CONTRIBUTING.md) for the local equivalents of rewritten addresses. Do not expose the built-in server beyond 127.0.0.1.

### Your own instance

1. Put the repository in the web root of an Apache virtual host.
2. Create a data directory outside the web root and point to it with `veri_dizini` in `ayar.php` or the `KUTADGU_DATA` environment variable.
3. Edit `ayar.php`, the single configuration file: `kok` (your address), `marka` (your name), `iletisim_eposta` (published in OAI-PMH `Identify`), `bas_editorler` (your founding board), and leave `varsayilan_yazar` and `ana_site` empty.
4. Schedule `yedek.sh` for nightly archive backups and copy them off site.

Secrets (bot tokens, Zenodo token, SMTP relay credentials) never go into the repository or `ayar.php`; they are written from the admin panel into the data directory.

Packaging (container image, installer, upgrade path) is the first item on the [roadmap](ROADMAP.md).

## Governance

Kutadgu is run by a founding board, not by an individual. Votes are equal, decisions and their reasons are public, and the conditions under which the system may be handed over to an institution are fixed in its declaration. See [`GOVERNANCE.md`](GOVERNANCE.md).

## Contributing, security, licence, citation

- How to contribute: [`CONTRIBUTING.md`](CONTRIBUTING.md) (Turkish and English)
- Reporting a vulnerability: [`SECURITY.md`](SECURITY.md)
- Software: **AGPL-3.0-only** ([`LICENSE`](LICENSE), explained in [`LISANS.md`](LISANS.md)). Published works: **CC BY 4.0**.
- How to cite: [`CITATION.cff`](CITATION.cff), concept DOI [10.5281/zenodo.21919023](https://doi.org/10.5281/zenodo.21919023)

The name "Kutadgu" and its mark may not be used as the name of a derived system, so that a reader can always tell from the name under which rules a work was published. Run your own instance under your own name.

---

## Türkçe

**Kutadgu**, yazardan ve okurdan hiçbir ücret almadan yayın yapmak isteyen dergiler, dernekler ve araştırma toplulukları için açık kaynaklı bir yayın yazılımıdır. Hakem raporları hakemin adıyla, editöryal kararlar gerekçesiyle yayımlanır; arşivin tamamı herkes tarafından indirilebilir ve çoğaltılabilir.

Örnek kurulum **[kutadgu.net](https://kutadgu.net)** adresinde çalışır. Yazılım, herhangi bir üniversitenin, kütüphanenin ya da derneğin sıradan bir PHP barındırma hizmetinde kendi kurulumunu çalıştırabileceği biçimde yazılmıştır: veritabanı, derleme adımı ya da paket yöneticisi gerekmez.

- Kurulum ve katkı: [`CONTRIBUTING.md`](CONTRIBUTING.md)
- Yönetim ve karar düzeni: [`GOVERNANCE.md`](GOVERNANCE.md)
- Yol haritası: [`ROADMAP.md`](ROADMAP.md)
- Lisans: yazılım AGPL-3.0-only, yayımlanan çalışmalar CC BY 4.0 ([`LISANS.md`](LISANS.md))
