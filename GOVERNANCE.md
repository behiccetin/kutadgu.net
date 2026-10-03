# Governance

Kutadgu is run by a board, not by a person. This document summarises how decisions are made, who maintains the software and what happens if the people involved today are no longer able to carry it. The binding texts are the declaration (`/bildiri.php`) and the board page (`/kurul.php`) on the running system; where this summary and those texts differ, those texts prevail.

[Türkçe aşağıda](#türkçe)

## The founding board

Five researchers from four universities founded the system and form its founding board of chief editors:

| Name | Affiliation | Role in founding |
|---|---|---|
| Dr. Behiç Çetin | Burdur Mehmet Akif Ersoy University | Designed and develops the software, operates the infrastructure |
| Prof. Dr. Murat Kayalar | Burdur Mehmet Akif Ersoy University | Publishing principles, review criteria |
| Assoc. Prof. Dr. Gökhan Kalağan | Balıkesir University | Publishing principles, review criteria |
| Prof. Dr. Mustafa Zihni Tunca | Süleyman Demirel University | Publishing principles, review criteria |
| Prof. Dr. İbrahim Atilla Acar | İzmir Kâtip Çelebi University | Publishing principles, review criteria |

All five are joint creators of the software release on Zenodo; the division above is a division of labour, not a hierarchy.

## Two different things: an office and a record

- **Chief editor in office** is an authority: assigning referees, adding editors, writing editorial notes, voting. It can be handed over and it ends by a written route (own handover, death, a majority decision of the founders, a written end date, or for appointed chief editors an unmet activity criterion at the end of a one-year term).
- **Founding chief editor** is a record of who started the system. It carries no authority, no veto and no priority, and it cannot be granted to anyone later, least of all for money.

## How decisions are made

- **Votes are equal.** No founder's vote outweighs another's.
- **Money and resources are board decisions.** The annual budget, items of expenditure, support to be accepted and **applications for funding** are voted on by the chief editors in office.
- **Disputes between author and referee** are not decided by one person; they go to a recorded board vote, and the votes and reasons are published with the work.
- **Board decisions are permanent.** Results, votes and reasons are never deleted or altered, by anyone, the founders included.
- **Payments are public.** If anyone, including a founder, is paid for hosting, maintenance or translation, the item and its size are published on the statistics page. No income may ever arise from restricting access.

## Principles that cannot be changed

The declaration and the draft statute fix a set of principles that no vote and no setting can narrow. They are enforced in code as well as in text (`tg_ayar_ilke_suz` filters `ayar.php` at load time, and a gate stops a board vote whose subject would narrow one of them). Among them:

1. No fee from authors, readers or institutions, under any name.
2. The archive can always be downloaded in full, without login or approval.
3. Referee reports are never deleted.
4. Published works stay under an open licence (CC BY 4.0).
5. No advertising.
6. The system may not be sold, in whole or in part.
7. The declaration and the names of the founding board may not be removed.

## Maintainers

| Area | Maintainer today |
|---|---|
| Software and releases | Behiç Çetin |
| Infrastructure (reference instance) | Behiç Çetin, on a volunteer basis |
| Editorial process | Chief editors in office |

Today one person maintains the software. That is the main continuity risk of the project and we say so plainly. The steps under way to reduce it are on the [roadmap](ROADMAP.md): the test suite (now in the repository) running automatically on every change, an English developer guide and glossary for the Turkish identifiers, reproducible packaging, a second instance run by a different organisation, and a second maintainer with commit rights.

## Continuity and handover

- The archive is downloadable by anyone at any time, so the record does not depend on one server.
- If no sign is received from the founding chief editors for six months, the duty of sustaining the system passes to an institution that has accepted the declaration, or failing that to a preservation service that accepts the archive.
- The system may be handed over to a public or private institution only if that institution accepts every condition of the declaration. A receiving institution exercises editorial authority but cannot change the principles above.

---

## Türkçe

Kutadgu bir kişi tarafından değil, bir kurul tarafından yönetilir. Bağlayıcı metinler canlı sistemdeki bildiri (`/bildiri.php`) ve kurul sayfasıdır (`/kurul.php`); bu özet ile o metinler ayrışırsa o metinler geçerlidir.

- **Kurucu kurul** dört üniversiteden beş araştırmacıdır. Kurucu sıfatı bir kayıttır, yetki taşımaz; görevdeki baş editörlük ise bir yetkidir ve yazılı bir yolla sona erer.
- **Oylar eşittir.** Bütçe, harcama, kabul edilecek destekler ve **fon başvuruları** görevdeki baş editörlerin oyuyla karara bağlanır.
- **Kurul kararları kalıcıdır**; kurucular dahil kimse geri alamaz.
- **Ödemeler açıktır.** Kurucular dahil kime ne ödendiyse istatistik sayfasında yazılır. Hiçbir gelir erişimi kısıtlamaktan doğamaz.
- **Bugün yazılımı tek kişi sürdürüyor.** Bu, projenin en büyük süreklilik riskidir; azaltma adımları yol haritasındadır.
- **Devir** yalnızca bildirinin bütün koşullarını kabul eden bir kuruma yapılabilir.
