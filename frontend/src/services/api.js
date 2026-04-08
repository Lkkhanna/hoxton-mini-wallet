import axios from 'axios';

/**
 * API Service Layer
 *
 * All API calls go through this module for clean separation between
 * UI logic and network communication.
 *
 * In Docker, the Vue dev server proxies /api to the backend container.
 * In production, API_URL would point to the deployed backend.
 */
const apiClient = axios.create({
  baseURL: process.env.VUE_APP_API_URL || '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  timeout: 10000,
});

// Normalize successful responses to the shared API envelope and attach a
// structured apiError object so components do not need axios-specific parsing.
apiClient.interceptors.response.use(
  response => response.data,
  error => {
    const errResponse = error.response;
    if (errResponse) {
      // Attach structured error data for the UI
      error.apiError = {
        status: errResponse.status,
        success: errResponse.data?.success ?? false,
        message: errResponse.data?.message || 'An unexpected error occurred',
        errors: errResponse.data?.errors || {},
        data: errResponse.data?.data || {},
        meta: errResponse.data?.meta || {},
      };
    } else {
      error.apiError = {
        status: 0,
        success: false,
        message: 'Network error — please check your connection',
        errors: {},
        data: {},
        meta: {},
      };
    }
    return Promise.reject(error);
  }
);

export default {
  // ─── Account APIs ─────────────────────────────────────────────
  listAccounts() {
    return apiClient.get('/accounts');
  },

  createAccount(data) {
    return apiClient.post('/accounts', data);
  },

  getBalance(accountId) {
    return apiClient.get(`/accounts/${accountId}/balance`);
  },

  getTransactions(accountId, params = {}) {
    return apiClient.get(`/accounts/${accountId}/transactions`, { params });
  },

  // ─── Transfer API ────────────────────────────────────────────
  transfer(data) {
    return apiClient.post('/transfers', data);
  },
};
