import { redirect } from "next/navigation";

/** Legacy create route — canonical product creation lives on /admin/products. */
export default function NewProductPage() {
  redirect("/admin/products?create=1");
}
