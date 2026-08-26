# PrestHubot

## Installation

* Install dependencies
```bash
composer install
```
* Fetch a Github Token : https://github.com/settings/tokens/new?description=PrestHubot&scopes=repo,read:org

## Configuration

You can using parameters :
* `--ghtoken=<ghtoken>`
* `--username=<ghusername> `

**OR**

You can use .env file :
* Copy .env.dist to .env
* Define your token & username from Github
```
GH_TOKEN=<ghtoken>
GH_USERNAME=<ghusername>
```

## Usage

### Check PR
Permits to check PrestaShop PR (and their status)

```bash
php bin/console github:check:pr
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| `--ghtoken=<ghtoken>` | Yes/No | Use it or use .env |
| `--request=<request>` | No | Use the title of a search request or create your specific request |
| `--exclude:author=<ghusername>` | No | Exclude for displaying PR created by <ghusername> |
| `--exclude:reviewer=<ghusername>` | No | Exclude for displaying PR approved by <ghusername> |
| `--filter:file=<ext>` | No | Filter on extensions file (one or many separated by comma) |
| `--filter:numapproved=<ext>` | No | Filter on number of approved reviews (one or many separated by comma) |
| `--orderBy=<order>` | No | Order by columns (one or many separated by comma : projectName, id, createdAt) |

#### Samples

* Fetch PR to review with 1 or 2 approved reviews, excluding Progi1984's PR and PR that Progi1984 has approved

`php bin/console github:check:pr --request "PR Waiting for Review" --filter:numapproved=2,1 --exclude:reviewer=Progi1984 --exclude:author=Progi1984`

### Check Module
Permits to check PrestaShop Modules

```bash
php bin/console github:check:module
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| `--ghtoken=<ghtoken>` | Yes/No | Use it or use .env |
| `--limit=<offset,length>` | Yes/No | Limit search (from offset and length of all repositories) |
| `--module=<module>` | No | Filter only one module |
| `--branch=<branch>` | No | Branch for searching files (default: master) |


#### Informations
| Column | | Notes|
| ------------- | ------------- | ------------- |
| Files | .travis.yml | Check if .travis.yml is present<br>Check if `before_deploy` & `deploy` are present in the file |

### Check Repositories
Permits to check PrestaShop Repositories

```bash
php bin/console github:check:repository
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| `--ghtoken=<ghtoken>` | Yes/No | Use it or use .env |
| `--public` | No | Filter only on public repositories |
| `--private` | No | Filter only on private repositories |

### Get Notifications
Get Github Notifications

```bash
php bin/console github:notifications
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| `--ghtoken=<ghtoken>` | Yes/No | Use it or use .env |

### Get Stats
Get some stats

```bash
php bin/console github:stats
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| `--ghtoken=<ghtoken>` | Yes/No | Use it or use .env |
| `--username=<ghusername>` | Yes/No | Use it or use .env |

### Get Stats from repository
Get some stats

```bash
php bin/console github:stats:repository
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| `--ghtoken=<ghtoken>` | Yes/No | Use it or use .env |
| `--repository=<repository>` | No | Just a repository in PS Organization or all the org if it is not defined |
| `--pr:date:created=<date>` | No | Filter on creation date for PR |
| `--pr:date:merged=<date>` | No | Filter on merge date for PR |

### Generate Issues Report
Permits to track all issues created in the last month (4 weeks) for the PrestaShop project

```bash
php bin/console github:issues:report
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| `--ghtoken=<ghtoken>` | Yes/No | Use it or use .env |
| `--dateStart=<dateStart>` | Yes | Format date: `yyyy-mm-dd` |
| `--dateEnd=<dateEnd>` | No | Format date: `yyyy-mm-dd`)` (default: dateStart + 28 days) |
| `--outputDir=<outputDir>` | No | Output directory (default: `var/report`) |

### Generate Reviewers Report
Permits to track all reviews

```bash
php bin/console github:review:report
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| `--ghtoken=<ghtoken>` | Yes/No | Use it or use .env |
| `--dateStart=<dateStart>` | Yes | Format date: `yyyy-mm-dd` |
| `--dateEnd=<dateEnd>` | No | Format date: `yyyy-mm-dd`)` (default: today) |
| `--byDate=<0|1>` | No | Report by date or author |

### Export contributors

```bash
php bin/console github:contributors:export --contributorsFile contributors.csv --outputFile output.csv
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| `--ghtoken=<ghtoken>` | Yes/No | Use it or use .env |
| `--contributorsFile=<contributorsFile>` | Yes | Contributors file |
| `--outputFile=<outputFile>` | Yes | Output file |


### Export contributors Stats

```bash
php bin/console github:contributors:stats --contributorsFile contributors.csv --outputFile output.csv
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| `--ghtoken=<ghtoken>` | Yes/No | Use it or use .env |
| `--contributorsFile=<contributorsFile>` | Yes | Contributors file |
| `--outputFile=<outputFile>` | Yes | Output file |

### Slack Notifier for Core Team
Notify on Slack :
- Nightly Build Status
- If PR are needed to merge
- PR to review
- Modules releases
- Modules improvements

```bash
php bin/console slack:notifier
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| `--ghtoken=<ghtoken>` | Yes/No | Use it or use .env |
| `--slacktoken=<slacktoken>` | Yes/No | Use it or use .env |
| `--slackchannel=<slackchannel>` | Yes/No | Use it or use .env |

### Weekly Triage Agent
Pre-qualify the issues and pull requests touched in the past week so the sheriff
reads a ranked list instead of the whole tracker, and post it to Slack.

Each item is sent to Claude with a rubric and comes back with a proposal:
- issues get a severity (`Critical` / `Major` / `Minor` / `Trivial`), a suggested
  next step (`TBR` / `NMI` / `Needs Specs` / ready), the area concerned, possible
  duplicates picked from a keyword shortlist, and flags for a possible security
  report or a regression
- pull requests get an attention level and, above all, **who they are actually
  waiting on** - read from the timeline rather than from labels, which are often
  stale

**It proposes and never decides.** Nothing is written back to GitHub: no label,
no board card, no comment. A human accepts, corrects or ignores every line.

```bash
php bin/console github:triage:weekly --dry-run
```

Runs every Monday from `.github/workflows/triageagent.yml`.

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| `--ghtoken=<ghtoken>` | Yes/No | Use it or use .env |
| `--anthropic-token=<token>` | Yes/No | Use it or use .env (`ANTHROPIC_API_KEY`) |
| `--slacktoken=<slacktoken>` | Yes/No | Use it or use .env |
| `--slackchannel=<slackchannel>` | Yes/No | Use it or use .env (defaults to `SLACK_CHANNEL_CORE`) |
| `--since=<YYYY-MM-DD>` | No | Window start. Defaults to 7 days ago |
| `--until=<YYYY-MM-DD>` | No | Window end. Defaults to today. Pass both to reproduce a past week exactly |
| `--limit=<n>` | No | Classify only the first n issues and n pull requests, so a short run still exercises both rubrics |
| `--dry-run` | No | Render everything but do not post it to Slack |
| `--report=<path>` | No | Where to write the full report |
| `--run-url=<url>` | No | Link the Slack digest back to this run. The workflow fills it in |

The workflow exposes the same two controls on `workflow_dispatch`: a `dry-run`
toggle and a `channel` override.

Two outputs, on purpose. The **Slack message is a digest**: the attention list,
any proposed Critical, the blocking pull requests, a census of the rest, and a
link back. Nothing is repeated between those blocks — an item named in the
attention list is skipped further down. Slack collapses anything much past four
thousand characters behind a "See more", so listing every Minor there would hide
the part that matters.

The **full report** — every item, every rationale, plus the branch check,
possible duplicates and the list of what was skipped and why — goes to the job
summary and an artifact, and is what the digest links to.

#### The rubric

The prompts live in `src/App/Resources/triage/` and are the substance of this
command:

- `severity_system.md` reproduces the project's [severity
  classification](https://build.prestashop-project.org/news/2019/severity-classification/)
  verbatim, then adds what that page leaves open: how to read its
  "percentage of users" thresholds for a shop platform, the clauses that
  override the count (security, data loss, broken E2E, law compliance, money),
  and how to judge whether a workaround is obvious.
- It also encodes the boundary from [how issues are
  sorted](https://www.prestashop-project.org/get-involved/report-issues/how-issues-are-sorted/):
  **severity is proposed, priority never is.** Priority belongs to the
  Development / Product Management / QA meeting. What the agent can usefully
  hand that meeting is whether a bug is a regression, kept independent of
  severity - a trivial regression is still Trivial.
- `severity_examples.md` holds 24 worked examples, 6 per level, mined from
  closed issues that maintainers labelled themselves.
- `pr_triage_system.md` covers pull requests, where severity does not apply.

The rubric plus its examples is around 8 000 tokens and is identical for every
item in a run, so it is sent as a cached block: the first item pays for it and
the rest read it back at a tenth of the price. The command prints the measured
token usage and cost at the end of every run, and warns if the cache is being
invalidated.

#### Calibration

`github:triage:calibrate` answers the only question that matters about the
rubric: **how far does it agree with the maintainers?** Without that number the
weekly report is confident verdicts nobody can check.

```bash
php bin/console github:triage:calibrate            # score the held-out set
php bin/console github:triage:calibrate --mine     # regenerate the worked examples
php bin/console github:triage:calibrate --limit=20 # smoke test, 5 of each class
```

Manual only, from `.github/workflows/triagecalibrate.yml`. It is not a recurring
job — run it when the rubric changes. The split seed is fixed, so two runs are
directly comparable and this doubles as a regression test on the prompt.

There is no fine-tuning. The ~2 300 closed issues carrying exactly one severity
label are split once, deterministically and stratified by class, into a pool the
worked examples are mined from and a held-out set that is scored and never
appears in a prompt. Mining from the held-out half would hand over the answers.

Read the confusion matrix, not the headline percentage. The corpus is heavily
imbalanced (68 Critical against 1 378 Minor), so plain accuracy looks good while
saying nothing. The numbers that matter are **Critical recall** — a Critical
proposed as Minor is an issue the sheriff never sees ranked — and the
**off-by-one rate**, since a Major proposed as Critical costs one glance and
nothing more.

The held-out set is scored with sequential calls rather than the Batch API.
Batching would halve the cost, but it targets completion within 24 hours while a
GitHub-hosted job is cut off at six; a few dollars is a poor trade for a run that
can vanish. Expect roughly 80 minutes and $7 for the full 334 issues.

| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| `--ghtoken=<ghtoken>` | Yes/No | Use it or use .env |
| `--anthropic-token=<token>` | Yes/No | Use it or use .env. Not needed with `--mine` |
| `--mine` | No | Regenerate `severity_examples.md` from the pool and stop |
| `--refresh` | No | Refetch the corpus instead of using the cached copy |
| `--limit=<n>` | No | Score only the first n held-out issues. The set is interleaved, so any prefix stays balanced across the four classes |
| `--report=<path>` | No | Where to write the scored report |

#### Known limits

- The weekly window cannot surface a long-stalled PR: selecting on recent
  updates means one nobody has touched in months is invisible *because* nobody
  touched it. This covers what moved, not what is rotting.
- Duplicate detection can only pick from a keyword shortlist, so it cannot
  invent an issue number and cannot find a duplicate that shares no title
  keywords.
- PR triage reads metadata, not diffs. It can say a PR waited a month; it cannot
  say whether the change is any good.
- Severity is proposed from the report, not from a reproduction. An overstated
  report and a genuine Critical read alike on paper - that is what `TBR` and the
  confidence field are for.

### Release Note helper
Generate the list of resolved issues in a milestone

```bash
php bin/console github:release:note 
```

#### Parameters
| Type                            | Name       | Required | Notes                                | Default    |
|---------------------------------|------------|----------|--------------------------------------|------------|
| Argument                        | milestone  | No       | milestone selected for search        | none       |
| Option                          | output     | No       | output between 'table' or 'markdown' | table      |
| Option                          | repository | No       | repository to explore                | PrestaShop |

Note that if the milestone is not defined, It will be asked to the user.
#### Example
```bash
php bin/console github:release:note 1.7.8.2 --repository=PrestaShop --output=table
or
php bin/console github:release:note 1.7.8.2 --repository=PrestaShop
or
php bin/console github:release:note 1.7.8.2 --output=table
or 
php bin/console github:release:note 1.7.8.2
```  
```
provide:
```bash
├─────────────────┼───────────────────────────────────────────────────────┼───────────────────────────────────────────────────────┤
│  Issues involved in the milestone 1.7.8.2 (3)                                                                                   │
├─────────────────┼───────────────────────────────────────────────────────┼───────────────────────────────────────────────────────┤
│ Issue N°        │ url                                                   │ Title                                                 │
│ 26623           │ https://github.com/PrestaShop/PrestaShop/issues/26623 │ Release 1.7.8.2                                       │
│ 26327           │ https://github.com/PrestaShop/PrestaShop/issues/26327 │ Wishlist button disappears while using Faceted Search │
│ 23394           │ https://github.com/PrestaShop/PrestaShop/issues/23394 │ Missing Alias CustomerQueryBuilder.php                │
└─────────────────┴───────────────────────────────────────────────────────┴───────────────────────────────────────────────────────┘

Output generated in 0s for 3 rows.
```

and


```bash
php bin/console github:release:note 1.7.8.2 --output=markdown
or
php bin/console github:release:note 1.7.8.2 --repository=PrestaShop --output=markdown
``` 

provide:
```bash
Issues involved in the milestone 1.7.8.2
========================================

- [Release 1.7.8.2](https://github.com/PrestaShop/PrestaShop/issues/26623)
- [Wishlist button disappears while using Faceted Search](https://github.com/PrestaShop/PrestaShop/issues/26327)
- [Missing Alias CustomerQueryBuilder.php](https://github.com/PrestaShop/PrestaShop/issues/23394)

Output generated in 0s for 3 rows.
```

### CQRS Endpoints
Get Commands and Queries developed as CQRS endpoints

```bash
php bin/console github:cqrs:endpoints
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| `--ghtoken=<ghtoken>` | Yes/No | Use it or use .env |
