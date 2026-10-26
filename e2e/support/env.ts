/** Where the front-end and the API under test live (override with env vars). */
export const BASE_URL = process.env.E2E_BASE_URL ?? "http://localhost:5173";
export const API_URL = (process.env.E2E_API_URL ?? "http://localhost:8000/api").replace(/\/+$/, "");
