import { featuredProducts, formatPrice } from "@/lib/home-data";
import { CartIcon, StarIcon } from "./icons";

function RatingStars({ rating }: { rating: number }) {
  return (
    <div className="flex items-center gap-0.5">
      {[1, 2, 3, 4, 5].map((star) => (
        <StarIcon
          key={star}
          className={`h-3.5 w-3.5 ${star <= Math.round(rating) ? "text-[#c9a227]" : "text-zinc-200"}`}
          filled={star <= Math.round(rating)}
        />
      ))}
    </div>
  );
}

export function FeaturedProducts() {
  return (
    <section id="products" className="bg-white py-20 sm:py-24">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div className="text-center">
          <p className="text-sm font-semibold uppercase tracking-[0.2em] text-[#c9a227]">
            Hot Deals
          </p>
          <h2 className="mt-2 text-3xl font-bold tracking-tight text-zinc-900 sm:text-4xl">
            Featured Products
          </h2>
          <p className="mx-auto mt-3 max-w-2xl text-base text-zinc-500">
            Hand-picked premium products with unbeatable factory-direct prices — updated daily
            from our supplier network.
          </p>
        </div>

        <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
          {featuredProducts.map((product) => {
            const discount = Math.round(
              ((product.oldPrice - product.price) / product.oldPrice) * 100,
            );

            return (
              <article
                key={product.id}
                className="group flex flex-col overflow-hidden rounded-2xl border border-zinc-100 bg-white shadow-sm transition hover:-translate-y-1 hover:border-zinc-200 hover:shadow-xl"
              >
                <div className="relative">
                  <div
                    className={`flex aspect-square items-center justify-center bg-gradient-to-br ${product.gradient}`}
                  >
                    <span className="text-6xl drop-shadow-md transition group-hover:scale-110">
                      {product.emoji}
                    </span>
                  </div>
                  <span className="absolute left-3 top-3 rounded-full bg-red-600 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide text-white">
                    {product.badge}
                  </span>
                  {discount > 0 && (
                    <span className="absolute right-3 top-3 rounded-full bg-zinc-900/80 px-2 py-1 text-[10px] font-bold text-[#e8c547] backdrop-blur">
                      -{discount}%
                    </span>
                  )}
                </div>

                <div className="flex flex-1 flex-col p-4">
                  <h3 className="line-clamp-2 text-sm font-semibold leading-snug text-zinc-900">
                    {product.name}
                  </h3>

                  <div className="mt-2 flex items-center gap-2">
                    <RatingStars rating={product.rating} />
                    <span className="text-xs text-zinc-400">
                      {product.rating} ({product.reviews.toLocaleString()})
                    </span>
                  </div>

                  <div className="mt-3 flex items-baseline gap-2">
                    <span className="text-lg font-bold text-red-600">{formatPrice(product.price)}</span>
                    <span className="text-sm text-zinc-400 line-through">
                      {formatPrice(product.oldPrice)}
                    </span>
                  </div>

                  <button
                    type="button"
                    className="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-zinc-900 py-2.5 text-sm font-semibold text-white transition hover:bg-[#c9a227] hover:text-zinc-900"
                  >
                    <CartIcon className="h-4 w-4" />
                    Add to Cart
                  </button>
                </div>
              </article>
            );
          })}
        </div>
      </div>
    </section>
  );
}
