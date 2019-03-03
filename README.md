# Misuzu
[![StyleCI](https://styleci.io/repos/114177358/shield)](https://styleci.io/repos/114177358)
[![CodeFactor](https://www.codefactor.io/repository/github/flashwave/misuzu/badge)](https://www.codefactor.io/repository/github/flashwave/misuzu)
[![License](https://img.shields.io/github/license/flashwave/misuzu.svg)](https://github.com/flashwave/misuzu/blob/master/LICENSE)

## Requirements
 - PHP 7.2
 - MySQL 8.0
 - Redis
 - [Composer](https://getcomposer.org/)
 - [node.js](https://nodejs.org/) (for the typescript and less compilers)
 - [Yarn](https://yarnpkg.com/)

## Additional Configuration

Make sure to set the GLOBAL MySQL variable `log_bin_trust_function_creators` to `ON` so the migration script can create stored procedures. I can't automate this because said variable is not changeable at a session scope and only root can touch global variables.
