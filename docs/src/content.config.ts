import { defineCollection } from 'astro:content';
import { docsLoader, i18nLoader } from '@astrojs/starlight/loaders';
import { docsSchema, i18nSchema } from '@astrojs/starlight/schema';
import { z } from 'astro/zod';

export const collections = {
	docs: defineCollection({ loader: docsLoader(), schema: docsSchema() }),
	i18n: defineCollection({
		loader: i18nLoader(),
		schema: i18nSchema({
			extend: z.object({
				'pk.nav.label': z.string(),
				'pk.nav.docs': z.string(),
				'pk.nav.guides': z.string(),
				'pk.nav.reference': z.string(),
				'pk.version.title': z.string(),
				'pk.github.label': z.string(),
				'pk.lang.label': z.string(),
				'pk.lang.en': z.string(),
				'pk.lang.fr': z.string(),
				'pk.lang.enShort': z.string(),
				'pk.lang.frShort': z.string(),
				'pk.theme.toDark': z.string(),
				'pk.theme.toLight': z.string(),
				'pk.breadcrumb.label': z.string(),
				'pk.copy.label': z.string(),
				'pk.copy.copied': z.string(),
				'pk.toc.title': z.string(),
				'pk.toc.edit': z.string(),
				'pk.toc.issue': z.string(),
				'pk.install.name': z.string(),
				'pk.install.requirements': z.string(),
				'pk.install.copy': z.string(),
				'pk.install.copied': z.string(),
				'pk.footer.before': z.string(),
				'pk.footer.after': z.string(),
				'pk.search.button': z.string(),
				'pk.search.label': z.string(),
				'pk.search.placeholder': z.string(),
				'pk.search.close': z.string(),
				'pk.search.results': z.string(),
				'pk.search.loading': z.string(),
				'pk.search.error': z.string(),
				'pk.search.empty': z.string(),
				'pk.search.move': z.string(),
				'pk.search.open': z.string(),
				'pk.search.dismiss': z.string(),
				'pk.search.esc': z.string(),
				'pk.search.enter': z.string(),
				'pk.search.ctrl': z.string(),
			}),
		}),
	}),
};
