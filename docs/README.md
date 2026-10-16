# PayKit Bundle documentation

The documentation site of `makfly/paykit-bundle`, built with
[Astro Starlight](https://starlight.astro.build). Pages live in
`src/content/docs/` as Markdown or MDX.

```bash
bun install
bun run dev      # local preview on http://127.0.0.1:4387 (or `make docs` from the root)
bun run check    # type and content checks
bun run build    # static site in dist/
```

This folder is excluded from the Composer package by `.gitattributes`.
