// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';

// https://astro.build/config
export default defineConfig({
	// 4321 is Astro's default and often taken by another project on the machine.
	server: { host: '127.0.0.1', port: 4387 },
	integrations: [
		starlight({
			title: 'PayKit Bundle',
			description:
				'Stripe billing for Symfony: plans in config, feature gating, usage metering and webhooks.',
			logo: { src: './src/assets/logo.svg', alt: 'PayKit' },
			favicon: '/favicon.svg',
			head: [
				{ tag: 'link', attrs: { rel: 'icon', href: '/favicon.ico', sizes: 'any' } },
				{ tag: 'link', attrs: { rel: 'apple-touch-icon', href: '/apple-touch-icon.png' } },
			],
			// Search runs on /search-index.json (src/pages), which also works in dev.
			pagefind: false,
			defaultLocale: 'root',
			locales: {
				root: { label: 'English', lang: 'en' },
				fr: { label: 'Français', lang: 'fr' },
			},
			social: [
				{ icon: 'github', label: 'GitHub', href: 'https://github.com/MakFly/paykit-bundle' },
			],
			editLink: { baseUrl: 'https://github.com/MakFly/paykit-bundle/edit/main/docs/' },
			customCss: [
				'@fontsource-variable/geist',
				'@fontsource-variable/geist-mono',
				'./src/styles/theme.css',
			],
			expressiveCode: {
				themes: ['github-light', 'github-dark'],
				styleOverrides: {
					borderRadius: '0.5rem',
					codeFontFamily: "'Geist Mono Variable', ui-monospace, monospace",
					codeFontSize: '0.8125rem',
					codeLineHeight: '1.7',
					uiFontFamily: "'Geist Variable', ui-sans-serif, system-ui, sans-serif",
					frames: { shadowColor: 'transparent', editorActiveTabIndicatorTopColor: 'transparent' },
				},
			},
			components: {
				Header: './src/components/Header.astro',
				PageFrame: './src/components/PageFrame.astro',
				TwoColumnContent: './src/components/TwoColumnContent.astro',
				PageTitle: './src/components/PageTitle.astro',
				PageSidebar: './src/components/PageSidebar.astro',
				Sidebar: './src/components/Sidebar.astro',
				Footer: './src/components/Footer.astro',
				ThemeSelect: './src/components/ThemeSelect.astro',
				Search: './src/components/Search.astro',
			},
			sidebar: [
				{ label: 'Introduction', slug: 'introduction', translations: { fr: 'Introduction' } },
				{
					label: 'Getting started',
					translations: { fr: 'Prise en main' },
					items: [{ autogenerate: { directory: 'getting-started' } }],
				},
				{
					label: 'Guides',
					translations: { fr: 'Guides' },
					items: [{ autogenerate: { directory: 'guides' } }],
				},
				{
					label: 'Reference',
					translations: { fr: 'Référence' },
					items: [{ autogenerate: { directory: 'reference' } }],
				},
			],
		}),
	],
});
