export interface Product {
  id: string;
  productId: string;
  name: string;
  slug: string;
  brand: string;
  category: string;
  summary: string;
  description: string;
  status: string;
  sku: string;
  price: number;
  currency: string;
  available: number;
  specs: Record<string, string | number | boolean | string[]>;
  images: { id?: string; url: string; alt: string; primary: boolean }[];
}
export interface Page<T> {
  items: T[];
  total: number;
  page: number;
  limit: number;
}
export interface CartLine extends Product {
  quantity: number;
  amount: number;
  valid: boolean;
}
export interface Cart {
  groups: { id: string; name: string; components: string[] }[];
  items: CartLine[];
  subtotal: number;
  shipping: number;
  total: number;
  valid: boolean;
}
export interface Address {
  id?: string;
  name: string;
  street: string;
  city: string;
  postalCode: string;
  country: string;
}
export interface User {
  id: string;
  email: string;
  name: string;
  roles: string[];
  addresses: Address[];
  builds: { id: string; name: string; components: string[] }[];
}
export interface Issue {
  code: string;
  severity: 'ERROR' | 'WARNING' | 'INFO';
  message: string;
  suggestion: string;
  components: string[];
}
export interface BuildResult {
  valid: boolean;
  issues: Issue[];
  total: number;
  items: Product[];
}
export interface Order {
  id: string;
  number: string;
  status: string;
  paymentStatus: string;
  total: number;
  shipping: number;
  delivery: string;
  tracking: string;
  createdAt: string;
  expiresAt: string;
  shippingAddress: Address;
  lines: { name: string; sku: string; unitPrice: number; quantity: number; amount: number }[];
  history: { status: string; at: string; actor: string }[];
}
