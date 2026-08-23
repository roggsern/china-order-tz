export function returnsRootQueryKey() {
  return ['returns'] as const;
}

export function returnsListQueryKey() {
  return ['returns', 'list'] as const;
}

export function returnDetailQueryKey(returnId: string) {
  return ['returns', 'detail', returnId] as const;
}
