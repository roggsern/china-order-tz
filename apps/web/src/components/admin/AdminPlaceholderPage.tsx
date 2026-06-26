interface AdminPlaceholderPageProps {
  title: string;
  description: string;
  icon: string;
}

export function AdminPlaceholderPage({ title, description, icon }: AdminPlaceholderPageProps) {
  return (
    <div className="p-4 sm:p-6 lg:p-8">
      <h1 className="text-xl font-semibold text-zinc-900 sm:text-2xl">{title}</h1>
      <p className="mt-0.5 text-sm text-zinc-500">{description}</p>

      <div className="admin-card mt-6 flex flex-col items-center px-6 py-16 text-center">
        <span className="text-5xl">{icon}</span>
        <h2 className="mt-4 text-base font-semibold text-zinc-900">Coming in a future release</h2>
        <p className="mt-2 max-w-md text-sm text-zinc-500">
          This section is reserved for backend-connected management. Product management is fully
          available now under Products.
        </p>
      </div>
    </div>
  );
}
