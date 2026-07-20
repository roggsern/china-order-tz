import type { Metadata } from "next";
import Link from "next/link";
import Image from "next/image";
import { Breadcrumbs } from "@/components/catalog/Breadcrumbs";
import { getTzStores } from "@/lib/api/tz-stores";
import { StoreIcon } from "@/components/home/icons";

export const metadata: Metadata = {
  title: "Buy From TZ — Local Stores — CHINA ORDER TZ",
  description: "Shop ZION MODE, PEACHY LINGERIE, TZUR JEWELRY and ROVI BEAUTY — Tanzanian retail stores.",
};

export default async function BuyFromTzIndexPage() {
  let stores: Awaited<ReturnType<typeof getTzStores>> = [];
  try {
    stores = await getTzStores();
  } catch {
    stores = [];
  }

  return (
    <div className="bg-zinc-50 py-10 sm:py-14">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <Breadcrumbs items={[{ label: "Buy From TZ" }]} />
        <div className="mt-6">
          <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
            Local marketplace
          </p>
          <h1 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
            Buy From TZ
          </h1>
          <p className="mt-3 max-w-2xl text-base text-zinc-500">
            Four real Tanzanian businesses — fashion, lingerie, jewelry and beauty — with their own
            categories and inventory.
          </p>
        </div>

        {stores.length === 0 ? (
          <div className="mt-10 rounded-2xl border border-zinc-200 bg-white p-10 text-center">
            <StoreIcon className="mx-auto h-10 w-10 text-zinc-300" />
            <p className="mt-4 text-sm text-zinc-500">No storefront stores are available right now.</p>
          </div>
        ) : (
          <ul className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            {stores.map((store) => (
              <li key={store.id}>
                <Link
                  href={`/buy-from-tz/${store.slug}`}
                  className="flex h-full flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white transition hover:border-[#c9a227]/40 hover:shadow-md"
                >
                  <div
                    className="flex h-28 items-center justify-center"
                    style={{ backgroundColor: `${store.theme_color || "#c9a227"}22` }}
                  >
                    {store.logo_url ? (
                      <Image
                        src={store.logo_url}
                        alt={`${store.name} logo`}
                        width={72}
                        height={72}
                        className="h-16 w-16 rounded-xl object-cover"
                        unoptimized
                      />
                    ) : (
                      <span
                        className="flex h-16 w-16 items-center justify-center rounded-xl text-2xl font-bold text-white"
                        style={{ backgroundColor: store.theme_color || "#c9a227" }}
                      >
                        {store.name.charAt(0)}
                      </span>
                    )}
                  </div>
                  <div className="flex flex-1 flex-col p-4">
                    <h2 className="text-base font-semibold text-zinc-900">{store.name}</h2>
                    <p className="mt-1 line-clamp-3 text-sm text-zinc-500">
                      {store.description || "Local store on China Order TZ"}
                    </p>
                    <span className="mt-4 text-xs font-bold uppercase tracking-wide text-[#c9a227]">
                      Shop store →
                    </span>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
}
