<template>
  <div class="card transaction-card" id="transaction-list">
    <div class="card-title">
      <span>📋</span> Transaction History
      <span v-if="transactions.length" class="tx-count">{{ transactions.length }}</span>
    </div>

    <!-- No account selected -->
    <div v-if="!accountId" class="empty-state">
      <div class="empty-icon">📊</div>
      <p>Select an account to view transactions</p>
    </div>

    <!-- Loading -->
    <div v-else-if="loading" class="loading-container">
      <span class="spinner"></span>
      <span>Loading transactions...</span>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="error-message">
      {{ error }}
    </div>

    <!-- Empty -->
    <div v-else-if="transactions.length === 0" class="empty-state">
      <div class="empty-icon">📭</div>
      <p>No transactions yet</p>
    </div>

    <!-- Transaction List -->
    <div v-else class="tx-list">
      <div
        v-for="tx in transactions"
        :key="tx.id"
        class="tx-item"
        :class="tx.type"
      >
        <div class="tx-left">
          <div class="tx-type-badge" :class="tx.type">
            {{ tx.type === 'credit' ? '↓' : '↑' }}
          </div>
          <div class="tx-details">
            <div class="tx-description">{{ tx.description || formatDescription(tx) }}</div>
            <div class="tx-meta">
              <span class="tx-id" :title="tx.transaction_id">{{ truncateId(tx.transaction_id) }}</span>
              <span class="tx-dot">·</span>
              <span class="tx-time">{{ formatTime(tx.timestamp) }}</span>
            </div>
          </div>
        </div>
        <div class="tx-amount" :class="tx.type">
          {{ tx.type === 'credit' ? '+' : '-' }}${{ formatAmount(tx.amount) }}
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'TransactionList',
  props: {
    accountId: { type: String, default: null },
    transactions: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: String, default: null },
  },

  methods: {
    formatAmount(amount) {
      return parseFloat(amount).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    },

    formatTime(timestamp) {
      const date = new Date(timestamp);
      const now = new Date();
      const diffMs = now - date;
      const diffMins = Math.floor(diffMs / 60000);
      const diffHours = Math.floor(diffMs / 3600000);
      const diffDays = Math.floor(diffMs / 86400000);

      if (diffMins < 1) return 'Just now';
      if (diffMins < 60) return `${diffMins}m ago`;
      if (diffHours < 24) return `${diffHours}h ago`;
      if (diffDays < 7) return `${diffDays}d ago`;
      return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    },

    truncateId(id) {
      if (!id) return '';
      if (id.length <= 12) return id;
      return id.substring(0, 8) + '...';
    },

    formatDescription(tx) {
      if (tx.type === 'credit') {
        return `Received from ${tx.counterparty}`;
      }
      return `Sent to ${tx.counterparty}`;
    },
  },
};
</script>

<style scoped>
.transaction-card {
  min-height: 300px;
}

.tx-count {
  background: var(--color-primary-bg);
  color: var(--color-primary);
  padding: 2px 8px;
  border-radius: 12px;
  font-size: 12px;
  margin-left: auto;
}

.empty-state {
  text-align: center;
  padding: 48px 20px;
  color: var(--color-text-muted);
}

.empty-icon {
  font-size: 40px;
  margin-bottom: 12px;
  opacity: 0.5;
}

.empty-state p {
  font-size: 14px;
}

/* ─── Transaction Items ────────────────────────────────────────── */
.tx-list {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.tx-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 16px;
  border-radius: var(--radius-sm);
  transition: var(--transition);
  border: 1px solid transparent;
}

.tx-item:hover {
  background: var(--color-bg-card-hover);
  border-color: var(--color-border);
}

.tx-left {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
  flex: 1;
}

.tx-type-badge {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 16px;
  font-weight: 700;
  flex-shrink: 0;
}

.tx-type-badge.credit {
  background: var(--color-success-bg);
  color: var(--color-success);
}

.tx-type-badge.debit {
  background: var(--color-danger-bg);
  color: var(--color-danger);
}

.tx-details {
  min-width: 0;
}

.tx-description {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.tx-meta {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 2px;
}

.tx-id {
  font-size: 11px;
  color: var(--color-text-muted);
  font-family: monospace;
}

.tx-dot {
  color: var(--color-text-muted);
  font-size: 10px;
}

.tx-time {
  font-size: 11px;
  color: var(--color-text-muted);
}

.tx-amount {
  font-size: 15px;
  font-weight: 600;
  white-space: nowrap;
  margin-left: 16px;
}

.tx-amount.credit {
  color: var(--color-success);
}

.tx-amount.debit {
  color: var(--color-danger);
}
</style>
