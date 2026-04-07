<template>
  <DashboardView />
</template>

<script>
import DashboardView from './views/DashboardView.vue';

export default {
  name: 'App',
  components: {
    DashboardView,
  },
};
</script>

<style>
/* ─── Global Reset & Variables ─────────────────────────────────── */
:root {
  --color-bg: #0f1117;
  --color-bg-card: #1a1d27;
  --color-bg-card-hover: #22263a;
  --color-bg-input: #141722;
  --color-border: #2a2e3f;
  --color-border-focus: #5b6abf;
  --color-text: #e4e6ef;
  --color-text-secondary: #8b8fa3;
  --color-text-muted: #5a5e71;
  --color-primary: #6366f1;
  --color-primary-hover: #818cf8;
  --color-primary-bg: rgba(99, 102, 241, 0.1);
  --color-success: #10b981;
  --color-success-bg: rgba(16, 185, 129, 0.1);
  --color-danger: #ef4444;
  --color-danger-bg: rgba(239, 68, 68, 0.1);
  --color-warning: #f59e0b;
  --radius-sm: 8px;
  --radius-md: 12px;
  --radius-lg: 16px;
  --shadow-card: 0 4px 24px rgba(0, 0, 0, 0.25);
  --shadow-glow: 0 0 20px rgba(99, 102, 241, 0.15);
  --transition: all 0.2s ease;
  --font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  font-family: var(--font-family);
  background: var(--color-bg);
  color: var(--color-text);
  line-height: 1.6;
  min-height: 100vh;
}

/* ─── App Layout ───────────────────────────────────────────────── */
#wallet-app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.app-header {
  background: linear-gradient(135deg, #1a1d2e 0%, #252842 100%);
  border-bottom: 1px solid var(--color-border);
  padding: 24px 32px;
}

.header-content {
  max-width: 1280px;
  margin: 0 auto;
}

.logo {
  display: flex;
  align-items: center;
  gap: 12px;
}

.logo-icon {
  font-size: 32px;
}

.logo h1 {
  font-size: 24px;
  font-weight: 700;
  background: linear-gradient(135deg, #818cf8, #6366f1);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.tagline {
  color: var(--color-text-secondary);
  font-size: 13px;
  margin-top: 4px;
  padding-left: 44px;
}

.app-main {
  flex: 1;
  max-width: 1280px;
  width: 100%;
  margin: 0 auto;
  padding: 32px;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: 420px 1fr;
  gap: 32px;
  align-items: start;
}

.left-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.right-column {
  min-height: 400px;
}

.app-footer {
  text-align: center;
  padding: 20px;
  color: var(--color-text-muted);
  font-size: 12px;
  border-top: 1px solid var(--color-border);
}

/* ─── Shared Card Style ────────────────────────────────────────── */
.card {
  background: var(--color-bg-card);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 24px;
  box-shadow: var(--shadow-card);
  transition: var(--transition);
}

.card:hover {
  border-color: rgba(99, 102, 241, 0.2);
}

.card-title {
  font-size: 14px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.8px;
  color: var(--color-text-secondary);
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}

/* ─── Shared Form Styles ──────────────────────────────────────── */
.form-group {
  margin-bottom: 16px;
}

.form-group label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-secondary);
  margin-bottom: 6px;
}

.form-input,
.form-select {
  width: 100%;
  padding: 10px 14px;
  background: var(--color-bg-input);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  color: var(--color-text);
  font-size: 14px;
  font-family: var(--font-family);
  transition: var(--transition);
  outline: none;
}

.form-input:focus,
.form-select:focus {
  border-color: var(--color-border-focus);
  box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}

.form-input::placeholder {
  color: var(--color-text-muted);
}

/* ─── Buttons ──────────────────────────────────────────────────── */
.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 20px;
  font-size: 14px;
  font-weight: 600;
  font-family: var(--font-family);
  border: none;
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: var(--transition);
  outline: none;
}

.btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-primary {
  background: linear-gradient(135deg, #6366f1, #818cf8);
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: linear-gradient(135deg, #818cf8, #a5b4fc);
  box-shadow: var(--shadow-glow);
  transform: translateY(-1px);
}

.btn-secondary {
  background: var(--color-bg-input);
  border: 1px solid var(--color-border);
  color: var(--color-text);
}

.btn-secondary:hover:not(:disabled) {
  border-color: var(--color-border-focus);
  background: var(--color-bg-card-hover);
}

.btn-sm {
  padding: 6px 14px;
  font-size: 13px;
}

.btn-full {
  width: 100%;
}

/* ─── Loading Spinner ──────────────────────────────────────────── */
.spinner {
  display: inline-block;
  width: 18px;
  height: 18px;
  border: 2px solid rgba(99, 102, 241, 0.2);
  border-top-color: var(--color-primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.loading-container {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 32px;
  gap: 12px;
  color: var(--color-text-secondary);
  font-size: 14px;
}

/* ─── Error State ──────────────────────────────────────────────── */
.error-message {
  background: var(--color-danger-bg);
  color: var(--color-danger);
  padding: 12px 16px;
  border-radius: var(--radius-sm);
  font-size: 13px;
  border: 1px solid rgba(239, 68, 68, 0.2);
}

/* ─── Responsive ───────────────────────────────────────────────── */
@media (max-width: 900px) {
  .dashboard-grid {
    grid-template-columns: 1fr;
  }

  .app-main {
    padding: 16px;
  }

  .app-header {
    padding: 16px 20px;
  }
}
</style>
