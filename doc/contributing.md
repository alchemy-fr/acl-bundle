# Contributing

Bug reports, ideas and pull requests are welcome on
[GitHub](https://github.com/alchemy-fr/acl-bundle).

## Reporting a bug

Open an [issue](https://github.com/alchemy-fr/acl-bundle/issues) with the versions of the
bundle, PHP, Symfony and Doctrine, the database you use, and the smallest code that
reproduces the problem.

## Setting up the project

```bash
git clone git@github.com:alchemy-fr/acl-bundle.git
cd acl-bundle
composer install
```

## Running the tests

```bash
composer test
```

Without a local PHP 8.5, the same command runs in Docker:

```bash
docker run --rm -v "$PWD":/app -w /app php:8.5-cli vendor/bin/phpunit
```

The suite is made of unit tests: the Doctrine repository, the entity manager and the security
layer are replaced by mocks. Only `AccessControlEntryMappingTest` runs the entity mapping
against an in-memory SQLite database (it is skipped without `pdo_sqlite`).

The [CI](https://github.com/alchemy-fr/acl-bundle/actions/workflows/ci.yaml) runs the suite on
every pull request to `main`.

## Code style

```bash
composer cs      # php-cs-fixer
composer rector  # rector
```

Run both before opening a pull request.

## Pull requests

- Branch from `main` and keep one topic per pull request.
- Add or update the tests covering your change.
- Update the documentation in `doc/` when the behaviour or the configuration changes.
- Describe what changes for applications using the bundle, especially when it breaks
  backward compatibility.
