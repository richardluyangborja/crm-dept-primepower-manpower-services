/** Centavos → ₱ display. Money is ALWAYS integer centavos on the wire (specs/03). */
export const formatPHP = (centavos: number | null | undefined): string =>
  '₱' + ((centavos ?? 0) / 100).toLocaleString('en-PH', { maximumFractionDigits: 0 });

export const isPHPhone = (v: string): boolean => /^\+63\d{10}$/.test(v);
