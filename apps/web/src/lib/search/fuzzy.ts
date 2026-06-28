/** Maximum edit distance allowed for fuzzy term matching, scaled by term length. */
export function maxFuzzyDistance(term: string): number {
  const length = term.length;
  if (length <= 2) {
    return 0;
  }
  if (length <= 5) {
    return 1;
  }
  return 2;
}

/** Levenshtein edit distance — used for typo-tolerant matching. */
export function levenshteinDistance(a: string, b: string): number {
  if (a === b) {
    return 0;
  }
  if (a.length === 0) {
    return b.length;
  }
  if (b.length === 0) {
    return a.length;
  }

  const rows = a.length + 1;
  const cols = b.length + 1;
  const matrix: number[] = new Array(cols);

  for (let col = 0; col < cols; col += 1) {
    matrix[col] = col;
  }

  for (let row = 1; row < rows; row += 1) {
    let previous = matrix[0];
    matrix[0] = row;

    for (let col = 1; col < cols; col += 1) {
      const temp = matrix[col];
      const cost = a[row - 1] === b[col - 1] ? 0 : 1;
      matrix[col] = Math.min(
        matrix[col] + 1,
        matrix[col - 1] + 1,
        previous + cost,
      );
      previous = temp;
    }
  }

  return matrix[cols - 1]!;
}

export function isFuzzyMatch(source: string, target: string): boolean {
  if (!source || !target) {
    return false;
  }
  if (source === target) {
    return true;
  }

  const maxDistance = maxFuzzyDistance(target);
  if (maxDistance === 0) {
    return false;
  }

  return levenshteinDistance(source, target) <= maxDistance;
}
