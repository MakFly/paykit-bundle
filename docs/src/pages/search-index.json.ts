import type { APIRoute } from 'astro';
import { getCollection, render } from 'astro:content';

/**
 * Search index for the command palette: one record per page and one per h2/h3 section.
 * Built from the content collection, so it works in `astro dev` and in the static build
 * without a Pagefind indexing step.
 */
export const prerender = true;

type SearchRecord = {
	title: string;
	section?: string;
	group: string;
	href: string;
	text: string;
	lang: string;
};

const groupNames: Record<'en' | 'fr', Record<string, string>> = {
	en: {
		'getting-started': 'Getting started',
		guides: 'Guides',
		reference: 'Reference',
	},
	fr: {
		'getting-started': 'Prise en main',
		guides: 'Guides',
		reference: 'Référence',
	},
};

/** `index` is the English home. `fr` and `fr/index` are the French home. */
const pageSlug = (id: string) => (id === 'index' ? '' : id.endsWith('/index') ? id.slice(0, -'/index'.length) : id);

const pageHref = (id: string) => {
	const slug = pageSlug(id);
	return slug ? `/${slug}/` : '/';
};

const pageLang = (id: string): 'en' | 'fr' => (id === 'fr' || id.startsWith('fr/') ? 'fr' : 'en');

const pageGroup = (id: string, lang: 'en' | 'fr') => {
	const parts = pageSlug(id).split('/').filter(Boolean);
	if (parts[0] === 'fr') parts.shift();
	return groupNames[lang][parts[0] ?? ''] ?? (lang === 'fr' ? 'Documentation' : 'Docs');
};

/** Markdown and MDX to plain text: drops code fences, imports, components and link syntax. */
const plain = (markdown: string) =>
	markdown
		.replace(/```[\s\S]*?```/g, ' ')
		.replace(/^import .*$/gm, ' ')
		.replace(/<[^>]+>/g, ' ')
		.replace(/!?\[([^\]]*)\]\([^)]*\)/g, '$1')
		.replace(/^\s*(?:[-*+]|\d+\.)\s+/gm, ' ')
		.replace(/[`*_>|#]/g, ' ')
		.replace(/\s+/g, ' ')
		.trim();

export const GET: APIRoute = async () => {
	const entries = await getCollection('docs');
	const records: SearchRecord[] = [];

	for (const entry of entries) {
		// The landing pages repeat the docs; search lands on the docs themselves.
		if (entry.data.template === 'splash') continue;
		const lang = pageLang(entry.id);
		const href = pageHref(entry.id);
		const group = pageGroup(entry.id, lang);
		const { headings } = await render(entry);
		const sections = headings.filter((h) => h.depth === 2 || h.depth === 3);

		// Split the body on h2/h3 lines outside code fences; the n-th split is the n-th heading.
		const chunks: string[] = [''];
		let inFence = false;
		for (const line of (entry.body ?? '').split('\n')) {
			if (line.trimStart().startsWith('```')) inFence = !inFence;
			if (!inFence && /^#{2,3}\s/.test(line)) chunks.push('');
			else chunks[chunks.length - 1] += line + '\n';
		}

		records.push({
			title: entry.data.title,
			group,
			href,
			lang,
			text: plain(`${entry.data.description ?? ''} ${chunks[0]}`),
		});
		sections.forEach((heading, i) => {
			records.push({
				title: entry.data.title,
				section: heading.text,
				group,
				href: `${href}#${heading.slug}`,
				lang,
				text: plain(chunks[i + 1] ?? ''),
			});
		});
	}

	return new Response(JSON.stringify(records), {
		headers: { 'Content-Type': 'application/json; charset=utf-8' },
	});
};
