<template>
  <div class="card balance-card" id="balance-display">
    <div class="card-title">
      Balance
    </div>

    <div v-if="!accountId" class="balance-placeholder">
      Select an account to reveal its live ledger balance.
    </div>

    <div v-else-if="loading" class="loading-container">
      <span class="spinner"></span>
      <span>Loading balance...</span>
    </div>

    <div v-else-if="error" class="error-message">
      {{ error }}
    </div>

    <div v-else class="balance-amount-container">
      <div class="balance-label">Current balance</div>
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
  background: linear-gradient(145deg, rgba(12, 37, 57, 0.98), rgba(12, 80, 88, 0.94));
  color: #fdf8ef;
}

.balance-placeholder {
  color: rgba(253, 248, 239, 0.68);
  font-size: 0.94rem;
  text-align: center;
  padding: 28px 0;
}

.balance-amount-container {
  text-align: center;
  padding: 12px 0 4px;
}

.balance-label {
  font-size: 0.76rem;
  color: rgba(253, 248, 239, 0.7);
  text-transform: uppercase;
  letter-spacing: 0.16em;
  margin-bottom: 12px;
}

.balance-amount {
  font-family: var(--font-family-display);
  font-size: 3.4rem;
  font-weight: 600;
  letter-spacing: -0.03em;
  transition: color 0.3s ease;
  line-height: 1;
}

.balance-amount .currency {
  font-size: 1.8rem;
  margin-right: 4px;
  opacity: 0.72;
}

.balance-amount.positive {
  color: #f7dfab;
}

.balance-amount.zero {
  color: rgba(253, 248, 239, 0.82);
}

.balance-amount.negative {
  color: #f2b2aa;
}

.balance-account {
  font-size: 0.86rem;
  color: rgba(253, 248, 239, 0.72);
  margin-top: 14px;
  font-family: monospace;
}
</style>
