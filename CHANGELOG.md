# Changelog

## 0.1.0 - unreleased

- Initial Symfony port of PayKit: YAML catalog, versioned plan sync (`paykit:push`, `paykit:status`),
  subscription lifecycle (checkout, upgrade, scheduled downgrade, cancel-to-free, resume),
  entitlements and lazy-reset usage metering, Stripe adapter, idempotent webhooks, Symfony events,
  `#[RequiresFeature]`, `PAYKIT_FEATURE` voter, Twig functions, JSON API, fake provider.
