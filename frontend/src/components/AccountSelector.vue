<template>
  <div class="card" id="account-selector">
    <div class="card-title">
      Account
      <span v-if="loading && accounts.length" class="account-refreshing">Refreshing</span>
    </div>

    <div v-if="showBlockingLoader" class="loading-container">
      <span class="spinner"></span>
      <span>Loading accounts...</span>
    </div>

    <div v-else>
      <div class="form-group">
        <label for="account-dropdown">Select account</label>
        <select
          id="account-dropdown"
          class="form-select"
          :value="selectedAccountId"
          @change="$emit('account-selected', $event.target.value)"
        >
          <option value="" disabled>Choose an account...</option>
          <option
            v-for="account in accounts"
            :key="account.account_id"
            :value="account.account_id"
          >
            {{ account.account_id }}{{ account.name ? ` — ${account.name}` : '' }}
          </option>
        </select>
      </div>

      <div class="selected-account-panel" :class="{ empty: !selectedAccount }">
        <template v-if="selectedAccount">
          <div class="selected-account-copy">
            <span class="selected-account-label">Active account</span>
            <strong>{{ selectedAccount.account_id }}</strong>
            <p>{{ selectedAccount.name || 'No display name provided' }}</p>
          </div>
          <div class="selected-account-balance">
            <span>Balance</span>
            <strong>{{ formattedBalance }}</strong>
          </div>
        </template>
        <template v-else>
          <span class="selected-account-label">Active account</span>
          <p>Select an account to make it the working context for the dashboard.</p>
        </template>
      </div>

      <div class="account-actions">
        <button
          id="btn-create-account"
          class="btn btn-secondary btn-sm"
          @click="$emit('create-account')"
        >
          <span>+</span> New Account
        </button>
        <span class="account-count">{{ accounts.length }} account{{ accounts.length !== 1 ? 's' : '' }}</span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'AccountSelector',
  props: {
    accounts: { type: Array, default: () => [] },
    selectedAccountId: { type: String, default: null },
    selectedAccount: { type: Object, default: null },
    selectedBalance: { type: String, default: null },
    loading: { type: Boolean, default: false },
  },

  computed: {
    showBlockingLoader() {
      return this.loading && this.accounts.length === 0;
    },

    formattedBalance() {
      if (this.selectedBalance === null) return 'Awaiting refresh';
      return Number.parseFloat(this.selectedBalance || 0).toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },
  },
};
</script>

<style scoped>
.account-refreshing {
  margin-left: auto;
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(15, 118, 110, 0.08);
  color: var(--color-primary);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
}

.selected-account-panel {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 18px;
  margin: 6px 0 14px;
  border-radius: 20px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.88), rgba(245, 250, 249, 0.82));
  border: 1px solid rgba(13, 34, 56, 0.08);
}

.selected-account-panel.empty {
  display: block;
}

.selected-account-label {
  display: block;
  margin-bottom: 6px;
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--color-primary);
}

.selected-account-copy strong,
.selected-account-balance strong {
  display: block;
  font-size: 1.1rem;
  color: var(--color-ink);
}

.selected-account-copy p,
.selected-account-panel.empty p,
.selected-account-balance span {
  color: var(--color-ink-soft);
  font-size: 0.86rem;
}

.selected-account-balance {
  text-align: right;
}

.account-actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 4px;
}

.account-count {
  font-size: 0.8rem;
  color: var(--color-muted);
}

@media (max-width: 640px) {
  .selected-account-panel,
  .account-actions {
    flex-direction: column;
    align-items: flex-start;
  }

  .selected-account-balance {
    text-align: left;
  }
}
</style>
