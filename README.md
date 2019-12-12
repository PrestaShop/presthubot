# PrestHubot

## Installation

* Install dependancies
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
| --ghtoken=<ghtoken>  | Yes/No | Use it or use .env |
| --request=<request>  | No | Use the title of a search request or create your specific request |
| --filter:file=<ext>  | No | Filter on extensions file (one or many separated by comma) |

### Check Module
Permits to check PrestaShop Modules

```bash
php bin/console github:check:module
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| --ghtoken=<ghtoken>  | Yes/No | Use it or use .env |
| --module=<module>  | No | Filter only one module |


#### Informations
| Column | | Notes|
| ------------- | ------------- | ------------- |
| Files  | .travis.yml | Check if .travis.yml is present<br>Check if `before_deploy` & `deploy` are present in the file |

### Check Repositories
Permits to check PrestaShop Repositories

```bash
php bin/console github:check:repository
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| --ghtoken=<ghtoken>  | Yes/No | Use it or use .env |
| --public  | No | Filter only on public repositories |
| --private  | No | Filter only on private repositories |

### Get Stats
Get some stats

```bash
php bin/console github:stats
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| --ghtoken=<ghtoken>  | Yes/No | Use it or use .env |
| --username=<ghusername>  | Yes/No | Use it or use .env |

### Get Stats from repository
Get some stats

```bash
php bin/console github:stats:repository
```

#### Parameters
| Parameter | Required | Notes |
| ------------- | ------------- | ------------- |
| --ghtoken=<ghtoken>  | Yes/No | Use it or use .env |
| --repository=<repository>  | No | Just a repository in PS Organization or all the org if it is not defined |
| --pr:date:created=<date>  | No | Filter on creation date for PR |
| --pr:date:merged=<date>  | No | Filter on merge date for PR |