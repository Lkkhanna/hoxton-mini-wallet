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
:root {
  --color-ink: #0d2238;
  --color-ink-soft: #7d8691;
  --color-muted: #718096;
  --color-surface: rgba(255, 252, 246, 0.92);
  --color-surface-strong: #fffdf8;
  --color-surface-soft: #f5efe4;
  --color-border: rgba(13, 34, 56, 0.12);
  --color-border-strong: rgba(13, 34, 56, 0.2);
  --color-primary: #0f766e;
  --color-primary-hover: #115e59;
  --color-primary-bg: rgba(15, 118, 110, 0.1);
  --color-secondary: #c8a96b;
  --color-secondary-soft: rgba(200, 169, 107, 0.18);
  --color-success: #147d64;
  --color-success-bg: rgba(20, 125, 100, 0.12);
  --color-danger: #b4534c;
  --color-danger-bg: rgba(180, 83, 76, 0.12);
  --color-warning: #b8860b;
  --color-shadow: 0 24px 60px rgba(18, 41, 62, 0.12);
  --color-shadow-soft: 0 16px 35px rgba(18, 41, 62, 0.08);
  --radius-sm: 14px;
  --radius-md: 20px;
  --radius-lg: 28px;
  --radius-pill: 999px;
  --transition: all 0.2s ease;
  --font-family-sans: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --font-family-display: 'Cormorant Garamond', Georgia, serif;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

html {
  background-color: #f2ece2;
}

body {
  font-family: var(--font-family-sans);
  background:
    radial-gradient(circle at top left, rgba(200, 169, 107, 0.22), transparent 32%),
    radial-gradient(circle at top right, rgba(15, 118, 110, 0.16), transparent 28%),
    linear-gradient(180deg, #f6f0e7 0%, #fbf8f2 42%, #f2ece2 100%);
  background-attachment: fixed;
  background-color: #f2ece2;
  color: var(--color-ink);
  line-height: 1.6;
  min-height: 100vh;
}

button,
input,
select,
textarea {
  font: inherit;
}

a {
  color: inherit;
}

#wallet-app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.app-header {
  position: sticky;
  top: 0;
  z-index: 100;
  padding: 0 32px;
  background: linear-gradient(180deg, #c8f1ef 0%, #bdebe9 100%);
  border-bottom: 1px solid rgba(6, 47, 57, 0.08);
  backdrop-filter: blur(10px);
  box-shadow: 0 8px 24px rgba(18, 41, 62, 0.06);
}

.header-shell,
.app-main,
.app-footer-inner {
  max-width: 1280px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
}

.header-shell {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  min-height: 82px;
  color: #062f39;
}

.brand-lockup {
  display: flex;
  align-items: center;
  text-decoration: none;
}

.brand-logo {
  width: 218px;
  max-width: 100%;
  height: 30px;
  display: block;
  flex-shrink: 0;
  object-fit: contain;
  object-position: left center;
}

.header-nav {
  display: flex;
  align-items: center;
  gap: 30px;
  margin-left: auto;
  margin-right: 24px;
}

.header-nav a {
  color: #123c45;
  text-decoration: none;
  font-size: 0.96rem;
  font-weight: 600;
  letter-spacing: -0.01em;
}

.header-nav-action {
  display: none;
  align-items: center;
  justify-content: center;
  width: 100%;
  margin-top: 4px;
  padding: 12px 16px;
  border: 1px solid rgba(6, 47, 57, 0.1);
  border-radius: 16px;
  background: rgba(15, 118, 110, 0.08);
  color: #0f766e;
  font-weight: 800;
  text-align: center;
}

.desktop-header-action {
  display: inline-flex;
}

.mobile-nav-toggle {
  display: none;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  padding: 0;
  border-radius: 18px;
  border: 1.5px solid rgba(6, 47, 57, 0.18);
  background: rgba(255, 255, 255, 0.44);
  color: #062f39;
}

.mobile-nav-toggle-icon,
.mobile-nav-toggle-icon::before,
.mobile-nav-toggle-icon::after {
  display: block;
  width: 18px;
  height: 2px;
  border-radius: 999px;
  background: currentColor;
  transition: var(--transition);
}

.mobile-nav-toggle-icon {
  position: relative;
}

.mobile-nav-toggle-icon::before,
.mobile-nav-toggle-icon::after {
  content: '';
  position: absolute;
  left: 0;
}

.mobile-nav-toggle-icon::before {
  top: -6px;
}

.mobile-nav-toggle-icon::after {
  top: 6px;
}

.header-meta {
  display: flex;
  align-items: center;
  gap: 14px;
  flex-wrap: wrap;
  justify-content: flex-end;
  color: #062f39;
}

.app-main {
  width: 100%;
  padding: 28px 32px 32px;
  margin-top: 0;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: minmax(320px, 420px) minmax(0, 1fr);
  gap: 28px;
  align-items: start;
}

.left-column,
.right-column {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.card {
  background: linear-gradient(180deg, rgba(255, 253, 248, 0.95), rgba(248, 243, 235, 0.92));
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  padding: 26px;
  box-shadow: var(--color-shadow-soft);
  transition: var(--transition);
  backdrop-filter: blur(16px);
}

.card:hover {
  transform: translateY(-2px);
  border-color: var(--color-border-strong);
}

.app-footer {
  padding: 0 32px 26px;
}

.app-footer-inner {
  border-top: 1px solid rgba(13, 34, 56, 0.08);
  padding-top: 18px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  color: var(--color-muted);
  font-size: 0.82rem;
}

.card-title {
  font-size: 0.8rem;
  font-weight: 800;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--color-ink-soft);
  margin-bottom: 18px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.form-group {
  margin-bottom: 18px;
}

.form-group label {
  display: block;
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--color-ink-soft);
  margin-bottom: 8px;
}

.form-input,
.form-select {
  width: 100%;
  padding: 13px 16px;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid var(--color-border);
  border-radius: 16px;
  color: var(--color-ink);
  font-size: 0.95rem;
  transition: var(--transition);
  outline: none;
  appearance: none;
}

.form-input:focus,
.form-select:focus {
  border-color: rgba(15, 118, 110, 0.45);
  box-shadow: 0 0 0 4px rgba(15, 118, 110, 0.08);
  background: #fff;
}

.form-input::placeholder {
  color: #8d98a8;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 20px;
  font-size: 0.92rem;
  font-weight: 700;
  border: 1px solid transparent;
  border-radius: var(--radius-pill);
  cursor: pointer;
  transition: var(--transition);
  outline: none;
  text-decoration: none;
}

.btn:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.btn-primary {
  background: linear-gradient(135deg, #0f766e, #126a63);
  color: white;
  box-shadow: 0 10px 24px rgba(15, 118, 110, 0.16);
}

.btn-primary:hover:not(:disabled) {
  background: linear-gradient(135deg, #115e59, #0f5a55);
  transform: translateY(-1px);
}

.btn-secondary {
  background: transparent;
  border: 1.5px solid rgba(6, 47, 57, 0.75);
  color: #062f39;
}

.btn-secondary:hover:not(:disabled) {
  border-color: #062f39;
  background: rgba(255, 255, 255, 0.32);
}

.btn-sm {
  padding: 9px 16px;
  font-size: 0.84rem;
}

.btn-full {
  width: 100%;
}

.spinner {
  display: inline-block;
  width: 18px;
  height: 18px;
  border: 2px solid rgba(15, 118, 110, 0.18);
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
  padding: 32px 20px;
  gap: 12px;
  color: var(--color-ink-soft);
  font-size: 0.94rem;
}

.error-message {
  background: var(--color-danger-bg);
  color: var(--color-danger);
  padding: 12px 16px;
  border-radius: 16px;
  font-size: 0.86rem;
  border: 1px solid rgba(180, 83, 76, 0.18);
}

.soft-divider {
  width: 100%;
  height: 1px;
  background: linear-gradient(90deg, transparent, rgba(13, 34, 56, 0.12), transparent);
}

@media (max-width: 1080px) {
  .header-shell {
    flex-wrap: wrap;
    min-height: auto;
    padding: 14px 0;
  }

  .header-nav {
    order: 3;
    width: 100%;
    margin: 0;
    gap: 18px;
    flex-wrap: wrap;
  }

  .dashboard-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 900px) {
  .app-main {
    padding: 22px 18px 20px;
  }

  .app-header {
    padding: 0 18px;
  }

  .header-shell {
    flex-direction: row;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
  }

  .app-footer-inner {
    flex-direction: column;
    align-items: stretch;
  }

  .brand-lockup {
    width: auto;
    flex: 0 1 auto;
  }

  .brand-logo {
    width: 188px;
    height: 28px;
  }

  .mobile-nav-toggle {
    display: inline-flex;
    margin-left: auto;
    flex-shrink: 0;
  }

  .header-nav {
    display: none;
    order: 3;
    width: 100%;
    flex-direction: column;
    align-items: stretch;
    gap: 6px;
    padding: 14px;
    border-radius: 22px;
    background: rgba(255, 255, 255, 0.78);
    border: 1px solid rgba(6, 47, 57, 0.08);
    box-shadow: var(--color-shadow-soft);
  }

  .header-nav a {
    width: 100%;
    padding: 10px 8px;
    font-size: 1rem;
  }

  .header-nav.header-nav-open {
    display: flex;
  }

  .header-nav-action {
    display: inline-flex;
  }

  .header-meta {
    display: none;
  }

  .card {
    padding: 22px;
  }

  .app-footer {
    padding: 0 18px 22px;
  }
}
</style>
