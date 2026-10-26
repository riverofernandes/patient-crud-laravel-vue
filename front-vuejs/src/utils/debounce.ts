// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function debounce<T extends (...args: any[]) => void>(
  fn: T,
  wait = 400
) {
  let timer: ReturnType<typeof setTimeout> | undefined;

  const debounced = (...args: Parameters<T>): void => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => fn(...args), wait);
  };

  debounced.cancel = (): void => {
    if (timer) clearTimeout(timer);
    timer = undefined;
  };

  return debounced;
}
