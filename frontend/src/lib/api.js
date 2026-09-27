import axios from "axios";

const BACKEND_URL = process.env.REACT_APP_BACKEND_URL || "";
export const API = `${BACKEND_URL}/api`;

// Filament admin panel, served by Laravel (not a SPA route).
export const ADMIN_URL = `${BACKEND_URL}/admin`;

export const api = axios.create({
  baseURL: API,
  withCredentials: true,
  withXSRFToken: true,
  headers: { "Content-Type": "application/json", Accept: "application/json" },
});

// Sanctum SPA auth: fetch the XSRF-TOKEN cookie before any state-changing auth call.
export function ensureCsrf() {
  return axios.get(`${BACKEND_URL}/sanctum/csrf-cookie`, { withCredentials: true });
}

// Accepts a Laravel error body ({ message, errors }) or a plain string.
export function formatApiError(data) {
  if (data == null) return "Something went wrong. Please try again.";
  if (typeof data === "string") return data;
  if (data.errors && typeof data.errors === "object") {
    const first = Object.values(data.errors).flat()[0];
    if (typeof first === "string") return first;
  }
  if (typeof data.message === "string" && data.message) return data.message;
  return "Something went wrong. Please try again.";
}
