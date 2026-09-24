import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { ChevronRight } from 'lucide-react';
import { ProductDetails } from '@/components/store/ProductDetails';
import { getMenu, getStorefront, storeUrl } from '@/lib/storefront';

async function load(tenant: string, slug: string) {
  const store = await getStorefront(tenant);
  const menu = store?.branches[0] ? await getMenu(tenant, store.branches[0].slug) : null;
  const product = menu?.products.find((p) => p.slug === decodeURIComponent(slug));

  return store && menu && product ? { store, menu, product } : null;
}

export async function generateMetadata({ params }: PageProps<'/s/[tenant]/p/[slug]'>): Promise<Metadata> {
  const { tenant, slug } = await params;
  const data = await load(tenant, slug);
  if (!data) return {};
  const { product } = data;
  const description = product.description ?? `${product.name} در منوی ${data.store.name}`;

  return {
    title: product.name,
    description,
    alternates: { canonical: storeUrl(tenant, `/p/${encodeURIComponent(product.slug)}`) },
    openGraph: { title: product.name, description, ...(product.images[0] ? { images: [{ url: product.images[0].url }] } : {}) },
  };
}

/** A product as its own page: shareable, indexable, and the same option picker as the menu sheet. */
export default async function ProductPage({ params }: PageProps<'/s/[tenant]/p/[slug]'>) {
  const { tenant, slug } = await params;
  const data = await load(tenant, slug);
  if (!data) notFound();
  const { store, menu, product } = data;
  const category = menu.categories.find((c) => product.category_ids.includes(c.id));

  const jsonLd = [
    {
      '@context': 'https://schema.org',
      '@type': 'Product',
      name: product.name,
      ...(product.description ? { description: product.description } : {}),
      ...(product.images.length ? { image: product.images.map((i) => i.url) } : {}),
      brand: { '@type': 'Brand', name: store.name },
      offers: {
        '@type': 'AggregateOffer',
        priceCurrency: 'IRR',
        lowPrice: Math.min(...product.variants.map((v) => v.price)),
        highPrice: Math.max(...product.variants.map((v) => v.price)),
        offerCount: product.variants.length,
        availability: product.is_available ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
      },
    },
    {
      '@context': 'https://schema.org',
      '@type': 'BreadcrumbList',
      itemListElement: [
        { '@type': 'ListItem', position: 1, name: store.name, item: storeUrl(tenant) },
        ...(category ? [{ '@type': 'ListItem', position: 2, name: category.name, item: storeUrl(tenant, `#cat-${category.id}`) }] : []),
        { '@type': 'ListItem', position: category ? 3 : 2, name: product.name },
      ],
    },
  ];

  return (
    <div className="mx-auto max-w-xl pt-4">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd).replace(/</g, '\u003c') }} />
      <nav aria-label="مسیر" className="mb-3 text-sm text-text-muted">
        <Link href={`/s/${tenant}${category ? `#cat-${category.id}` : ''}`} className="inline-flex items-center gap-1 hover:text-text">
          <ChevronRight className="size-4" aria-hidden="true" />{category?.name ?? 'منو'}
        </Link>
      </nav>
      <article className="rounded-3xl border border-border bg-surface px-5 pt-5 pb-4 shadow-[var(--shadow-sm)]">
        <h1 className="mb-4 text-2xl font-bold">{product.name}</h1>
        <ProductDetails product={product} branchId={menu.branch.id} />
      </article>
    </div>
  );
}
