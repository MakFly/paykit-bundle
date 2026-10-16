# Contributing

Thanks for helping! Bug reports, docs fixes and pull requests are welcome.

## Setup

```bash
git clone <your fork> && cd paykit-symfony
composer install
vendor/bin/phpunit             # unit + integration (SQLite in memory) + functional (kernel boot)
vendor/bin/phpstan analyse     # level 8
```

No Stripe account is needed: the tests use `PayKit\Bundle\Testing\FakeProvider` and a `MockClock`.

## Guidelines

- Target PHP 8.2 and Symfony 7.1+. Use `declare(strict_types=1)` and final classes by default.
- Keep billing rules in `Service/`, persistence in `Repository/BillingRepository`, and provider specifics behind `PaymentProviderInterface`.
- If you change an entity, update `migrations/` (add a new migration class). `MigrationTest` fails when the mapping and the migrations diverge.
- Every behavior change needs a test. Lifecycle scenarios go in `tests/Integration/SubscriptionLifecycleTest.php`.
- Keep the documentation in `docs/src/content/docs/` (especially the PayKit mapping page) and `README.md` in sync with user-facing changes, and add a line to `CHANGELOG.md`.
- Upstream PayKit is MIT-licensed. Port ideas, do not copy code verbatim.

## Commit messages

Use conventional commits (`feat:`, `fix:`, `docs:`, `test:`, `chore:`).

## Security

Please report vulnerabilities privately to the maintainers rather than opening a public issue.
