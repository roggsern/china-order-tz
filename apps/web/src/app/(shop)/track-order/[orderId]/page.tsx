import { redirect } from "next/navigation";

type PageProps = {
  params: Promise<{ orderId: string }>;
};

export default async function LegacyTrackOrderRedirectPage({ params }: PageProps) {
  const { orderId } = await params;
  redirect(`/track/${orderId}`);
}
