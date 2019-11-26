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
php bin/console github:check:pr --ghtoken=<ghtoken>
```

### Check Module
Permits to check PrestaShop Modules

```bash
php bin/console github:check:module --ghtoken=<ghtoken> --module=<module>
```

#### Informations
| Column | | Notes|
| ------------- | ------------- | ------------- |
| Files  | .travis.yml | Check if .travis.yml is present<br>Check if `before_deploy` & `deploy` are present in the file |

### Check Repositories
Permits to check PrestaShop Repositories

```bash
php bin/console github:check:repository --ghtoken=<ghtoken>
```

### Get Stats
Get Stats

```bash
php bin/console github:stats --ghtoken=<ghtoken> --username=<ghusername> 
```