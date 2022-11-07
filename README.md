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
