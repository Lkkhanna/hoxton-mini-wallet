<template>
  <div class="card balance-card" id="balance-display">
    <div class="card-title">
      <span>💰</span> Balance
    </div>

    <!-- No account selected -->
    <div v-if="!accountId" class="balance-placeholder">
      Select an account to view balance
    </div>

    <!-- Loading -->
    <div v-else-if="loading" class="loading-container">
      <span class="spinner"></span>
      <span>Loading balance...</span>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error-message">
      {{ error }}
    </div>

    <!-- Balance Display -->
    <div v-else class="balance-amount-container">
      <div class="balance-label">Current Balance</div>
      <div class="balance-amount" :class="balanceClass">
        <span class="currency">$</span>{{ formattedBalance }}
      </div>
      <div class="balance-account">{{ accountId }}</div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'BalanceDisplay',
  props: {
    accountId: { type: String, default: null },
    balance: { type: String, default: null },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
  },

  computed: {
    formattedBalance() {
      if (this.balance === null) return '0.00';
      return parseFloat(this.balance).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },

    balanceClass() {
      const val = parseFloat(this.balance || 0);
      if (val > 0) return 'positive';
      if (val === 0) return 'zero';
      return 'negative';
    },
  },
};
</script>

<style scoped>
.balance-card {
  background: linear-gradient(135deg, #1a1d2e 0%, #1e2235 100%);
}

.balance-placeholder {
  color: var(--color-text-muted);
  font-size: 14px;
  text-align: center;
  padding: 20px 0;
}

.balance-amount-container {
  text-align: center;
  padding: 8px 0;
}

.balance-label {
  font-size: 12px;
  color: var(--color-text-muted);
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 8px;
}

.balance-amount {
  font-size: 36px;
  font-weight: 700;
  letter-spacing: -0.5px;
  transition: color 0.3s ease;
}

.balance-amount .currency {
  font-size: 22px;
  margin-right: 2px;
  opacity: 0.7;
}

.balance-amount.positive {
  color: var(--color-success);
}

.balance-amount.zero {
  color: var(--color-text-secondary);
}

.balance-amount.negative {
  color: var(--color-danger);
}

.balance-account {
  font-size: 13px;
  color: var(--color-text-muted);
  margin-top: 8px;
  font-family: monospace;
}
</style>
